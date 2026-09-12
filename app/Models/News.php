<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Actualités (FIUCC-55/56, section 29 du cahier des charges). Catégories
 * fusionnées (migration 009) entre celles du cahier §29 (announcement/
 * community/events/sports/culture/education) et celles déjà écrites dans
 * 001_initial_schema.sql (announcement/event/urgent/general) — voir le
 * commentaire de la migration. author_id capturé à la création comme
 * events.created_by, jamais affiché publiquement : users n'a pas de nom
 * d'affichage (seul l'email existe réellement, CLAUDE.md), afficher un
 * email sur une page publique serait une fuite, pas une signature.
 */
final class News extends Model
{
    public const CATEGORIES = [
        'announcement', 'community', 'events', 'sports', 'culture', 'education',
        'event', 'urgent', 'general',
    ];

    private const PUBLIC_PER_PAGE = 9;
    private const ADMIN_PER_PAGE = 20;

    /**
     * Publications réellement publiées (published_at non nul et déjà passé)
     * — jamais un brouillon ni une publication programmée dans le futur.
     * Aucun job/cron : la bascule se fait seule au prochain chargement, dès
     * que NOW() dépasse published_at — même principe que
     * Event::displayState() (calculé, jamais stocké séparément).
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

    /**
     * Même condition de visibilité que latest() (FIUCC-23, dashboard admin).
     */
    public static function countPublished(): int
    {
        return (int) static::db()
            ->query('SELECT COUNT(*) FROM news WHERE published_at IS NOT NULL AND published_at <= NOW()')
            ->fetchColumn();
    }

    /**
     * Liste publique (FIUCC-55) — recherche, filtre catégorie, pagination.
     * Même visibilité que latest().
     *
     * @param array{search?: string, category?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function publicList(array $filters, int $page, int $perPage = self::PUBLIC_PER_PAGE): array
    {
        $conditions = ['published_at IS NOT NULL', 'published_at <= NOW()'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(title LIKE :search1 OR excerpt LIKE :search2)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM news {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'Event::publicList() :
        // PDO::ATTR_EMULATE_PREPARES à false n'accepte pas de valeur liée à
        // cet endroit, sûr ici parce que $page/$perPage sont typés int
        // (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, slug, excerpt, cover_image, category, published_at
             FROM news
             {$where}
             ORDER BY published_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Catégories réellement représentées parmi les articles publiés — même
     * principe qu'Event::publicCategories().
     */
    public static function publicCategories(): array
    {
        $stmt = static::db()->query(
            'SELECT DISTINCT category FROM news
             WHERE published_at IS NOT NULL AND published_at <= NOW()
             ORDER BY category ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Détail d'un article (FIUCC-55) — jamais un brouillon ni programmé dans
     * le futur, même avec le bon slug, même principe qu'Event::findBySlug().
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, title, slug, excerpt, content, cover_image, category, published_at
             FROM news
             WHERE slug = :slug AND published_at IS NOT NULL AND published_at <= NOW()
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Articles de la même catégorie (FIUCC-55, "Related") — jamais l'article
     * courant lui-même, même visibilité que findBySlug().
     */
    public static function related(int $excludeId, string $category, int $limit = 3): array
    {
        $stmt = static::db()->prepare(
            'SELECT title, slug, cover_image, category, published_at
             FROM news
             WHERE id != :exclude_id AND category = :category
               AND published_at IS NOT NULL AND published_at <= NOW()
             ORDER BY published_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(':category', $category);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Liste admin (FIUCC-56) : tous les articles, y compris les brouillons
     * (published_at NULL) et les publications programmées dans le futur.
     *
     * @param array{search?: string, category?: string} $filters
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

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM news {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, slug, cover_image, category, published_at, created_at
             FROM news
             {$where}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Un article, tous champs — formulaire d'édition admin. Distinct de
     * findBySlug() (public, jamais un brouillon/futur).
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Création (FIUCC-56). Slug déduit du titre, jamais saisi ni modifié
     * après coup — même principe qu'Event::create()/Album::create().
     *
     * @param array{title: string, excerpt: ?string, content: ?string, cover_image: ?string,
     *              category: string, author_id: ?int, published_at: ?string} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO news (title, slug, excerpt, content, cover_image, category, author_id, published_at)
             VALUES (:title, :slug, :excerpt, :content, :cover_image, :category, :author_id, :published_at)'
        );
        $stmt->execute([
            'title'        => $data['title'],
            'slug'         => self::uniqueSlug($data['title']),
            'excerpt'      => $data['excerpt'],
            'content'      => $data['content'],
            'cover_image'  => $data['cover_image'],
            'category'     => $data['category'],
            'author_id'    => $data['author_id'],
            'published_at' => $data['published_at'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Mise à jour — ne touche jamais au slug (même raison qu'Event::update()) :
     * un lien déjà partagé ne doit pas casser après une correction de titre.
     * La couverture se change via updateCoverImage() seulement.
     *
     * @param array{title: string, excerpt: ?string, content: ?string, category: string,
     *              published_at: ?string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE news SET title = :title, excerpt = :excerpt, content = :content,
             category = :category, published_at = :published_at
             WHERE id = :id'
        );
        $stmt->execute([
            'title'        => $data['title'],
            'excerpt'      => $data['excerpt'],
            'content'      => $data['content'],
            'category'     => $data['category'],
            'published_at' => $data['published_at'],
            'id'           => $id,
        ]);
    }

    public static function updateCoverImage(int $id, string $path): void
    {
        $stmt = static::db()->prepare('UPDATE news SET cover_image = :cover_image WHERE id = :id');
        $stmt->execute(['cover_image' => $path, 'id' => $id]);
    }

    /**
     * Suppression définitive (FIUCC-56) — aucune table ne référence news en
     * clé étrangère (001_initial_schema.sql), rien à faire côté base au-delà
     * de la ligne elle-même. Le fichier de couverture n'est pas géré par une
     * contrainte SQL — à l'appelant de le supprimer séparément.
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM news WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function uniqueSlug(string $title): string
    {
        $base = self::slugify($title);
        $slug = $base;
        $suffix = 2;

        while (self::slugExists($slug)) {
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

        return $slug !== '' ? $slug : 'article';
    }

    private static function slugExists(string $slug): bool
    {
        $stmt = static::db()->prepare('SELECT COUNT(*) FROM news WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
