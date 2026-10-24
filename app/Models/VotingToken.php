<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Model;
use App\Services\Base32;
use PDOException;

/**
 * Jetons de vote (FIUCC-64, sections 19-20 du cahier des charges).
 *
 * `used_at` est la seule chose qu'on a le droit de lire pour répondre à un
 * membre sur SON PROPRE vote : le fait d'avoir voté ou non, jamais pour qui
 * ni pour quoi (CLAUDE.md, règle 13) — cette table n'a d'ailleurs aucune
 * colonne qui permettrait de répondre à autre chose.
 *
 * Taille et forme du jeton : voir CLAUDE.md, règle 3 (128 bits, raisonnement
 * complet). `token_hash` est toujours SHA-256 des octets bruts — jamais de
 * la chaîne Base32 affichée au membre, qui n'est qu'un encodage d'affichage
 * (App\Services\Base32).
 *
 * Verrouillage : émission et régénération ne sont possibles que tant que
 * l'élection est 'open' et le membre validated+student — chaque écriture
 * porte sa propre garde SQL, même discipline que le reste de l'épopée
 * élections (défense en profondeur, jamais un seul contrôle applicatif).
 *
 * Anti-double-vote à la régénération (conversation FIUCC-64) : un seul jeton
 * peut exister par (élection, membre) — UNIQUE(election_id, member_id),
 * migration 001. regenerate() ne crée donc jamais une deuxième ligne : c'est
 * un UPDATE en place du hash existant, gardé par `used_at IS NULL` DANS la
 * requête elle-même. Deux conséquences directes :
 * - l'ancien jeton en clair cesse de correspondre à quoi que ce soit dès que
 *   l'UPDATE s'exécute, QUE L'ENVOI DU NOUVEAU EMAIL RÉUSSISSE OU NON — les
 *   deux opérations (écrire le nouveau hash, envoyer l'email) sont
 *   distinctes, et casser l'ancien lien ne dépend jamais du succès de la
 *   seconde ;
 * - si le membre a déjà voté, l'UPDATE affecte zéro ligne, sans exception ni
 *   contrôle séparé à oublier — même si l'écran de l'admin affichait "pas
 *   encore voté" une seconde plus tôt (course), la garde se réévalue au
 *   moment de l'écriture, pas au moment de l'affichage.
 */
final class VotingToken extends Model
{
    public const ADMIN_PER_PAGE = 25;

    private const TOKEN_BYTES = 16;

    /**
     * Ligne unique (élection, membre) — statut affiché à l'admin ou
     * expiration à réutiliser dans l'email, jamais le hash ni de quoi
     * reconstruire le code en clair.
     */
    public static function find(int $electionId, int $memberId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT sent_at, used_at, expires_at, attempts, receipt_code
             FROM voting_tokens WHERE election_id = :election_id AND member_id = :member_id
             LIMIT 1'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function hasVoted(int $electionId, int $memberId): bool
    {
        $stmt = static::db()->prepare(
            'SELECT 1 FROM voting_tokens
             WHERE election_id = :election_id AND member_id = :member_id AND used_at IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Émission (première fois). Renvoie le code Base32 groupé (prêt à
     * afficher/emailer) si l'émission a eu lieu, null sinon — élection ni
     * 'open' ni en attente du vote avec sa liste publiée (envoi à l'avance,
     * Election::tokensSendable()), membre pas validated+student, ou jeton déjà existant pour ce
     * couple (UNIQUE(election_id, member_id), capturé en PDOException plutôt
     * qu'en pré-contrôle séparé : la contrainte est l'autorité, pas une
     * lecture préalable qui pourrait être périmée au moment de l'écriture).
     */
    public static function issue(int $electionId, int $memberId): ?string
    {
        $raw = random_bytes(self::TOKEN_BYTES);
        $hash = hash('sha256', $raw);
        $code = Base32::encode($raw);
        $expiresAt = self::expiryFor(self::ttlHours());

        $stmt = static::db()->prepare(
            "INSERT INTO voting_tokens (election_id, member_id, token_hash, expires_at)
             SELECT :election_id, :member_id, :token_hash, LEAST(GREATEST(:expires_at, e.start_at + INTERVAL :ttl_hours HOUR), e.end_at)
             FROM elections e
             INNER JOIN members m ON m.id = :member_id_check
             WHERE e.id = :election_id_check
               AND (e.status = 'open' OR (e.status = 'candidacies_closed' AND (e.candidate_list_at IS NULL OR e.candidate_list_at <= NOW())))
               AND m.status = 'validated' AND m.status_type = 'student'"
        );

        try {
            $stmt->execute([
                'election_id'       => $electionId,
                'election_id_check' => $electionId,
                'member_id'         => $memberId,
                'member_id_check'   => $memberId,
                'token_hash'        => $hash,
                'expires_at'        => $expiresAt,
                'ttl_hours'         => self::ttlHours(),
            ]);
        } catch (PDOException) {
            // Course : un jeton existe déjà pour ce couple (UNIQUE), ou
            // collision de token_hash (astronomiquement improbable sur 128
            // bits) — dans les deux cas, ne rien émettre plutôt que de
            // réessayer en silence.
            return null;
        }

        return $stmt->rowCount() > 0 ? Base32::group($code) : null;
    }

    /**
     * Régénération (jeton perdu, ou verrouillé après trop de tentatives
     * incorrectes) — admin-only côté contrôleur, jamais en libre-service
     * membre. Voir le commentaire de classe : invalide l'ancien jeton par
     * construction, avant même que l'email du nouveau parte.
     */
    public static function regenerate(int $electionId, int $memberId): ?string
    {
        $raw = random_bytes(self::TOKEN_BYTES);
        $hash = hash('sha256', $raw);
        $code = Base32::encode($raw);
        $expiresAt = self::expiryFor(self::ttlHours());

        $stmt = static::db()->prepare(
            "UPDATE voting_tokens vt
             INNER JOIN elections e ON e.id = vt.election_id
             INNER JOIN members m ON m.id = vt.member_id
             SET vt.token_hash = :token_hash,
                 vt.expires_at = LEAST(GREATEST(:expires_at, e.start_at + INTERVAL :ttl_hours HOUR), e.end_at),
                 vt.sent_at = NULL,
                 vt.attempts = 0
             WHERE vt.election_id = :election_id AND vt.member_id = :member_id
               AND vt.used_at IS NULL
               AND (e.status = 'open' OR (e.status = 'candidacies_closed' AND (e.candidate_list_at IS NULL OR e.candidate_list_at <= NOW())))
               AND m.status = 'validated' AND m.status_type = 'student'"
        );

        $stmt->execute([
            'token_hash'  => $hash,
            'expires_at'  => $expiresAt,
            'ttl_hours'   => self::ttlHours(),
            'election_id' => $electionId,
            'member_id'   => $memberId,
        ]);

        return $stmt->rowCount() > 0 ? Base32::group($code) : null;
    }

    public static function markSent(int $electionId, int $memberId, bool $success): void
    {
        $stmt = static::db()->prepare(
            'UPDATE voting_tokens SET sent_at = :sent_at
             WHERE election_id = :election_id AND member_id = :member_id'
        );
        $stmt->execute([
            'sent_at'     => $success ? date('Y-m-d H:i:s') : null,
            'election_id' => $electionId,
            'member_id'   => $memberId,
        ]);
    }

    /**
     * Vérifie un code saisi par le membre. La normalisation (espaces,
     * tirets, casse) et le décodage Base32 ont lieu ICI, avant tout
     * hachage — jamais après (décision explicite conversation FIUCC-64).
     * Incrémente attempts à chaque échec de comparaison ; ne l'incrémente
     * jamais pour un jeton absent/déjà utilisé/expiré/verrouillé, qui ne
     * sont pas des "tentatives" mais des états à part.
     *
     * @return array{ok: bool, reason: ?string} reason parmi : 'no_token',
     *         'already_used', 'locked', 'expired', 'invalid'.
     */
    public static function verify(int $electionId, int $memberId, string $enteredCode): array
    {
        $stmt = static::db()->prepare(
            'SELECT token_hash, used_at, expires_at, attempts
             FROM voting_tokens WHERE election_id = :election_id AND member_id = :member_id
             LIMIT 1'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return ['ok' => false, 'reason' => 'no_token'];
        }

        if ($row['used_at'] !== null) {
            return ['ok' => false, 'reason' => 'already_used'];
        }

        $maxAttempts = (int) Config::get('security.vote_token_max_attempts', 5);

        if ((int) $row['attempts'] >= $maxAttempts) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $raw = Base32::decode(Base32::normalize($enteredCode), self::TOKEN_BYTES);
        $matches = $raw !== null && hash_equals($row['token_hash'], hash('sha256', $raw));

        if (!$matches) {
            self::registerFailedAttempt($electionId, $memberId);

            return ['ok' => false, 'reason' => 'invalid'];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Vue admin — membres actuellement éligibles avec leur état de jeton
     * pour cette élection (aucune ligne si jamais émis : LEFT JOIN).
     * Paginée : une ligne par étudiant validé, la liste grandit avec la
     * communauté.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminListForElection(int $electionId, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $total = (int) static::db()
            ->query("SELECT COUNT(*) FROM members WHERE status = 'validated' AND status_type = 'student'")
            ->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'Event::publicList() :
        // sûrs ici parce que $page/$perPage sont typés int (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT m.id AS member_id, m.full_name,
                    vt.sent_at, vt.used_at, vt.expires_at, vt.attempts,
                    (vt.id IS NOT NULL) AS has_token
             FROM members m
             LEFT JOIN voting_tokens vt ON vt.member_id = m.id AND vt.election_id = :election_id
             WHERE m.status = 'validated' AND m.status_type = 'student'
             ORDER BY m.full_name ASC, m.id ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute(['election_id' => $electionId]);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Membres à traiter par l'envoi en masse (FIUCC-64) : éligibles, sans
     * jeton livré pour l'instant. `has_token` distingue les deux cas que
     * l'appelant doit traiter différemment :
     * - false : jamais émis -> issue() (INSERT) ;
     * - true (sent_at NULL, used_at NULL) : déjà émis mais jamais envoyé
     *   avec succès (échec SMTP lors d'un essai précédent) -> regenerate()
     *   (UPDATE), PAS issue() (qui échouerait sur UNIQUE(election_id,
     *   member_id)) — le code en clair d'un essai précédent n'est jamais
     *   récupérable (seul le hash est stocké, règle 3), donc "renvoyer" un
     *   jeton jamais livré veut nécessairement dire lui en substituer un
     *   nouveau, jamais rejouer l'ancien.
     * Jamais un membre déjà voté (used_at renseigné) ni déjà servi avec
     * succès (sent_at renseigné) : rejouable à volonté, ne retraite que
     * l'écart réel.
     */
    public static function pendingForBatchSend(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT m.id AS member_id, m.full_name, u.id AS user_id, u.email,
                    (vt.id IS NOT NULL) AS has_token
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN voting_tokens vt ON vt.member_id = m.id AND vt.election_id = :election_id
             WHERE m.status = 'validated' AND m.status_type = 'student'
               AND (vt.id IS NULL OR (vt.sent_at IS NULL AND vt.used_at IS NULL))
             ORDER BY m.full_name ASC"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Destinataires d'un rappel manuel (FIUCC-73) : tout membre ayant un
     * jeton non utilisé pour cette élection, qu'il l'ait reçu ou non —
     * volontairement plus large que pendingForBatchSend() (règle validée
     * conversation FIUCC-73 : "membres avec un token non utilisé
     * uniquement"). Ne relance jamais quelqu'un qui a déjà voté : c'est la
     * seule garde qui compte ici, la fenêtre de 12h entre deux envois vit
     * dans App\Models\Election::claimReminderSlot(), pas dans cette
     * requête.
     */
    public static function pendingReminderRecipients(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT m.full_name, u.email
             FROM voting_tokens vt
             INNER JOIN members m ON m.id = vt.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE vt.election_id = :election_id AND vt.used_at IS NULL"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Destinataires de l'annonce de clôture (FIUCC-73, §31 du cahier) :
     * même population que pendingReminderRecipients() (n'a pas voté), mais
     * suivie par membre via closure_notified_at (migration 015) plutôt que
     * par élection — à la différence du rappel, cette annonce n'est envoyée
     * qu'une fois par membre, jamais rejouée pour qui l'a déjà reçue.
     */
    public static function pendingClosureNotice(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT m.id AS member_id, m.full_name, u.email
             FROM voting_tokens vt
             INNER JOIN members m ON m.id = vt.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE vt.election_id = :election_id AND vt.used_at IS NULL
               AND vt.closure_notified_at IS NULL"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * N'est appelé qu'après un envoi réussi (même convention que
     * markSent()) : un échec SMTP laisse closure_notified_at à NULL, pour
     * que pendingClosureNotice() reprenne exactement les membres non
     * couverts au prochain clic, sans en oublier ni en redoubler aucun.
     */
    public static function markClosureNotified(int $electionId, int $memberId): void
    {
        $stmt = static::db()->prepare(
            'UPDATE voting_tokens SET closure_notified_at = NOW()
             WHERE election_id = :election_id AND member_id = :member_id'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);
    }

    private static function registerFailedAttempt(int $electionId, int $memberId): void
    {
        $stmt = static::db()->prepare(
            'UPDATE voting_tokens SET attempts = attempts + 1
             WHERE election_id = :election_id AND member_id = :member_id'
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);
    }

    /**
     * Durée de validité d'un code, comptée depuis l'envoi — ou depuis
     * l'ouverture du vote pour un code envoyé à l'avance (GREATEST(...,
     * start_at + ttl) dans issue()/regenerate()), sinon un code envoyé
     * trois jours avant expirerait avant même d'avoir servi. Toujours
     * plafonnée à end_at.
     */
    private static function ttlHours(): int
    {
        return (int) Config::get('security.vote_token_ttl_hours', 72);
    }

    private static function expiryFor(int $ttlHours): string
    {
        return date('Y-m-d H:i:s', strtotime('+' . $ttlHours . ' hours'));
    }
}
