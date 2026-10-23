<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Candidatures (FIUCC-61, sections 16-25 du cahier des charges). Un
 * candidat doit être un membre éligible au vote (Member::isVoterEligible()
 * — validated + student, un alumni ne se présente pas), vérifié à la
 * création ET à l'approbation, jamais seulement côté formulaire — de même
 * que l'incompatibilité avec la Commission électorale (art. 6,
 * App\Models\ElectionCommission).
 *
 * Verrouillage : la création n'est permise que tant que l'élection est
 * 'draft' ou 'candidacies' ; modification/approbation/rejet le restent
 * aussi pendant 'candidacies_closed' (migration 023 : vérification des
 * dossiers et régularisation après la clôture du dépôt, art. 15) — chaque écriture
 * porte sa propre garde SQL (jointure jusqu'à elections.status), en plus
 * du contrôle applicatif dans Admin\CandidateController, même principe
 * qu'App\Models\Election pour les postes.
 *
 * Retrait (withdraw()) est la seule exception : possible à tout moment
 * tant que l'élection n'est pas 'published' — y compris pendant 'open',
 * un candidat peut légitimement devoir se retirer en cours de scrutin. Les
 * voix déjà exprimées pour lui ne sont jamais touchées : votes.created_at
 * est une DATE (pas un DATETIME) précisément pour empêcher tout
 * recoupement, impossible de distinguer "avant" et "après" le retrait
 * même si on le voulait — et annuler après coup le bulletin de quelqu'un
 * qui a voté de bonne foi serait pire que de compter une voix pour un
 * candidat depuis retiré.
 */
final class Candidate extends Model
{
    /**
     * Liste des candidats d'une élection pour l'admin — jointure jusqu'au
     * membre pour permettre l'avertissement d'inéligibilité directement
     * dans la liste (décision explicite conversation FIUCC-61 : personne
     * n'ouvre les fiches une par une la veille d'un scrutin).
     */
    public static function adminListForElection(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT c.id, c.ballot_number, c.status, c.photo, c.submitted_at,
                    ep.id AS position_id, ep.name AS position_name,
                    m.id AS member_id, m.full_name, m.status AS member_status, m.status_type AS member_status_type
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN members m ON m.id = c.member_id
             WHERE ep.election_id = :election_id
             ORDER BY ep.sort_order ASC, c.ballot_number ASC, m.full_name ASC"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Candidatures en attente d'approbation, regroupées par élection —
     * cloche admin (App\Services\AdminTasks). Seulement les élections
     * encore 'draft'/'candidacies' : passé ce stade, approve() est
     * verrouillé, une candidature pending n'est plus une action possible.
     *
     * @return list<array{election_id: int, title: string, pending: int}>
     */
    public static function pendingCountsByElection(): array
    {
        $rows = static::db()->query(
            "SELECT e.id AS election_id, e.title, COUNT(*) AS pending
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE c.status = 'pending' AND e.status IN ('draft', 'candidacies', 'candidacies_closed')
             GROUP BY e.id, e.title
             ORDER BY e.start_at ASC"
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'election_id' => (int) $row['election_id'],
            'title'       => (string) $row['title'],
            'pending'     => (int) $row['pending'],
        ], $rows);
    }

    /**
     * Liste publique des candidats approuvés d'une élection (FIUCC-63,
     * §17-18 du cahier des charges) — jamais 'pending'/'rejected'/
     * 'withdrawn' : seul un candidat "publié" (approve(), même verbe que le
     * cahier §25) apparaît sur cette page.
     *
     * Confidentialité (décision explicite conversation FIUCC-63) : un
     * candidat accepte implicitement d'être visible EN TANT QUE candidat,
     * pas de voir tout son profil membre exposé dans ce nouveau contexte.
     * - photo du membre : jamais sélectionnée ici, à dessein — seule
     *   candidates.photo (une photo choisie pour CETTE candidature) peut
     *   apparaître ; pas de repli sur la photo de profil.
     * - faculty/year : toujours renvoyés, ce sont les deux champs que le
     *   cahier §17 demande explicitement sur la fiche candidat.
     * - department/instagram : redigés en NULL directement dans la requête
     *   si members.is_public = 0, pour qu'aucun appelant ne puisse les
     *   afficher par erreur même s'il oublie de vérifier le drapeau —
     *   la confidentialité est appliquée une fois, ici, pas à chaque vue.
     * - email/téléphone/numéro étudiant : jamais sélectionnés, cette page
     *   n'en a aucun usage légitime (cahier §18 : photo, nom, poste, bio,
     *   manifeste).
     */
    public static function publicListForElection(int $electionId): array
    {
        $stmt = static::db()->prepare(
            "SELECT c.id, c.ballot_number, c.bio, c.manifesto, c.photo,
                    ep.id AS position_id, ep.name AS position_name, ep.sort_order AS position_sort_order,
                    m.full_name,
                    m.faculty,
                    m.year,
                    CASE WHEN m.is_public = 1 THEN m.department ELSE NULL END AS department,
                    CASE WHEN m.is_public = 1 THEN m.instagram ELSE NULL END AS instagram
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN members m ON m.id = c.member_id
             WHERE ep.election_id = :election_id AND c.status = 'approved'
             ORDER BY ep.sort_order ASC, c.ballot_number ASC, m.full_name ASC"
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Un candidat, tous champs + infos membre/poste/élection jointes —
     * formulaire d'édition admin et vérifications de verrouillage
     * (Admin\CandidateController a besoin du statut de l'élection sans
     * requête séparée).
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT c.*, ep.election_id, ep.name AS position_name,
                    m.full_name, m.status AS member_status, m.status_type AS member_status_type
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN members m ON m.id = c.member_id
             WHERE c.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Garde SQL via INSERT ... SELECT ... WHERE (même principe
     * qu'Election::addPosition()) : n'insère rien si l'élection n'est plus
     * 'draft'/'candidacies', ou si le membre n'est plus éligible — deux
     * conditions vérifiées dans la requête elle-même, pas seulement par le
     * contrôleur en amont.
     *
     * @param array{election_position_id: int, member_id: int, ballot_number: ?int,
     *              bio: ?string, manifesto: ?string, photo: ?string} $data
     * @return int|null null si l'élection n'était pas draft/candidacies, ou si le
     *                   membre n'était pas éligible (validated + student)
     */
    public static function create(array $data): ?int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO candidates (election_position_id, member_id, ballot_number, bio, manifesto, photo, status)
             SELECT :election_position_id, :member_id, :ballot_number, :bio, :manifesto, :photo, 'pending'
             FROM election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = :member_id_check
             WHERE ep.id = :election_position_id_check AND e.status IN ('draft', 'candidacies')
               AND m.status = 'validated' AND m.status_type = 'student'
               AND NOT EXISTS (
                   SELECT 1 FROM election_commission_members cm
                   WHERE cm.election_id = e.id AND cm.member_id = m.id
               )"
        );
        $stmt->execute([
            'election_position_id'       => $data['election_position_id'],
            'election_position_id_check' => $data['election_position_id'],
            'member_id'                  => $data['member_id'],
            'member_id_check'            => $data['member_id'],
            'ballot_number'              => $data['ballot_number'],
            'bio'                        => $data['bio'],
            'manifesto'                  => $data['manifesto'],
            'photo'                      => $data['photo'],
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) static::db()->lastInsertId();
    }

    /**
     * Conditions communes au dépôt par le membre lui-même (lot 3,
     * règlement électoral art. 9-14) : dépôt ouvert ET date limite pas
     * encore passée (jamais déduit du seul statut), membre validé et
     * étudiant, hors Commission électorale (art. 6). Fragment SQL attendant
     * les alias e (elections) et m (members).
     */
    private const MEMBER_ELIGIBLE_GUARD = "m.status = 'validated' AND m.status_type = 'student'
               AND NOT EXISTS (
                   SELECT 1 FROM election_commission_members cm
                   WHERE cm.election_id = e.id AND cm.member_id = m.id
               )";

    private const MEMBER_DEPOSIT_GUARD = "e.status = 'candidacies' AND e.candidacy_deadline > NOW()
               AND " . self::MEMBER_ELIGIBLE_GUARD;

    /**
     * Fenêtre de correction (lot 4, art. 15 — régularisation) : le bureau a
     * demandé une correction, son délai court encore, et le vote n'est pas
     * ouvert. Possible même après la clôture du dépôt. Attend les alias c,
     * e et m.
     */
    private const MEMBER_CORRECTION_GUARD = "c.status = 'correction' AND c.correction_deadline > NOW()
               AND e.status IN ('candidacies', 'candidacies_closed')
               AND " . self::MEMBER_ELIGIBLE_GUARD;

    /** Dossier modifiable par son auteur : en attente pendant le dépôt, ou à corriger dans le délai. */
    private const MEMBER_EDITABLE_GUARD = "((c.status = 'pending' AND " . self::MEMBER_DEPOSIT_GUARD . ")
               OR (" . self::MEMBER_CORRECTION_GUARD . "))";

    /**
     * Dépôt d'une candidature par le membre lui-même (lot 3). Une seule
     * candidature active par membre et par élection (décision du bureau
     * 2026-10-10 : un seul poste) — une candidature retirée par son auteur
     * ne compte plus, une candidature rejetée si (rejet définitif).
     * Toutes les conditions sont dans l'INSERT lui-même.
     *
     * @return int|null id du candidat, null si une condition n'est pas remplie
     */
    public static function submitByMember(int $positionId, int $memberId, string $bio, string $manifesto): ?int
    {
        // Redépôt après un retrait volontaire : la contrainte
        // uq_candidate_position_member (un membre une fois par poste)
        // interdit une seconde ligne — on réactive donc la candidature
        // retirée (retour en 'pending', à réexaminer), mêmes gardes.
        $reactivated = self::reactivateWithdrawn($positionId, $memberId, $bio, $manifesto);

        if ($reactivated !== null) {
            return $reactivated;
        }

        $stmt = static::db()->prepare(
            "INSERT INTO candidates (election_position_id, member_id, ballot_number, bio, manifesto, photo, status, submitted_at)
             SELECT ep.id, m.id, NULL, :bio, :manifesto, NULL, 'pending', NOW()
             FROM election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = :member_id
             WHERE ep.id = :position_id AND " . self::MEMBER_DEPOSIT_GUARD . "
               AND NOT EXISTS (
                   SELECT 1 FROM candidates c2
                   INNER JOIN election_positions ep2 ON ep2.id = c2.election_position_id
                   WHERE ep2.election_id = e.id AND c2.member_id = m.id AND c2.status != 'withdrawn'
               )"
        );
        $stmt->execute([
            'bio'         => $bio,
            'manifesto'   => $manifesto,
            'member_id'   => $memberId,
            'position_id' => $positionId,
        ]);

        return $stmt->rowCount() > 0 ? (int) static::db()->lastInsertId() : null;
    }

    private static function reactivateWithdrawn(int $positionId, int $memberId, string $bio, string $manifesto): ?int
    {
        $find = static::db()->prepare(
            "SELECT c.id FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN election_positions target ON target.id = :position_id AND target.election_id = ep.election_id
             WHERE c.member_id = :member_id AND c.status = 'withdrawn'
             ORDER BY c.id DESC LIMIT 1"
        );
        $find->execute(['position_id' => $positionId, 'member_id' => $memberId]);
        $candidateId = $find->fetchColumn();

        if ($candidateId === false) {
            return null;
        }

        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = c.member_id
             INNER JOIN election_positions target ON target.id = :position_id AND target.election_id = e.id
             SET c.status = 'pending', c.election_position_id = target.id, c.bio = :bio, c.manifesto = :manifesto,
                 c.ballot_number = NULL, c.submitted_at = NOW()
             WHERE c.id = :id AND c.status = 'withdrawn' AND " . self::MEMBER_DEPOSIT_GUARD . "
               AND NOT EXISTS (
                   SELECT 1 FROM (SELECT c2.id, c2.member_id, c2.status, ep2.election_id
                                  FROM candidates c2 INNER JOIN election_positions ep2 ON ep2.id = c2.election_position_id) other
                   WHERE other.election_id = e.id AND other.member_id = m.id AND other.status != 'withdrawn'
               )"
        );
        $stmt->execute(['position_id' => $positionId, 'bio' => $bio, 'manifesto' => $manifesto, 'id' => (int) $candidateId]);

        return $stmt->rowCount() > 0 ? (int) $candidateId : null;
    }

    /**
     * Modification par son auteur : seulement sa propre candidature, encore
     * 'pending' tant que le dépôt est ouvert, ou 'correction' dans le délai
     * fixé par la Commission (lot 4). Le poste peut changer, mais seulement
     * pour un poste de la même élection. Une correction renvoyée repasse
     * 'pending' et date corrected_at.
     */
    public static function updateByMember(int $candidateId, int $memberId, int $positionId, string $bio, string $manifesto): bool
    {
        // Le poste visé doit appartenir à la même élection : l'UPDATE
        // ci-dessous écrit election_position_id tel quel, c'est donc ici
        // qu'un poste d'une autre élection est refusé.
        $samePosition = static::db()->prepare(
            'SELECT 1 FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN election_positions target ON target.id = :position_id AND target.election_id = ep.election_id
             WHERE c.id = :id LIMIT 1'
        );
        $samePosition->execute(['position_id' => $positionId, 'id' => $candidateId]);

        if ($samePosition->fetchColumn() === false) {
            return false;
        }

        // Garde lue sous verrou, puis écriture simple : dans un UPDATE
        // multi-tables, MySQL ne garantit pas l'ordre des affectations du
        // SET, donc corrected_at ne peut pas y dépendre de c.status. Et la
        // valeur de retour ne peut pas se déduire d'une relecture de la
        // garde : une correction renvoyée après la clôture du dépôt redevient
        // 'pending' — donc non modifiable — alors qu'elle a bien été écrite.
        $db = static::db();
        $db->beginTransaction();

        try {
            $guard = $db->prepare(
                "SELECT c.status FROM candidates c
                 INNER JOIN election_positions ep ON ep.id = c.election_position_id
                 INNER JOIN elections e ON e.id = ep.election_id
                 INNER JOIN members m ON m.id = c.member_id
                 WHERE c.id = :id AND c.member_id = :member_id
                   AND " . self::MEMBER_EDITABLE_GUARD . "
                 FOR UPDATE"
            );
            $guard->execute(['id' => $candidateId, 'member_id' => $memberId]);
            $status = $guard->fetchColumn();

            if ($status === false) {
                $db->rollBack();

                return false;
            }

            $db->prepare(
                "UPDATE candidates
                 SET election_position_id = :position_id, bio = :bio, manifesto = :manifesto,
                     corrected_at = IF(:was_correction = 1, NOW(), corrected_at),
                     status = 'pending'
                 WHERE id = :id"
            )->execute([
                'position_id'    => $positionId,
                'bio'            => $bio,
                'manifesto'      => $manifesto,
                'was_correction' => $status === 'correction' ? 1 : 0,
                'id'             => $candidateId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * La candidature appartient-elle à ce membre ET est-elle encore
     * modifiable par lui (en attente pendant le dépôt, ou correction
     * demandée et délai en cours) ?
     */
    public static function ownsEditable(int $candidateId, int $memberId): bool
    {
        $stmt = static::db()->prepare(
            "SELECT 1 FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = c.member_id
             WHERE c.id = :id AND c.member_id = :member_id
               AND " . self::MEMBER_EDITABLE_GUARD . " LIMIT 1"
        );
        $stmt->execute(['id' => $candidateId, 'member_id' => $memberId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retrait par son auteur, tant que le dépôt est ouvert et le dossier en
     * attente : il pourra redéposer avant la date limite. (Le retrait
     * d'un candidat approuvé reste une action admin, withdraw().)
     */
    public static function cancelByMember(int $candidateId, int $memberId): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = c.member_id
             SET c.status = 'withdrawn'
             WHERE c.id = :id AND c.member_id = :member_id
               AND " . self::MEMBER_EDITABLE_GUARD
        );
        $stmt->execute(['id' => $candidateId, 'member_id' => $memberId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Candidature active (non retirée) d'un membre à une élection — au plus
     * une (submitByMember()).
     */
    public static function activeForMember(int $electionId, int $memberId): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT c.*, ep.name AS position_name
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             WHERE ep.election_id = :election_id AND c.member_id = :member_id AND c.status != 'withdrawn'
             ORDER BY c.id DESC LIMIT 1"
        );
        $stmt->execute(['election_id' => $electionId, 'member_id' => $memberId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Toutes les candidatures d'un membre (page profil, "Mes candidatures").
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allForMember(int $memberId): array
    {
        $stmt = static::db()->prepare(
            "SELECT c.id, c.status, c.submitted_at, c.created_at, ep.name AS position_name, e.id AS election_id, e.title AS election_title
             FROM candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE c.member_id = :member_id
             ORDER BY c.id DESC"
        );
        $stmt->execute(['member_id' => $memberId]);

        return $stmt->fetchAll();
    }

    public static function setPublicPhoto(int $candidateId, ?string $photoPath): ?string
    {
        $stmt = static::db()->prepare('SELECT photo FROM candidates WHERE id = :id');
        $stmt->execute(['id' => $candidateId]);
        $old = $stmt->fetchColumn();

        static::db()->prepare('UPDATE candidates SET photo = :photo WHERE id = :id')
            ->execute(['photo' => $photoPath, 'id' => $candidateId]);

        return $old !== false && $old !== null ? (string) $old : null;
    }

    /**
     * Champs de profil seuls (jamais le statut ni le membre) — guardé aux
     * mêmes conditions que create(), mais sans revérifier l'éligibilité du
     * membre : modifier la biographie d'un candidat déjà en base ne
     * concerne pas son éligibilité, qui est du ressort d'approve().
     *
     * @param array{election_position_id: int, ballot_number: ?int, bio: ?string, manifesto: ?string} $data
     */
    public static function updateProfile(int $id, array $data): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             SET c.election_position_id = :election_position_id, c.ballot_number = :ballot_number,
                 c.bio = :bio, c.manifesto = :manifesto
             WHERE c.id = :id AND e.status IN ('draft', 'candidacies', 'candidacies_closed')"
        );
        $stmt->execute([
            'election_position_id' => $data['election_position_id'],
            'ballot_number'        => $data['ballot_number'],
            'bio'                  => $data['bio'],
            'manifesto'            => $data['manifesto'],
            'id'                   => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function updatePhoto(int $id, string $photoPath): void
    {
        $stmt = static::db()->prepare('UPDATE candidates SET photo = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photoPath, 'id' => $id]);
    }

    /**
     * "Publier" un candidat (cahier des charges §25 : "publier candidats")
     * — revérifie l'éligibilité du membre dans la requête elle-même : si
     * le membre est devenu inéligible entre l'ajout (pending) et
     * l'approbation, l'approbation échoue plutôt que de publier un
     * candidat qui ne devrait plus pouvoir se présenter.
     */
    public static function approve(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             INNER JOIN members m ON m.id = c.member_id
             SET c.status = 'approved', c.decision_reason = NULL, c.correction_deadline = NULL, c.decided_at = NOW()
             WHERE c.id = :id AND c.status IN ('pending', 'correction')
               AND e.status IN ('draft', 'candidacies', 'candidacies_closed')
               AND m.status = 'validated' AND m.status_type = 'student'
               AND NOT EXISTS (
                   SELECT 1 FROM election_commission_members cm
                   WHERE cm.election_id = e.id AND cm.member_id = m.id
               )"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Rejet définitif (décision du bureau 2026-10-10), avec son motif —
     * seulement depuis 'pending' ou 'correction' : un candidat approuvé
     * se retire (withdraw()), il n'est pas rejeté après coup, et un rejet
     * ne se transforme plus en approbation (approve() exige aussi l'un de
     * ces deux statuts).
     */
    public static function reject(int $id, ?string $reason = null): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             SET c.status = 'rejected', c.decision_reason = :reason, c.correction_deadline = NULL, c.decided_at = NOW()
             WHERE c.id = :id AND c.status IN ('pending', 'correction')
               AND e.status IN ('draft', 'candidacies', 'candidacies_closed')"
        );
        $stmt->execute(['id' => $id, 'reason' => $reason]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Demande de correction (art. 15, régularisation) : motif et délai,
     * futur et antérieur au début du vote — le candidat doit pouvoir
     * corriger AVANT que la liste ne soit arrêtée. Seulement depuis
     * 'pending'.
     */
    public static function requestCorrection(int $id, string $reason, string $deadline): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             SET c.status = 'correction', c.decision_reason = :reason, c.correction_deadline = :deadline, c.decided_at = NOW()
             WHERE c.id = :id AND c.status = 'pending'
               AND e.status IN ('candidacies', 'candidacies_closed')
               AND :deadline_future > NOW() AND :deadline_before_vote < e.start_at"
        );
        $stmt->execute([
            'reason'               => $reason,
            'deadline'             => $deadline,
            'deadline_future'      => $deadline,
            'deadline_before_vote' => $deadline,
            'id'                   => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retrait (décision explicite conversation FIUCC-61) — seule écriture
     * permise hors 'draft'/'candidacies', jusqu'à 'closed' inclus mais pas
     * 'published' (une fois les résultats publiés, l'élection est
     * l'archive historique, plus rien ne bouge — même principe que le
     * verrouillage total d'Election une fois publiée). Ne touche jamais
     * aux lignes de votes : voir le commentaire de classe.
     */
    public static function withdraw(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             SET c.status = 'withdrawn'
             WHERE c.id = :id AND c.status = 'approved' AND e.status != 'published'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Contrôle applicatif préalable, avant l'écriture — même convention que
     * Member::studentIdExists() ailleurs dans le projet : on vérifie
     * l'unicité par une requête dédiée pour un message d'erreur clair sur
     * le formulaire, plutôt que de laisser remonter l'exception PDO de la
     * contrainte uq_candidate_position_ballot (qui reste le garde-fou
     * réel, au cas où — cette méthode n'est qu'un confort d'UX).
     */
    public static function ballotNumberTaken(int $positionId, int $ballotNumber, ?int $excludeCandidateId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM candidates
                WHERE election_position_id = :position_id AND ballot_number = :ballot_number';
        $params = ['position_id' => $positionId, 'ballot_number' => $ballotNumber];

        if ($excludeCandidateId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeCandidateId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Nombre de voix déjà exprimées pour ce candidat — total agrégé
     * uniquement, jamais une ligne individuelle ni un rapprochement avec
     * un électeur (impossible : votes n'a ni member_id ni référence au
     * token). Utilisé uniquement dans la confirmation de retrait
     * (Admin\CandidateController), jamais affiché ailleurs — les résultats
     * ne sont publics qu'après fermeture officielle (§22 du cahier).
     */
    public static function voteCount(int $id): int
    {
        $stmt = static::db()->prepare('SELECT COUNT(*) FROM votes WHERE candidate_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}
