<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modèle minimal — juste ce dont a besoin la section "Our Journey" de la
 * page Community (FIUCC-40). Le CRUD complet et /events (FIUCC-48, phase 3)
 * ne sont pas construits ici, volontairement.
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
}
