<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Base32;
use PDOException;

/**
 * Enregistrement du bulletin (FIUCC-66, sections 20-21 du cahier des
 * charges) — la seule classe de tout ce projet où l'identité d'un membre
 * et le contenu de son bulletin coexistent dans la même portée PHP. Voir
 * App\Controllers\VoteController::submit() pour ce qui n'en sort jamais
 * (affichage, journal, session) — cette classe, elle, ne garantit que
 * l'écriture : deux tables, aucune clé commune (règle 2).
 *
 * castBallot() prend les sélections en UN SEUL paramètre tableau, jamais
 * éclatées en arguments scalaires — vérifié empiriquement (conversation
 * FIUCC-66) que Exception::getTraceAsString(), ce que public/index.php
 * journalise réellement en cas d'exception, réduit un argument tableau à
 * la chaîne littérale "Array" (jamais son contenu), alors qu'un argument
 * scalaire s'y affiche en clair. Le second paramètre ($memberId) reste
 * scalaire et apparaîtrait donc dans une trace — accepté : ça révèle
 * qu'une exception a touché ce membre pendant qu'il votait, pas ce qu'il
 * a choisi, même catégorie d'information que ActivityLog::record() sur le
 * chemin de succès (participation, jamais contenu).
 */
final class Vote extends Model
{
    private const RECEIPT_BYTES = 6; // 48 bits — identifiant, pas un secret (voir migration 014)
    private const RECEIPT_MAX_ATTEMPTS = 3;

    /**
     * @param array<int, int|null> $selections position_id => candidate_id
     *        (null = vote blanc), une entrée par poste de l'élection.
     * @return array{ok: bool, reason: ?string, receipt: ?string} reason
     *         parmi : 'not_open', 'candidate_unavailable', 'already_used'.
     */
    public static function castBallot(int $electionId, int $memberId, array $selections): array
    {
        $db = static::db();
        $db->beginTransaction();

        try {
            foreach ($selections as $positionId => $candidateId) {
                $inserted = $candidateId === null
                    ? self::insertBlank($db, $electionId, (int) $positionId)
                    : self::insertForCandidate($db, $electionId, (int) $positionId, $candidateId);

                if (!$inserted) {
                    $db->rollBack();

                    // Un INSERT sur zéro ligne ici signifie soit l'élection
                    // n'est plus 'open', soit (pour un choix non-blanc) le
                    // candidat n'est plus 'approved' ou ne correspond plus
                    // à ce poste — dans les deux cas, aucune substitution,
                    // aucun repli sur un vote blanc : la soumission entière
                    // est rejetée (décision explicite conversation FIUCC-66).
                    return ['ok' => false, 'reason' => 'candidate_unavailable', 'receipt' => null];
                }
            }

            $receipt = self::consumeToken($db, $electionId, $memberId);

            if ($receipt === null) {
                $db->rollBack();

                return ['ok' => false, 'reason' => 'already_used', 'receipt' => null];
            }

            $db->commit();

            return ['ok' => true, 'reason' => null, 'receipt' => $receipt];
        } catch (PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private static function insertBlank(\PDO $db, int $electionId, int $positionId): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO votes (election_id, position_id, candidate_id, vote_hash, created_at)
             SELECT :election_id, :position_id, NULL, :vote_hash, CURDATE()
             FROM elections e
             WHERE e.id = :election_id_check AND e.status = 'open'"
        );
        $stmt->execute([
            'election_id'       => $electionId,
            'election_id_check' => $electionId,
            'position_id'       => $positionId,
            'vote_hash'         => bin2hex(random_bytes(32)),
        ]);

        return $stmt->rowCount() === 1;
    }

    private static function insertForCandidate(\PDO $db, int $electionId, int $positionId, int $candidateId): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO votes (election_id, position_id, candidate_id, vote_hash, created_at)
             SELECT :election_id, :position_id, :candidate_id, :vote_hash, CURDATE()
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE c.id = :candidate_id_check AND c.status = 'approved'
               AND c.election_position_id = :position_id_check
               AND e.id = :election_id_check AND e.status = 'open'"
        );
        $stmt->execute([
            'election_id'        => $electionId,
            'election_id_check'  => $electionId,
            'position_id'        => $positionId,
            'position_id_check'  => $positionId,
            'candidate_id'       => $candidateId,
            'candidate_id_check' => $candidateId,
            'vote_hash'          => bin2hex(random_bytes(32)),
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Marque le jeton utilisé et y écrit le reçu — même garde SQL
     * (used_at IS NULL) que App\Models\VotingToken::regenerate(), pour la
     * même raison : une course avec un double envoi ne doit jamais pouvoir
     * consommer le jeton deux fois. Réessaie uniquement sur collision de
     * receipt_code (UNIQUE, 48 bits — astronomiquement improbable) ;
     * n'importe quelle autre cause d'échec de l'UPDATE (used_at déjà posé)
     * arrête net, sans réessayer.
     */
    private static function consumeToken(\PDO $db, int $electionId, int $memberId): ?string
    {
        for ($attempt = 0; $attempt < self::RECEIPT_MAX_ATTEMPTS; $attempt++) {
            $receipt = Base32::encode(random_bytes(self::RECEIPT_BYTES));

            try {
                $stmt = $db->prepare(
                    "UPDATE voting_tokens SET used_at = NOW(), receipt_code = :receipt
                     WHERE election_id = :election_id AND member_id = :member_id AND used_at IS NULL"
                );
                $stmt->execute([
                    'receipt'     => $receipt,
                    'election_id' => $electionId,
                    'member_id'   => $memberId,
                ]);

                return $stmt->rowCount() === 1 ? $receipt : null;
            } catch (PDOException $exception) {
                if (!str_contains($exception->getMessage(), 'uq_voting_tokens_receipt')) {
                    throw $exception;
                }
                // Collision sur receipt_code : nouveau tirage, prochaine itération.
            }
        }

        return null;
    }
}
