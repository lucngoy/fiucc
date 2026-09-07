<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Galerie photo par albums (FIUCC-52, section 28 du cahier des charges) —
 * lecture publique ici. CRUD admin (upload multiple, réorganisation,
 * suppression) : FIUCC-54, pas construit dans ce modèle pour l'instant.
 * Un album peut être rattaché à un événement (albums.event_id, migration
 * 007) — les photos d'un événement précis sont son album, pas une table
 * séparée : voir Event::images()/recentPhotos().
 */
final class Album extends Model
{
    private const PUBLIC_PER_PAGE = 9;

    /**
     * Albums publiés avec au moins une photo — un album 'published' sans
     * photo n'a rien à montrer publiquement (peut arriver pendant qu'un
     * admin prépare un album avant d'y uploader quoi que ce soit).
     * photo_count et cover_image (première photo par sort_order) dérivés
     * par jointure, jamais stockés en dur sur albums.
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
                    (SELECT p.image_path FROM album_photos p WHERE p.album_id = a.id ORDER BY p.sort_order ASC, p.created_at ASC LIMIT 1) AS cover_image
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
     * même principe qu'Event::images().
     */
    public static function photos(int $albumId): array
    {
        $stmt = static::db()->prepare(
            'SELECT image_path, caption FROM album_photos WHERE album_id = :id ORDER BY sort_order ASC, created_at ASC'
        );
        $stmt->execute(['id' => $albumId]);

        return $stmt->fetchAll();
    }
}
