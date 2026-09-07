<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Galerie photo par albums (FIUCC-52, section 28 du cahier des charges) —
 * lecture publique et CRUD admin avec upload par lot (FIUCC-54). Un album
 * peut être rattaché à un événement (albums.event_id, migration 007) — les
 * photos d'un événement précis sont son album, pas une table séparée : voir
 * Event::images()/recentPhotos().
 */
final class Album extends Model
{
    private const PUBLIC_PER_PAGE = 9;
    private const ADMIN_PER_PAGE = 20;

    /**
     * Albums publiés avec au moins une photo — un album 'published' sans
     * photo n'a rien à montrer publiquement (peut arriver pendant qu'un
     * admin prépare un album avant d'y uploader quoi que ce soit).
     * cover_image/cover_thumbnail (première photo par sort_order) dérivés
     * par jointure, jamais stockés en dur sur albums — cover_thumbnail
     * peut être NULL (migration 008, colonne nullable), la vue retombe sur
     * cover_image dans ce cas.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function publicList(int $page, int $perPage = self::PUBLIC_PER_PAGE): array
    {
        $countStmt = static::db()->query(
            "SELECT COUNT(*) FROM albums a
             WHERE a.status = 'published' AND EXISTS (SELECT 1 FROM album_photos p WHERE p.album_id = a.id)"
        );
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison que Event::publicList() :
        // PDO::ATTR_EMULATE_PREPARES à false n'accepte pas de valeur liée à
        // cet endroit, sûr ici parce que $page/$perPage sont typés int
        // (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT a.id, a.title, a.slug, a.description, a.created_at,
                    (SELECT COUNT(*) FROM album_photos p WHERE p.album_id = a.id) AS photo_count,
                    (SELECT p.image_path FROM album_photos p WHERE p.album_id = a.id ORDER BY p.sort_order ASC, p.created_at ASC LIMIT 1) AS cover_image,
                    (SELECT p.thumbnail_path FROM album_photos p WHERE p.album_id = a.id ORDER BY p.sort_order ASC, p.created_at ASC LIMIT 1) AS cover_thumbnail
             FROM albums a
             WHERE a.status = 'published' AND EXISTS (SELECT 1 FROM album_photos p WHERE p.album_id = a.id)
             ORDER BY a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Détail d'un album (FIUCC-52) — jamais un brouillon, même avec le bon
     * slug, même principe qu'Event::findBySlug(). event_title/event_slug
     * : renseignés seulement si l'événement lié est lui-même visible
     * publiquement (même condition que Event::findBySlug — jamais un
     * brouillon) ; sinon NULL, pour ne jamais afficher de lien mort "voir
     * l'événement" vers une fiche que le visiteur ne pourrait pas ouvrir.
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT a.id, a.title, a.slug, a.description, a.created_at,
                    e.title AS event_title, e.slug AS event_slug
             FROM albums a
             LEFT JOIN events e ON e.id = a.event_id AND e.status IN ('published', 'archived', 'cancelled')
             WHERE a.slug = :slug AND a.status = 'published'
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Photos d'un album, dans l'ordre choisi par l'admin (sort_order),
     * même principe qu'Event::images(). thumbnail_path peut être NULL —
     * la vue retombe sur image_path (voir gallery/index.php et show.php).
     */
    public static function photos(int $albumId): array
    {
        $stmt = static::db()->prepare(
            'SELECT image_path, thumbnail_path, caption FROM album_photos WHERE album_id = :id ORDER BY sort_order ASC, created_at ASC'
        );
        $stmt->execute(['id' => $albumId]);

        return $stmt->fetchAll();
    }

    /**
     * Liste admin (FIUCC-54) : tous les statuts, y compris 'draft'. Recherche
     * + pagination, même principe qu'Event::adminList().
     *
     * @param array{search?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(array $filters, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = 'a.title LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM albums a {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT a.id, a.title, a.slug, a.status, a.event_id, a.created_at, e.title AS event_title,
                    (SELECT COUNT(*) FROM album_photos p WHERE p.album_id = a.id) AS photo_count,
                    (SELECT COALESCE(p.thumbnail_path, p.image_path) FROM album_photos p WHERE p.album_id = a.id ORDER BY p.sort_order ASC, p.created_at ASC LIMIT 1) AS cover_thumbnail
             FROM albums a
             LEFT JOIN events e ON e.id = a.event_id
             {$where}
             ORDER BY a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Un album, tous champs, quel que soit son statut — formulaire d'édition
     * admin. Distinct de findBySlug() (public, jamais un brouillon).
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM albums WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Photos d'un album pour la page de gestion admin — id (pour
     * réordonner/supprimer une photo précise) et thumbnail_path bruts
     * (pas de fallback ici, c'est un outil de gestion, pas un affichage
     * public).
     */
    public static function photosAdmin(int $albumId): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, image_path, thumbnail_path, caption, sort_order
             FROM album_photos WHERE album_id = :id ORDER BY sort_order ASC, created_at ASC'
        );
        $stmt->execute(['id' => $albumId]);

        return $stmt->fetchAll();
    }

    /**
     * Événements déjà rattachés à un album — pour exclure ces options du
     * menu déroulant "événement lié" (UNIQUE sur albums.event_id, la
     * contrainte réelle reste côté base ; ceci évite juste de proposer un
     * choix voué à l'échec). $excludeAlbumId : ne pas exclure l'événement
     * déjà lié à l'album en cours d'édition, sinon son propre lien
     * disparaîtrait du menu.
     */
    public static function linkedEventIds(?int $excludeAlbumId = null): array
    {
        $sql = 'SELECT event_id FROM albums WHERE event_id IS NOT NULL';
        $params = [];

        if ($excludeAlbumId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeAlbumId;
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Création (FIUCC-54). Slug déduit du titre, jamais saisi ni modifié
     * après coup — même principe qu'Event::create()/uniqueSlug() : un lien
     * déjà partagé ne doit pas casser après une correction de titre.
     *
     * @param array{title: string, description: ?string, event_id: ?int, status: string, created_by: ?int} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO albums (title, slug, description, event_id, status, created_by)
             VALUES (:title, :slug, :description, :event_id, :status, :created_by)'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'slug'        => self::uniqueSlug($data['title']),
            'description' => $data['description'],
            'event_id'    => $data['event_id'],
            'status'      => $data['status'],
            'created_by'  => $data['created_by'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * @param array{title: string, description: ?string, event_id: ?int, status: string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE albums SET title = :title, description = :description, event_id = :event_id, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'event_id'    => $data['event_id'],
            'status'      => $data['status'],
            'id'          => $id,
        ]);
    }

    /**
     * Suppression définitive — sûre au niveau base : album_photos porte
     * ON DELETE CASCADE (migration 006). Les fichiers (image_path +
     * thumbnail_path de chaque photo) ne sont pas gérés par une contrainte
     * SQL — à l'appelant de les récupérer via photosAdmin() AVANT
     * d'appeler delete(), puis de les supprimer séparément (voir
     * Admin\GalleryController::delete()).
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM albums WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Ajoute une photo à la fin de l'album (sort_order = position suivante)
     * — l'upload par lot (FIUCC-54) ajoute plusieurs photos d'affilée sans
     * jamais réutiliser un sort_order déjà pris.
     */
    public static function addPhoto(int $albumId, string $imagePath, string $thumbnailPath, ?string $caption): int
    {
        // $albumId interpolé, pas lié — sûr ici parce qu'il est typé int
        // (strict_types=1), même raison que le LIMIT/OFFSET de
        // publicList()/adminList().
        $nextOrder = (int) static::db()
            ->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM album_photos WHERE album_id = ' . $albumId)
            ->fetchColumn();

        $stmt = static::db()->prepare(
            'INSERT INTO album_photos (album_id, image_path, thumbnail_path, caption, sort_order)
             VALUES (:album_id, :image_path, :thumbnail_path, :caption, :sort_order)'
        );
        $stmt->execute([
            'album_id'       => $albumId,
            'image_path'     => $imagePath,
            'thumbnail_path' => $thumbnailPath,
            'caption'        => $caption,
            'sort_order'     => $nextOrder,
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Met à jour légende + ordre de plusieurs photos en une fois (FIUCC-54,
     * formulaire unique "Gérer les photos") — scope explicite sur
     * $albumId : un id de photo qui n'appartient pas à cet album est
     * ignoré, jamais mis à jour à l'aveugle depuis une entrée de formulaire.
     *
     * @param array<int, array{caption: ?string, sort_order: int}> $updates Clé = id de la photo
     */
    public static function updatePhotos(int $albumId, array $updates): void
    {
        $stmt = static::db()->prepare(
            'UPDATE album_photos SET caption = :caption, sort_order = :sort_order
             WHERE id = :id AND album_id = :album_id'
        );

        foreach ($updates as $photoId => $data) {
            $stmt->execute([
                'caption'    => $data['caption'],
                'sort_order' => $data['sort_order'],
                'id'         => $photoId,
                'album_id'   => $albumId,
            ]);
        }
    }

    /**
     * Supprime une photo précise, scopée à son album (même garde que
     * updatePhotos()). Renvoie la ligne supprimée (image_path/
     * thumbnail_path) pour que l'appelant supprime les fichiers — null si
     * l'id ne correspond à aucune photo de cet album.
     */
    public static function deletePhoto(int $albumId, int $photoId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT image_path, thumbnail_path FROM album_photos WHERE id = :id AND album_id = :album_id'
        );
        $stmt->execute(['id' => $photoId, 'album_id' => $albumId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $deleteStmt = static::db()->prepare('DELETE FROM album_photos WHERE id = :id AND album_id = :album_id');
        $deleteStmt->execute(['id' => $photoId, 'album_id' => $albumId]);

        return $row;
    }

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

        return $slug !== '' ? $slug : 'album';
    }

    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        $sql = 'SELECT COUNT(*) FROM albums WHERE slug = :slug';
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
