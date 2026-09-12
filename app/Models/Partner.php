<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Espace partenaires (FIUCC-90, section "Mentorat & partenariats" du
 * cahier — "espace partenaires : anciens, entreprises, sponsors
 * d'événements"). Vitrine gérée par l'admin, pas d'auto-inscription : le
 * cahier ne décrit qu'une liste à afficher, jamais de formulaire public
 * ni de suivi de paiement/niveau de sponsoring — rien de tout ça n'est
 * demandé, pas inventé ici. type reprend exactement les trois
 * catégories du cahier.
 */
final class Partner extends Model
{
    private const PUBLIC_PER_PAGE = 12;
    private const ADMIN_PER_PAGE = 20;

    /**
     * Liste publique (FIUCC-90) — seulement 'published', filtre type
     * facultatif, triée par sort_order (choix manuel de l'admin) puis nom.
     *
     * @param array{type?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function publicList(array $filters, int $page, int $perPage = self::PUBLIC_PER_PAGE): array
    {
        $conditions = ["status = 'published'"];
        $params = [];

        $type = trim((string) ($filters['type'] ?? ''));
        if (in_array($type, ['alumni', 'company', 'sponsor'], true)) {
            $conditions[] = 'type = :type';
            $params['type'] = $type;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM partners {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'Event::publicList() :
        // PDO::ATTR_EMULATE_PREPARES à false, $page/$perPage typés int
        // (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, name, type, logo, description, website_url
             FROM partners
             {$where}
             ORDER BY sort_order ASC, name ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Liste admin (FIUCC-90) — tous statuts, filtre recherche/type/statut.
     *
     * @param array{search?: string, type?: string, status?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(array $filters, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = 'name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if (in_array($type, ['alumni', 'company', 'sponsor'], true)) {
            $conditions[] = 'type = :type';
            $params['type'] = $type;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['draft', 'published'], true)) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM partners {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, name, type, logo, status, sort_order, created_at
             FROM partners
             {$where}
             ORDER BY sort_order ASC, name ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * @param array{name: string, type: string, description: ?string,
     *              website_url: ?string, status: string, sort_order: int,
     *              logo: ?string, created_by: ?int} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO partners (name, type, logo, description, website_url, status, sort_order, created_by)
             VALUES (:name, :type, :logo, :description, :website_url, :status, :sort_order, :created_by)'
        );
        $stmt->execute([
            'name'        => $data['name'],
            'type'        => $data['type'],
            'logo'        => $data['logo'],
            'description' => $data['description'],
            'website_url' => $data['website_url'],
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'],
            'created_by'  => $data['created_by'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Métadonnées seules — pas le logo, changé séparément via updateLogo()
     * seulement si l'admin en envoie un nouveau, même principe que
     * Document::update()/updateFile().
     *
     * @param array{name: string, type: string, description: ?string, website_url: ?string, status: string, sort_order: int} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE partners SET name = :name, type = :type, description = :description,
                 website_url = :website_url, status = :status, sort_order = :sort_order
             WHERE id = :id'
        );
        $stmt->execute([
            'name'        => $data['name'],
            'type'        => $data['type'],
            'description' => $data['description'],
            'website_url' => $data['website_url'],
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'],
            'id'          => $id,
        ]);
    }

    public static function updateLogo(int $id, string $logoPath): void
    {
        $stmt = static::db()->prepare('UPDATE partners SET logo = :logo WHERE id = :id');
        $stmt->execute(['logo' => $logoPath, 'id' => $id]);
    }

    /**
     * Suppression définitive — aucune table ne référence partners en clé
     * étrangère. Le logo sous public/uploads/ n'est pas géré par une
     * contrainte SQL — à l'appelant de le supprimer séparément (voir
     * Admin\PartnerController::delete()).
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM partners WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
