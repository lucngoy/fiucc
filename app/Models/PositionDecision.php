<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Décision de la Commission électorale sur un poste (lot 5, migration 026 ;
 * règlement électoral 2026, art. 24 et 30). La Commission tranche, l'admin
 * enregistre — seulement quand le vote n'a désigné personne et que le
 * règlement renvoie à la Commission :
 *
 * - égalité persistante au SECOND tour (au 1er tour, une égalité appelle
 *   un second tour, jamais une décision — sauf s'il ne reste pas deux
 *   ex aequo encore en lice pour le disputer) ;
 * - candidat seul en tête retiré pendant le vote.
 *
 * Une décision par poste (UNIQUE), jamais modifiée. Elle ne touche pas aux
 * chiffres du vote : les résultats l'affichent à côté. L'admin qui
 * l'enregistre est tracé dans activity_logs, pas dans la table (voir la
 * migration 026 : garde-fou d'anonymat du schéma).
 */
final class PositionDecision extends Model
{
    public const REASON_MAX_LENGTH = 2000;

    /**
     * Décisions d'une élection, indexées par poste.
     *
     * @return array<int, array> position_id => décision (avec candidate_name, member_id)
     */
    public static function forElection(int $electionId): array
    {
        $stmt = static::db()->prepare(
            'SELECT d.*, c.member_id, m.full_name AS candidate_name
             FROM election_position_decisions d
             INNER JOIN election_positions ep ON ep.id = d.election_position_id
             INNER JOIN candidates c ON c.id = d.candidate_id
             INNER JOIN members m ON m.id = c.member_id
             WHERE ep.election_id = :election_id'
        );
        $stmt->execute(['election_id' => $electionId]);
        $decisions = [];

        foreach ($stmt->fetchAll() as $row) {
            $decisions[(int) $row['election_position_id']] = $row;
        }

        return $decisions;
    }

    /**
     * Candidats encore en lice ('approved') d'un poste, y compris ceux qui
     * n'ont reçu aucune voix (absents des résultats agrégés).
     *
     * @return array<int, array{id: int, name: string}> indexé par candidate_id
     */
    public static function approvedCandidates(int $positionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT c.id, m.full_name AS name
             FROM candidates c
             INNER JOIN members m ON m.id = c.member_id
             WHERE c.election_position_id = :position_id AND c.status = 'approved'
             ORDER BY c.ballot_number ASC, m.full_name ASC"
        );
        $stmt->execute(['position_id' => $positionId]);
        $candidates = [];

        foreach ($stmt->fetchAll() as $row) {
            $candidates[(int) $row['id']] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }

        return $candidates;
    }

    /**
     * La Commission peut-elle statuer sur ce poste, et entre qui ? null si
     * aucune décision n'est prévue (vainqueur désigné par le vote, aucun
     * suffrage, égalité de 1er tour à départager par un second tour,
     * élection pas encore publiée, décision déjà enregistrée).
     *
     * @param array $election ligne elections
     * @param array $group    une entrée de Vote::groupResultsByPosition()
     * @return array{kind: string, candidates: array<int, array{id: int, name: string}>}|null
     */
    public static function eligibility(array $election, array $group): ?array
    {
        if ($election['status'] !== 'published' || ($group['decision'] ?? null) !== null) {
            return null;
        }

        $outcome = $group['outcome'];
        $positionId = (int) $group['position']['id'];
        $approved = self::approvedCandidates($positionId);

        if ($outcome['status'] === 'tie') {
            $tiedInRace = array_intersect_key($approved, array_flip($outcome['candidate_ids']));
            $isSecondRound = $election['first_round_id'] !== null;

            if (!$isSecondRound && count($tiedInRace) >= 2) {
                return null;
            }

            $choices = $tiedInRace !== [] ? $tiedInRace : $approved;

            return $choices !== [] ? ['kind' => $isSecondRound ? 'tie' : 'tie_withdrawn', 'candidates' => $choices] : null;
        }

        if ($outcome['status'] === 'withdrawn') {
            return $approved !== [] ? ['kind' => 'withdrawn', 'candidates' => $approved] : null;
        }

        return null;
    }

    /**
     * Enregistre la décision. Tout est revérifié ici, élection verrouillée,
     * même si le contrôleur l'a déjà fait : poste de cette élection,
     * élection publiée, décision prévue sur ce poste, candidat parmi les
     * choix permis. L'index UNIQUE empêche une seconde décision.
     *
     * @return string|null null si enregistrée, sinon un code d'erreur
     *                     ('not_allowed', 'invalid_candidate', 'already_decided')
     */
    public static function record(int $electionId, int $positionId, int $candidateId, string $reason): ?string
    {
        $db = static::db();
        $db->beginTransaction();

        try {
            $lock = $db->prepare('SELECT * FROM elections WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $electionId]);
            $election = $lock->fetch();

            $groups = $election !== false ? Vote::groupResultsByPosition($electionId) : [];
            $group = $groups[$positionId] ?? null;

            if ($group === null) {
                $db->rollBack();

                return 'not_allowed';
            }

            if ($group['decision'] !== null) {
                $db->rollBack();

                return 'already_decided';
            }

            $eligibility = self::eligibility($election, $group);

            if ($eligibility === null) {
                $db->rollBack();

                return 'not_allowed';
            }

            if (!isset($eligibility['candidates'][$candidateId])) {
                $db->rollBack();

                return 'invalid_candidate';
            }

            $db->prepare(
                'INSERT INTO election_position_decisions (election_position_id, candidate_id, reason, decided_at)
                 VALUES (:position_id, :candidate_id, :reason, NOW())'
            )->execute([
                'position_id'  => $positionId,
                'candidate_id' => $candidateId,
                'reason'       => $reason,
            ]);

            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();

            // 23000 : index UNIQUE — une décision arrivée entre-temps.
            if ($e->getCode() === '23000') {
                return 'already_decided';
            }

            throw $e;
        }

        Government::syncFromElectionResults($electionId, $positionId);

        return null;
    }
}
