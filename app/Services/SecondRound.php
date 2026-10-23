<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Vote;
use PDO;

/**
 * Second tour (lot 5, migration 026 ; règlement électoral 2026, art. 24 :
 * second tour en cas d'égalité entre les candidats arrivés en tête).
 *
 * Décisions du bureau (2026-10-12) : créé après la publication des
 * résultats du 1er tour, il ne reprend QUE les postes à égalité et, pour
 * chacun, QUE les ex aequo encore en lice — copiés automatiquement, pour
 * qu'aucun ne puisse être oublié ni ajouté. Il démarre directement en
 * 'candidacies_closed' (pas de nouveau dépôt) ; la suite est celle de tout
 * scrutin : ouverture, vote anonyme par jeton, clôture, publication. La
 * Commission électorale du 1er tour est reprise. Les
 * électeurs suivent la même règle que pour tout scrutin. Un seul second
 * tour par élection (index UNIQUE elections.first_round_id), jamais de
 * troisième : une égalité persistante revient à la Commission
 * (App\Models\PositionDecision).
 */
final class SecondRound
{
    /**
     * Postes qui appellent un second tour : égalité en tête et au moins
     * deux ex aequo encore en lice ('approved'). Une égalité où un ex aequo
     * s'est retiré, faute de deux candidats à départager, relève de la
     * Commission (PositionDecision::eligibility()).
     *
     * @return array<int, array{position: array, candidate_ids: list<int>}> indexé par position_id
     */
    public static function tiedPositions(array $election): array
    {
        if ($election['status'] !== 'published' || $election['first_round_id'] !== null) {
            return [];
        }

        $tied = [];

        foreach (Vote::groupResultsByPosition((int) $election['id']) as $positionId => $group) {
            if ($group['outcome']['status'] !== 'tie') {
                continue;
            }

            $inRace = self::approvedAmong($group['outcome']['candidate_ids']);

            if (count($inRace) >= 2) {
                $tied[$positionId] = ['position' => $group['position'], 'candidate_ids' => $inRace];
            }
        }

        return $tied;
    }

    /** Le second tour d'une élection, s'il existe. */
    public static function of(int $firstRoundId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM elections WHERE first_round_id = :id LIMIT 1');
        $stmt->execute(['id' => $firstRoundId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Crée le second tour. Tout est revérifié ici, 1er tour verrouillé :
     * publié, pas lui-même un second tour, pas déjà doté d'un second tour,
     * au moins un poste à égalité.
     *
     * @return array{id: ?int, error: ?string} error : 'not_allowed', 'already_exists', 'no_tie'
     */
    public static function create(int $firstRoundId, string $title, ?string $description, string $startAt, string $endAt): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        $copiedPhotos = [];

        try {
            $lock = $db->prepare('SELECT * FROM elections WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $firstRoundId]);
            $firstRound = $lock->fetch();

            if ($firstRound === false || $firstRound['status'] !== 'published' || $firstRound['first_round_id'] !== null) {
                $db->rollBack();

                return ['id' => null, 'error' => 'not_allowed'];
            }

            if (self::of($firstRoundId) !== null) {
                $db->rollBack();

                return ['id' => null, 'error' => 'already_exists'];
            }

            $tied = self::tiedPositions($firstRound);

            if ($tied === []) {
                $db->rollBack();

                return ['id' => null, 'error' => 'no_tie'];
            }

            $db->prepare(
                // Liste publiée dès la création (migration 027) : ses
                // candidats sont les ex aequo, déjà connus de tous. Heure
                // fournie par PHP, comme les autres dates saisies, et non
                // NOW() : elle est relue par Election::candidateListPublished()
                // avec l'horloge de PHP, dont le fuseau peut différer de
                // celui de MySQL.
                "INSERT INTO elections (title, description, candidacy_deadline, candidate_list_at, candidacy_fee_required, candidacy_fee_details,
                                        rules_document_id, first_round_id, start_at, end_at, handover_date, status)
                 VALUES (:title, :description, NULL, :candidate_list_at, 0, NULL, :rules_document_id, :first_round_id, :start_at, :end_at,
                         :handover_date, 'candidacies_closed')"
            )->execute([
                'title'             => $title,
                'description'       => $description,
                'rules_document_id' => $firstRound['rules_document_id'],
                'first_round_id'    => $firstRoundId,
                'start_at'          => $startAt,
                'end_at'            => $endAt,
                'handover_date'     => $firstRound['handover_date'],
                'candidate_list_at' => date('Y-m-d H:i:s'),
            ]);
            $secondRoundId = (int) $db->lastInsertId();

            $insertPosition = $db->prepare(
                'INSERT INTO election_positions (election_id, name, description, max_votes, sort_order)
                 VALUES (:election_id, :name, :description, :max_votes, :sort_order)'
            );
            $findCandidate = $db->prepare("SELECT * FROM candidates WHERE id = :id AND status = 'approved'");
            $insertCandidate = $db->prepare(
                "INSERT INTO candidates (election_position_id, member_id, ballot_number, bio, manifesto, photo, status, submitted_at, decided_at)
                 VALUES (:position_id, :member_id, :ballot_number, :bio, :manifesto, :photo, 'approved', :submitted_at, NOW())"
            );

            foreach ($tied as $entry) {
                $position = $entry['position'];
                $insertPosition->execute([
                    'election_id' => $secondRoundId,
                    'name'        => $position['name'],
                    'description' => $position['description'],
                    'max_votes'   => $position['max_votes'],
                    'sort_order'  => $position['sort_order'],
                ]);
                $newPositionId = (int) $db->lastInsertId();

                foreach ($entry['candidate_ids'] as $candidateId) {
                    $findCandidate->execute(['id' => $candidateId]);
                    $candidate = $findCandidate->fetch(PDO::FETCH_ASSOC);

                    // Revérifié sous verrou : toujours en lice.
                    if ($candidate === false) {
                        throw new \RuntimeException('Candidat ex aequo plus en lice pendant la création du second tour.');
                    }

                    $photo = self::copyPhoto($candidate['photo']);

                    if ($photo !== null) {
                        $copiedPhotos[] = $photo;
                    }

                    $insertCandidate->execute([
                        'position_id'   => $newPositionId,
                        'member_id'     => $candidate['member_id'],
                        'ballot_number' => $candidate['ballot_number'],
                        'bio'           => $candidate['bio'],
                        'manifesto'     => $candidate['manifesto'],
                        'photo'         => $photo,
                        'submitted_at'  => $candidate['submitted_at'],
                    ]);
                }
            }

            // La Commission du 1er tour reste celle du second (art. 6) :
            // c'est elle qui tranchera une égalité persistante. Reste
            // modifiable depuis la fiche du second tour, comme toute
            // Commission avant l'ouverture du vote.
            $db->prepare(
                'INSERT INTO election_commission_members (election_id, member_id, display_name, role)
                 SELECT :second_round_id, member_id, display_name, role
                 FROM election_commission_members WHERE election_id = :first_round_id'
            )->execute(['second_round_id' => $secondRoundId, 'first_round_id' => $firstRoundId]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            foreach ($copiedPhotos as $photo) {
                @unlink(Upload::publicPath($photo));
            }

            // 23000 : index UNIQUE first_round_id — un second tour créé entre-temps.
            if ($e instanceof \PDOException && $e->getCode() === '23000') {
                return ['id' => null, 'error' => 'already_exists'];
            }

            throw $e;
        }

        return ['id' => $secondRoundId, 'error' => null];
    }

    /**
     * @param list<int> $candidateIds
     * @return list<int> ceux encore 'approved', dans le même ordre
     */
    private static function approvedAmong(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id FROM candidates WHERE status = 'approved' AND id IN ($placeholders)"
        );
        $stmt->execute(array_values($candidateIds));
        $approved = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return array_values(array_filter($candidateIds, static fn (int $id): bool => in_array($id, $approved, true)));
    }

    /**
     * Copie de la photo publique, sous un nouveau nom : chaque élection
     * garde son fichier — remplacer la photo au second tour (qui supprime
     * l'ancienne) ne doit jamais effacer celle du 1er tour.
     */
    private static function copyPhoto(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }

        $source = Upload::publicPath($photo);

        if (!is_file($source)) {
            return null;
        }

        $extension = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
        $relative = 'candidates/' . bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');

        return copy($source, Upload::publicPath($relative)) ? $relative : null;
    }
}
