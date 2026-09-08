<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Candidatures (FIUCC-61, sections 16-25 du cahier des charges). Un
 * candidat doit être un membre éligible au vote (Member::isVoterEligible()
 * — validated + student, un alumni ne se présente pas), vérifié à la
 * création ET à l'approbation, jamais seulement côté formulaire.
 *
 * Verrouillage : création/modification/approbation/rejet ne sont permis
 * que tant que l'élection est 'draft' ou 'candidacies' — chaque écriture
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
            "SELECT c.id, c.ballot_number, c.status, c.photo,
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
               AND m.status = 'validated' AND m.status_type = 'student'"
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
             WHERE c.id = :id AND e.status IN ('draft', 'candidacies')"
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
             SET c.status = 'approved'
             WHERE c.id = :id AND e.status IN ('draft', 'candidacies')
               AND m.status = 'validated' AND m.status_type = 'student'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function reject(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE candidates c
             INNER JOIN election_positions ep ON ep.id = c.election_position_id
             INNER JOIN elections e ON e.id = ep.election_id
             SET c.status = 'rejected'
             WHERE c.id = :id AND e.status IN ('draft', 'candidacies')"
        );
        $stmt->execute(['id' => $id]);

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
