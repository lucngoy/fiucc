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
     *         parmi : 'election_closed', 'member_ineligible',
     *         'candidate_unavailable', 'already_used'.
     */
    public static function castBallot(int $electionId, int $memberId, array $selections): array
    {
        $db = static::db();
        $db->beginTransaction();

        try {
            // Deux contrôles explicites en tête de transaction (FIUCC-68 pour
            // l'élection, FIUCC-79 pour le membre — trouvé en écrivant les
            // tests de cette épopée : rien ne revérifiait le droit de vote au
            // moment même de l'écriture, seulement à l'émission du jeton,
            // potentiellement des heures ou des jours plus tôt). Deux
            // requêtes séparées plutôt qu'une jointure unique : c'est
            // précisément pour distinguer "élection fermée" de "membre
            // devenu inéligible" que l'appelant doit pouvoir donner un
            // message différent (docs/SECURITE_ELECTIONS.md, §1.10 —
            // "le droit de vote se vérifie à l'instant de l'écriture, jamais
            // à l'émission du jeton"). Sous REPEATABLE READ (InnoDB), ces
            // lectures établissent l'instantané que verront TOUTES les
            // lectures suivantes de cette transaction — les gardes répétées
            // dans insertBlank()/insertForCandidate()/consumeToken()
            // ci-dessous ne peuvent donc plus, en pratique, échouer pour ces
            // raisons une fois qu'on a dépassé ce point ; elles restent quand
            // même, en défense de profondeur, plutôt que de faire reposer
            // toute la garantie sur ce détail d'isolation.
            $stmt = $db->prepare(
                "SELECT 1 FROM elections WHERE id = :id AND status = 'open' AND end_at > NOW() LIMIT 1"
            );
            $stmt->execute(['id' => $electionId]);

            if ($stmt->fetchColumn() === false) {
                $db->rollBack();

                return ['ok' => false, 'reason' => 'election_closed', 'receipt' => null];
            }

            $stmt = $db->prepare(
                "SELECT 1 FROM members WHERE id = :id AND status = 'validated' AND status_type = 'student' LIMIT 1"
            );
            $stmt->execute(['id' => $memberId]);

            if ($stmt->fetchColumn() === false) {
                $db->rollBack();

                // Cas administratif (suspension, passage alumni... entre
                // l'émission du jeton et le vote), jamais confondu avec
                // election_closed ou candidate_unavailable : le membre doit
                // savoir qu'il faut contacter le bureau, pas revoir son
                // bulletin ni réessayer plus tard.
                return ['ok' => false, 'reason' => 'member_ineligible', 'receipt' => null];
            }

            foreach ($selections as $positionId => $candidateId) {
                $inserted = $candidateId === null
                    ? self::insertBlank($db, $electionId, $memberId, (int) $positionId)
                    : self::insertForCandidate($db, $electionId, $memberId, (int) $positionId, $candidateId);

                if (!$inserted) {
                    $db->rollBack();

                    // Un INSERT sur zéro ligne ici signifie l'élection n'est
                    // plus 'open', le membre n'est plus éligible, ou (pour un
                    // choix non-blanc) le candidat n'est plus 'approved' ou
                    // ne correspond plus à ce poste — dans tous les cas,
                    // aucune substitution, aucun repli sur un vote blanc : la
                    // soumission entière est rejetée (décision explicite
                    // conversation FIUCC-66). Le contrôle ci-dessus rend ce
                    // cas non atteignable en pratique pour l'élection et le
                    // membre (même raisonnement que pour candidate_unavailable
                    // plus haut) ; candidate_unavailable reste le message
                    // générique de ce filet, comme pour le candidat retiré.
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

    /**
     * Le JOIN sur members ne sert qu'à vérifier une condition — memberId
     * n'entre dans AUCUNE colonne insérée dans `votes` (toujours
     * election_id, position_id, candidate_id, vote_hash, created_at, rien
     * d'autre) : le lien entre un électeur et son bulletin ne doit exister
     * nulle part, y compris dans la garde qui protège son écriture
     * (CLAUDE.md règle 1).
     */
    private static function insertBlank(\PDO $db, int $electionId, int $memberId, int $positionId): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO votes (election_id, position_id, candidate_id, vote_hash, created_at)
             SELECT :election_id, :position_id, NULL, :vote_hash, CURDATE()
             FROM elections e
             INNER JOIN members m ON m.id = :member_id
             WHERE e.id = :election_id_check AND e.status = 'open' AND e.end_at > NOW()
               AND m.status = 'validated' AND m.status_type = 'student'"
        );
        $stmt->execute([
            'election_id'       => $electionId,
            'election_id_check' => $electionId,
            'member_id'         => $memberId,
            'position_id'       => $positionId,
            'vote_hash'         => bin2hex(random_bytes(32)),
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Même principe qu'insertBlank() : memberId ne sert qu'à la garde, ne
     * touche à aucune colonne insérée.
     */
    private static function insertForCandidate(\PDO $db, int $electionId, int $memberId, int $positionId, int $candidateId): bool
    {
        $stmt = $db->prepare(
            "INSERT INTO votes (election_id, position_id, candidate_id, vote_hash, created_at)
             SELECT :election_id, :position_id, :candidate_id, :vote_hash, CURDATE()
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = :member_id
             WHERE c.id = :candidate_id_check AND c.status = 'approved'
               AND c.election_position_id = :position_id_check
               AND e.id = :election_id_check AND e.status = 'open' AND e.end_at > NOW()
               AND m.status = 'validated' AND m.status_type = 'student'"
        );
        $stmt->execute([
            'election_id'        => $electionId,
            'election_id_check'  => $electionId,
            'member_id'          => $memberId,
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
     *
     * Rejointe à elections ET members avec les mêmes gardes status/end_at et
     * éligibilité que insertBlank()/insertForCandidate() (FIUCC-68,
     * FIUCC-79) — jamais atteignable en pratique puisque les contrôles en
     * tête de castBallot() auraient déjà arrêté la transaction avant
     * d'arriver ici, mais chaque écriture porte sa propre garde dans cette
     * épopée, jamais seulement l'ordre d'appel.
     */
    private static function consumeToken(\PDO $db, int $electionId, int $memberId): ?string
    {
        for ($attempt = 0; $attempt < self::RECEIPT_MAX_ATTEMPTS; $attempt++) {
            $receipt = Base32::encode(random_bytes(self::RECEIPT_BYTES));

            try {
                $stmt = $db->prepare(
                    "UPDATE voting_tokens vt
                     INNER JOIN elections e ON e.id = vt.election_id AND e.status = 'open' AND e.end_at > NOW()
                     INNER JOIN members m ON m.id = vt.member_id AND m.status = 'validated' AND m.status_type = 'student'
                     SET vt.used_at = NOW(), vt.receipt_code = :receipt
                     WHERE vt.election_id = :election_id AND vt.member_id = :member_id AND vt.used_at IS NULL"
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

    /**
     * Dépouillement (FIUCC-68, §22 du cahier des charges) — un total par
     * (poste, candidat), candidate_id NULL agrégé à part (vote blanc).
     *
     * Jamais filtré sur candidates.status : un candidat retiré après coup
     * garde les voix déjà exprimées pour lui (décision FIUCC-61,
     * App\Models\Candidate — "les voix déjà exprimées pour lui ne sont
     * jamais touchées"), donc son nom doit continuer à apparaître ici même
     * s'il ne serait plus proposable sur un bulletin neuf.
     *
     * Agrégat pur — aucune colonne de voting_tokens ni de members touchant
     * à l'identité d'un électeur n'entre dans cette requête, seulement le
     * décompte par candidat (CLAUDE.md règle 13 : un résultat public global
     * n'est pas la même chose qu'exposer le bulletin d'un membre précis).
     */
    public static function resultsForElection(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT v.position_id, v.candidate_id, m.full_name AS candidate_name, COUNT(*) AS vote_count
             FROM votes v
             LEFT JOIN candidates c ON c.id = v.candidate_id
             LEFT JOIN members m ON m.id = c.member_id
             WHERE v.election_id = :election_id
             GROUP BY v.position_id, v.candidate_id, m.full_name
             ORDER BY v.position_id ASC, vote_count DESC"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Total des bulletins enregistrés, toutes élections confondues (FIUCC-23,
     * dashboard admin) — encore plus strictement un agrégat pur que
     * resultsForElection() ci-dessus : aucune jointure du tout, pas même vers
     * candidates/members, seulement COUNT(*) FROM votes. Ne révèle ni pour
     * qui, ni pour quel poste, ni quelle élection (CLAUDE.md règle 1/13).
     */
    public static function countAll(): int
    {
        return (int) static::db()->query('SELECT COUNT(*) FROM votes')->fetchColumn();
    }

    /**
     * Assemble resultsForElection() et Election::positions() en une seule
     * structure prête à afficher — factorisé ici (FIUCC-72) parce que
     * App\Controllers\ElectionController::results(), Admin\ElectionController
     * ::results() et ::report() en avaient chacun besoin : dupliqué trois
     * fois, ce n'était plus une garde de sécurité à répéter par écriture
     * (comme ailleurs dans cette épopée), juste de l'assemblage de lecture.
     *
     * @return array<int, array{position: array, candidates: array, total: int}>
     */
    public static function groupResultsByPosition(int $electionId): array
    {
        $groups = [];

        foreach (Election::positions($electionId) as $position) {
            $groups[(int) $position['id']] = ['position' => $position, 'candidates' => [], 'total' => 0];
        }

        foreach (self::resultsForElection($electionId) as $row) {
            $positionId = (int) $row['position_id'];

            if (!isset($groups[$positionId])) {
                continue;
            }

            $groups[$positionId]['candidates'][] = $row;
            $groups[$positionId]['total'] += (int) $row['vote_count'];
        }

        return $groups;
    }
}
