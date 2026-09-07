<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Modèle minimal — juste ce dont a besoin la section "Latest Updates" de la
 * page d'accueil (FIUCC-46). Le CRUD complet et /news (phase 3, ticket
 * séparé) ne sont pas construits ici.
 */
final class News extends Model
{
    /**
     * Publications réellement publiées (published_at non nul et déjà passé)
     * — jamais un brouillon ni une publication programmée dans le futur.
     */
    public static function latest(int $limit = 4): array
    {
        $stmt = static::db()->prepare(
            "SELECT title, slug, excerpt, cover_image, category, published_at
             FROM news
             WHERE published_at IS NOT NULL AND published_at <= NOW()
             ORDER BY published_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
