<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modèle minimal — CRUD complet et /events (FIUCC-48, phase 3) ne sont pas
 * construits ici, volontairement. Sert la chronologie "Our Journey" de la
 * page Community (FIUCC-40) et la page d'accueil (FIUCC-46/47).
 */
final class Event extends Model
{
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
}
