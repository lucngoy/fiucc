<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Élections (FIUCC-59/60, sections 16-25 du cahier des charges) — création
 * et configuration côté admin. Candidatures (FIUCC-61), vote (FIUCC-64+) et
 * résultats (FIUCC-69+) : pas construits ici.
 *
 * Verrouillage de la configuration (décision explicite, conversation
 * FIUCC-59) : le titre, les dates de début et les postes ne sont
 * modifiables que tant que status = 'draft' — chaque écriture qui y touche
 * porte sa propre garde SQL (WHERE ... status = 'draft') EN PLUS du
 * contrôle applicatif dans Admin\ElectionController, pour qu'un bug côté
 * contrôleur ne puisse pas à lui seul corrompre une élection déjà entrée
 * en phase de candidatures ou ouverte au vote. Seule exception : end_at
 * reste modifiable pendant que status = 'open' (extendEndAt() ci-dessous),
 * pour permettre au bureau de prolonger ou raccourcir un scrutin en cours
 * sans jamais toucher le reste de la configuration ni les postes.
 */
final class Election extends Model
{
    private const ADMIN_PER_PAGE = 20;
    public const STATUSES = ['draft', 'candidacies', 'open', 'closed', 'published'];

    /**
     * @param array{search?: string, status?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(array $filters, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = 'title LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, self::STATUSES, true)) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM elections {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'ailleurs dans le projet
        // (Event::adminList()...) : PDO::ATTR_EMULATE_PREPARES à false
        // n'accepte pas de valeur liée à cet endroit, sûr ici parce que
        // $page/$perPage sont typés int (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at, e.status,
                    (SELECT COUNT(*) FROM election_positions ep WHERE ep.election_id = e.id) AS position_count
             FROM elections e
             {$where}
             ORDER BY e.start_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM elections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Liste publique (FIUCC-62/63 déclarent la page, mais aucune ne liste
     * les élections — /elections pointait dans le vide, découvert après
     * coup une fois le reste du module construit). Jamais 'draft' — même
     * règle que App\Controllers\ElectionController::findElectionOr404() :
     * une élection en configuration n'est pas encore publique. Pas de
     * pagination : le nombre d'élections réelles pour une seule communauté
     * reste faible, une page suffit.
     */
    public static function publicList(): array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM elections WHERE status != 'draft' ORDER BY start_at DESC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte à rebours public (FIUCC-62) — dérivé de status/start_at/end_at,
     * jamais stocké. Borné pour ne jamais afficher un compte à rebours figé
     * à zéro : le bureau ouvre/ferme le scrutin manuellement (FIUCC-59 §25,
     * "ouvrir l'élection"/"fermer l'élection"), donc start_at ou end_at peut
     * être dépassé sans que le statut ait encore changé — dans ce cas
     * l'affichage doit dire "imminent", pas montrer 00:00:00:00.
     *
     * @param array{status: string, start_at: string, end_at: string} $election
     * @return array{state: string, target: ?string} target est un datetime
     *         MySQL (fuseau app.timezone) à compter, ou null si l'état n'a
     *         pas de compte à rebours à afficher.
     */
    public static function votingWindowState(array $election): array
    {
        $now = time();
        $start = strtotime((string) $election['start_at']);
        $end = strtotime((string) $election['end_at']);
        $status = $election['status'];

        if (in_array($status, ['draft', 'candidacies'], true)) {
            return $now >= $start
                ? ['state' => 'opening_imminent', 'target' => null]
                : ['state' => 'before_open', 'target' => (string) $election['start_at']];
        }

        if ($status === 'open') {
            return $now >= $end
                ? ['state' => 'closing_imminent', 'target' => null]
                : ['state' => 'open_countdown', 'target' => (string) $election['end_at']];
        }

        return ['state' => $status === 'closed' ? 'closed' : 'published', 'target' => null];
    }

    /**
     * @param array{title: string, description: ?string, start_at: string, end_at: string} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO elections (title, description, start_at, end_at, status)
             VALUES (:title, :description, :start_at, :end_at, 'draft')"
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'start_at'    => $data['start_at'],
            'end_at'      => $data['end_at'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Garde SQL : n'affecte aucune ligne si l'élection n'est plus 'draft' au
     * moment de l'écriture (course possible entre l'affichage du formulaire
     * et la soumission — le contrôleur vérifie déjà le statut avant
     * d'arriver ici, ceci est la seconde ligne de défense, pas la première).
     *
     * @param array{title: string, description: ?string, start_at: string, end_at: string} $data
     * @return bool true si l'élection était bien 'draft' et a été modifiée
     */
    public static function update(int $id, array $data): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET title = :title, description = :description, start_at = :start_at, end_at = :end_at
             WHERE id = :id AND status = 'draft'"
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'start_at'    => $data['start_at'],
            'end_at'      => $data['end_at'],
            'id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Seule exception au verrouillage : prolonger ou raccourcir la clôture
     * d'une élection déjà ouverte (décision explicite, conversation
     * FIUCC-59) — jamais le titre, jamais start_at, jamais les postes.
     * Garde SQL sur status = 'open' ; la validation de la nouvelle date
     * (postérieure à maintenant) est du ressort de l'appelant
     * (Admin\ElectionController::extendDeadline()), pas de ce modèle.
     *
     * @return bool true si l'élection était bien 'open' et a été modifiée
     */
    public static function extendEndAt(int $id, string $newEndAt): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET end_at = :end_at WHERE id = :id AND status = 'open'"
        );
        $stmt->execute(['end_at' => $newEndAt, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'draft' -> 'candidacies' : verrouille définitivement titre/dates/
     * postes (Admin\ElectionController::update()/storePosition()/etc. ne
     * s'appliquent qu'à 'draft'). Exige au moins un poste — faire
     * progresser une élection sans aucun poste n'a pas de sens, et la
     * configuration devient justement figée à cet instant.
     */
    public static function startCandidacies(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections e
             SET e.status = 'candidacies'
             WHERE e.id = :id AND e.status = 'draft'
               AND EXISTS (SELECT 1 FROM election_positions WHERE election_id = e.id)"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'candidacies' -> 'open' : ouvre le vote. Ne vérifie pas qu'un
     * candidat approuvé existe par poste — un poste sans candidat approuvé
     * reste votable en blanc (App\Models\Vote::castBallot()), pas une
     * erreur de configuration à bloquer ici.
     */
    public static function open(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'open' WHERE id = :id AND status = 'candidacies'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'open' -> 'closed'. Pas de garde temporelle (end_at atteint ou non) :
     * le bureau garde la main pour fermer plus tôt si besoin, symétrique à
     * extendEndAt() qui permet déjà de prolonger. §25 du cahier : "Une
     * élection fermée ne doit plus accepter de votes" — déjà garanti
     * ailleurs, chaque garde SQL de vote vérifie elections.status = 'open'
     * en direct, jamais déduit d'une horloge.
     */
    public static function close(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'closed' WHERE id = :id AND status = 'open'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Pas de suppression au sens large (un scrutin est une archive
     * communautaire, décision explicite conversation FIUCC-59) — seulement
     * un brouillon qui n'a encore aucun poste, pour ne pas laisser un
     * brouillon créé par erreur polluer la liste indéfiniment. Garde SQL
     * double : status = 'draft' ET aucune ligne dans election_positions.
     *
     * @return bool true si l'élection remplissait les conditions et a été supprimée
     */
    public static function delete(int $id): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM elections
             WHERE id = :id AND status = 'draft'
               AND NOT EXISTS (SELECT 1 FROM election_positions WHERE election_id = elections.id)"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function positions(int $electionId): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, name, description, max_votes, sort_order
             FROM election_positions WHERE election_id = :election_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Garde SQL via INSERT ... SELECT ... WHERE plutôt qu'un simple INSERT :
     * n'insère rien si l'élection n'est plus 'draft', au lieu de faire
     * confiance au seul contrôle applicatif du contrôleur.
     *
     * @param array{name: string, description: ?string, max_votes: int, sort_order: int} $data
     * @return int|null null si l'élection n'était pas (ou plus) 'draft'
     */
    public static function addPosition(int $electionId, array $data): ?int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO election_positions (election_id, name, description, max_votes, sort_order)
             SELECT :election_id, :name, :description, :max_votes, :sort_order
             FROM elections WHERE id = :election_id_check AND status = 'draft'"
        );
        $stmt->execute([
            'election_id'       => $electionId,
            'election_id_check' => $electionId,
            'name'              => $data['name'],
            'description'       => $data['description'],
            'max_votes'         => $data['max_votes'],
            'sort_order'        => $data['sort_order'],
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) static::db()->lastInsertId();
    }

    /**
     * UPDATE avec jointure sur elections pour garder la garde de statut
     * dans la même requête atomique, plutôt qu'un SELECT de statut suivi
     * d'un UPDATE séparé (fenêtre de course entre les deux).
     *
     * @param array{name: string, description: ?string, max_votes: int, sort_order: int} $data
     * @return bool true si le poste appartenait bien à cette élection, 'draft', et a été modifié
     */
    public static function updatePosition(int $positionId, int $electionId, array $data): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             SET ep.name = :name, ep.description = :description, ep.max_votes = :max_votes, ep.sort_order = :sort_order
             WHERE ep.id = :position_id AND ep.election_id = :election_id AND e.status = 'draft'"
        );
        $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'],
            'max_votes'   => $data['max_votes'],
            'sort_order'  => $data['sort_order'],
            'position_id' => $positionId,
            'election_id' => $electionId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return bool true si le poste appartenait bien à cette élection, 'draft', et a été supprimé
     */
    public static function deletePosition(int $positionId, int $electionId): bool
    {
        $stmt = static::db()->prepare(
            "DELETE ep FROM election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE ep.id = :position_id AND ep.election_id = :election_id AND e.status = 'draft'"
        );
        $stmt->execute(['position_id' => $positionId, 'election_id' => $electionId]);

        return $stmt->rowCount() > 0;
    }
}
