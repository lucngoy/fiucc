<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Lecture publique (chronologie "Our Journey" de /community FIUCC-40, page
 * d'accueil FIUCC-46/47, liste FIUCC-48 et détail FIUCC-49 des événements),
 * inscription (FIUCC-50) et CRUD admin (FIUCC-51).
 */
final class Event extends Model
{
    private const PUBLIC_PER_PAGE = 9;

    /**
     * Bascule à venir/passé (section 26/27 du cahier des charges) : jamais
     * un statut stocké séparément, toujours recalculée à partir des dates —
     * un événement bascule seul à l'heure dite, sans qu'un admin ait à
     * changer quoi que ce soit. COALESCE(end_date, start_date) plutôt que
     * seulement start_date : un événement encore en cours (start_date passée,
     * end_date pas encore atteinte) ne doit pas basculer "passé" avant sa fin
     * réelle. 'archived' est toujours passé (décision éditoriale explicite de
     * l'admin, indépendante de la date) ; 'draft' n'apparaît jamais
     * publiquement, dans aucun des deux cas.
     */
    private static function whenClause(string $when): string
    {
        return $when === 'past'
            ? "(status = 'archived' OR (status IN ('published', 'cancelled') AND COALESCE(end_date, start_date) < NOW()))"
            : "(status IN ('published', 'cancelled') AND COALESCE(end_date, start_date) >= NOW())";
    }

    /**
     * Liste publique (FIUCC-48) : recherche, filtre catégorie, pagination.
     * $when vaut 'upcoming' ou 'past' — voir whenClause().
     *
     * @param array{search?: string, category?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function publicList(string $when, array $filters, int $page, int $perPage = self::PUBLIC_PER_PAGE): array
    {
        $conditions = [self::whenClause($when)];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(title LIKE :search1 OR description LIKE :search2 OR location LIKE :search3)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $orderDir = $when === 'past' ? 'DESC' : 'ASC';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM events {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés, pas liés — même raison que
        // Member::adminSearch() : PDO::ATTR_EMULATE_PREPARES à false
        // n'accepte pas de valeur liée à cet endroit, sûr ici parce que
        // $page/$perPage sont typés int (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, slug, description, category, location, cover_image, start_date, end_date, status, capacity
             FROM events
             {$where}
             ORDER BY start_date {$orderDir}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Compte des onglets Upcoming/Past — toujours sans filtre (recherche/
     * catégorie), pour que le nombre affiché sur l'onglet reste stable
     * pendant qu'on filtre à l'intérieur.
     */
    public static function countByWhen(string $when): int
    {
        return (int) static::db()
            ->query('SELECT COUNT(*) FROM events WHERE ' . self::whenClause($when))
            ->fetchColumn();
    }

    /**
     * Catégories réellement représentées parmi les événements visibles
     * publiquement (jamais un brouillon) — même principe que
     * Member::distinctFaculties()/activityCategoriesCount() ci-dessous.
     */
    public static function publicCategories(): array
    {
        $stmt = static::db()->query(
            "SELECT DISTINCT category FROM events
             WHERE status IN ('published', 'archived', 'cancelled') AND category IS NOT NULL AND category != ''
             ORDER BY category ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Détail d'un événement (FIUCC-49) — jamais un brouillon, même avec le
     * bon slug : un brouillon n'est pas prêt à être vu, peu importe qui a
     * le lien.
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT id, title, slug, description, category, location, cover_image, start_date, end_date, capacity, status
             FROM events
             WHERE slug = :slug AND status IN ('published', 'archived', 'cancelled')
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * "N inscrits" sur la page détail — 'going' et 'attended' comptent,
     * 'cancelled' non. Vaut 0 aujourd'hui pour tous les événements :
     * l'écriture dans event_registrations est FIUCC-50, pas construite ici.
     */
    public static function registeredCount(int $eventId): int
    {
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM event_registrations WHERE event_id = :id AND status IN ('going', 'attended')"
        );
        $stmt->execute(['id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Photos d'un événement précis (FIUCC-49, "Photos from past editions")
     * — pas Event::recentPhotos() ci-dessous, qui mélange tous les
     * événements pour la page d'accueil.
     */
    public static function images(int $eventId): array
    {
        $stmt = static::db()->prepare(
            'SELECT image_path, caption FROM event_images WHERE event_id = :id ORDER BY sort_order ASC, created_at ASC'
        );
        $stmt->execute(['id' => $eventId]);

        return $stmt->fetchAll();
    }

    /**
     * État affiché (upcoming/ongoing/completed/cancelled) — jamais stocké,
     * toujours recalculé depuis start_date/end_date/status au moment de
     * l'affichage (même logique que whenClause(), en PHP pour un event
     * déjà chargé plutôt qu'en SQL pour une liste).
     */
    public static function displayState(array $event): string
    {
        if ($event['status'] === 'cancelled') {
            return 'cancelled';
        }

        $now = time();
        $start = strtotime((string) $event['start_date']);
        $end = strtotime((string) ($event['end_date'] ?? $event['start_date']));

        if ($event['status'] === 'archived' || $now > $end) {
            return 'completed';
        }

        return $now >= $start ? 'ongoing' : 'upcoming';
    }

    /**
     * Chronologie de la vie de la communauté : événements publiés ou déjà
     * passés (archived), jamais les brouillons ni les annulés.
     */
    public static function timelineEntries(int $limit = 12): array
    {
        $stmt = static::db()->prepare(
            "SELECT title, description, location, start_date
             FROM events
             WHERE status IN ('published', 'archived')
             ORDER BY start_date ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Prochains événements (section 7 du cahier des charges, page d'accueil)
     * — publiés et à venir seulement, jamais un brouillon ou un événement
     * déjà passé/annulé.
     */
    public static function upcoming(int $limit = 3): array
    {
        $stmt = static::db()->prepare(
            "SELECT title, slug, description, category, location, cover_image, start_date
             FROM events
             WHERE status = 'published' AND start_date >= NOW()
             ORDER BY start_date ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Statistique "Events" de la page d'accueil (FIUCC-47) : tout événement
     * réellement organisé (publié ou archivé), passé ou à venir — pas les
     * brouillons/annulés, qui ne se sont jamais tenus.
     */
    public static function organizedCount(): int
    {
        return (int) static::db()
            ->query("SELECT COUNT(*) FROM events WHERE status IN ('published', 'archived')")
            ->fetchColumn();
    }

    /**
     * Statistique "Activities" (FIUCC-47) : nombre de catégories d'événements
     * réellement représentées (culture, sport, académique...) — une mesure
     * de la diversité des activités, distincte du nombre brut d'événements
     * ("Events" juste au-dessus). Aucune table "activités" dédiée n'existe ;
     * plutôt que d'en inventer une, on compte ce que les événements réels
     * couvrent déjà.
     */
    public static function activityCategoriesCount(): int
    {
        return (int) static::db()
            ->query("SELECT COUNT(DISTINCT category) FROM events WHERE status IN ('published', 'archived') AND category IS NOT NULL AND category != ''")
            ->fetchColumn();
    }

    /**
     * "Memories" (section 9 du cahier des charges, page d'accueil) : photos
     * réellement déposées sur des événements publiés/archivés — event_images
     * est la seule table de photos du schéma, il n'existe pas de table
     * "gallery" séparée. Vide tant qu'aucune photo n'est réellement
     * uploadée (aucun seeder n'en crée) : affiché honnêtement comme tel côté
     * vue, jamais masqué derrière des images inventées.
     */
    public static function recentPhotos(int $limit = 6): array
    {
        $stmt = static::db()->prepare(
            "SELECT ei.image_path, ei.caption, e.title AS event_title
             FROM event_images ei
             JOIN events e ON e.id = ei.event_id
             WHERE e.status IN ('published', 'archived')
             ORDER BY ei.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Statut d'inscription d'un membre précis — null si aucune ligne
     * n'existe encore pour ce couple (event_id, member_id).
     */
    public static function registrationStatus(int $eventId, int $memberId): ?string
    {
        $stmt = static::db()->prepare(
            'SELECT status FROM event_registrations WHERE event_id = :event_id AND member_id = :member_id LIMIT 1'
        );
        $stmt->execute(['event_id' => $eventId, 'member_id' => $memberId]);
        $status = $stmt->fetchColumn();

        return $status === false ? null : $status;
    }

    /**
     * Inscription (FIUCC-50) — transactionnelle. SELECT ... FOR UPDATE
     * verrouille la ligne events le temps de la transaction : deux
     * inscriptions concurrentes sur le même événement se sérialisent, la
     * seconde ne relit le compte qu'après que la première a validé ou
     * annulé la sienne — jamais deux personnes ne peuvent prendre le
     * dernier siège en même temps. Une ligne déjà 'cancelled' (désinscrit
     * puis re-inscrit) est réactivée par UPDATE plutôt que dupliquée : la
     * contrainte unique (event_id, member_id) interdirait de toute façon un
     * second INSERT.
     *
     * @return string 'registered'|'already_registered'|'full'|'not_found'
     */
    public static function register(int $eventId, int $memberId): string
    {
        $pdo = static::db();
        $pdo->beginTransaction();

        try {
            $lockStmt = $pdo->prepare('SELECT capacity FROM events WHERE id = :id FOR UPDATE');
            $lockStmt->execute(['id' => $eventId]);
            $event = $lockStmt->fetch();

            if ($event === false) {
                $pdo->rollBack();

                return 'not_found';
            }

            $existingStmt = $pdo->prepare(
                'SELECT status FROM event_registrations WHERE event_id = :event_id AND member_id = :member_id LIMIT 1'
            );
            $existingStmt->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            $existingStatus = $existingStmt->fetchColumn();

            if ($existingStatus !== false && in_array($existingStatus, ['going', 'attended'], true)) {
                $pdo->rollBack();

                return 'already_registered';
            }

            if ($event['capacity'] !== null) {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM event_registrations WHERE event_id = :event_id AND status IN ('going', 'attended')"
                );
                $countStmt->execute(['event_id' => $eventId]);

                if ((int) $countStmt->fetchColumn() >= (int) $event['capacity']) {
                    $pdo->rollBack();

                    return 'full';
                }
            }

            if ($existingStatus !== false) {
                $reactivateStmt = $pdo->prepare(
                    "UPDATE event_registrations SET status = 'going' WHERE event_id = :event_id AND member_id = :member_id"
                );
                $reactivateStmt->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO event_registrations (event_id, member_id, status) VALUES (:event_id, :member_id, 'going')"
                );
                $insertStmt->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            }

            $pdo->commit();

            return 'registered';
        } catch (\Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }

    /**
     * Désinscription (FIUCC-50) — passe à 'cancelled', ne supprime jamais
     * la ligne : garde l'historique (et permet une réinscription ultérieure
     * via register(), qui réactive plutôt que dupliquer).
     */
    public static function unregister(int $eventId, int $memberId): void
    {
        $stmt = static::db()->prepare(
            "UPDATE event_registrations SET status = 'cancelled'
             WHERE event_id = :event_id AND member_id = :member_id AND status IN ('going', 'attended')"
        );
        $stmt->execute(['event_id' => $eventId, 'member_id' => $memberId]);
    }

    private const ADMIN_PER_PAGE = 20;

    /**
     * Liste admin (FIUCC-51) : tous les statuts, y compris 'draft' — jamais
     * filtré publiquement comme publicList()/whenClause(). Recherche +
     * filtre statut, pas de bascule à venir/passé (l'admin gère le cycle de
     * vie éditorial, pas la chronologie affichée au public).
     *
     * @param array{search?: string, status?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(array $filters, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(title LIKE :search1 OR location LIKE :search2)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['draft', 'published', 'archived', 'cancelled'], true)) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM events {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison que publicList() ci-dessus.
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, slug, category, location, cover_image, start_date, end_date, capacity, status
             FROM events
             {$where}
             ORDER BY start_date DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Un événement, tous champs, quel que soit son statut — pour le
     * formulaire d'édition admin. Distinct de findBySlug() (public, jamais
     * un brouillon) : ici l'admin doit voir/modifier ses brouillons.
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Création (FIUCC-51). Le slug se déduit du titre et n'est jamais saisi
     * par l'admin (le cahier ne le liste pas parmi les champs du formulaire) ;
     * uniqueSlug() garantit qu'il ne collisionne jamais avec un événement
     * existant.
     *
     * @param array{title: string, description: ?string, category: ?string, location: ?string,
     *              start_date: string, end_date: ?string, cover_image: ?string, capacity: ?int,
     *              status: string, created_by: ?int} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO events (title, slug, description, category, location, start_date, end_date, cover_image, capacity, status, created_by)
             VALUES (:title, :slug, :description, :category, :location, :start_date, :end_date, :cover_image, :capacity, :status, :created_by)'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'slug'        => self::uniqueSlug($data['title']),
            'description' => $data['description'],
            'category'    => $data['category'],
            'location'    => $data['location'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'cover_image' => $data['cover_image'],
            'capacity'    => $data['capacity'],
            'status'      => $data['status'],
            'created_by'  => $data['created_by'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Mise à jour (FIUCC-51) — ne touche jamais au slug, même si le titre
     * change : un lien d'inscription déjà partagé (email de confirmation,
     * réseaux sociaux) ne doit pas casser après une simple correction de
     * titre. La couverture se change via updateCoverImage() seulement,
     * jamais ici (même principe que Member::updateProfile()/updatePhoto()).
     *
     * @param array{title: string, description: ?string, category: ?string, location: ?string,
     *              start_date: string, end_date: ?string, capacity: ?int, status: string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE events SET title = :title, description = :description, category = :category,
             location = :location, start_date = :start_date, end_date = :end_date,
             capacity = :capacity, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'category'    => $data['category'],
            'location'    => $data['location'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'capacity'    => $data['capacity'],
            'status'      => $data['status'],
            'id'          => $id,
        ]);
    }

    public static function updateCoverImage(int $id, string $path): void
    {
        $stmt = static::db()->prepare('UPDATE events SET cover_image = :cover_image WHERE id = :id');
        $stmt->execute(['cover_image' => $path, 'id' => $id]);
    }

    /**
     * Suppression définitive (FIUCC-51) — sûre au niveau base : event_images
     * et event_registrations portent ON DELETE CASCADE (001_initial_schema.sql).
     * Le fichier de couverture, lui, n'est pas géré par une contrainte SQL —
     * à supprimer séparément par l'appelant (voir Admin\EventController::delete(),
     * même best-effort qu'un ancien fichier remplacé).
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Slug unique dérivé du titre — base ASCII (translittération des
     * accents), suffixé -2/-3/... en cas de collision plutôt que de refuser
     * la création : deux événements peuvent légitimement partager un titre
     * ("Soirée culturelle" d'une année sur l'autre).
     */
    private static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = self::slugify($title);
        $slug = $base;
        $suffix = 2;

        while (self::slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slugify(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        $ascii = $ascii !== false ? $ascii : $title;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii));
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'evenement';
    }

    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        $sql = 'SELECT COUNT(*) FROM events WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
