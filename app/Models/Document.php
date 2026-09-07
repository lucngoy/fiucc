<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Espace documents (FIUCC-57/58, section 30 du cahier des charges).
 * documents.visibility (public/members/admin, 001_initial_schema.sql) est
 * une vraie barrière, pas un filtre d'affichage : le fichier lui-même vit
 * hors de public/ (storage/documents/, voir App\Services\Upload::
 * storeDocument()) et n'est jamais atteignable sans repasser par
 * App\Controllers\DocumentController::download(), qui revérifie
 * `visibility` à chaque requête — publicList()/adminList() ci-dessous ne
 * sont qu'un confort d'affichage, jamais la seule protection.
 */
final class Document extends Model
{
    private const PUBLIC_PER_PAGE = 12;
    private const ADMIN_PER_PAGE = 20;

    /**
     * Fragment WHERE selon le niveau d'accès du visiteur — même trois
     * niveaux que la vérification de téléchargement (DocumentController),
     * pour que la liste n'affiche jamais un document que le clic
     * suivant refuserait :
     * - 'admin' : aucun filtre, tout est visible ;
     * - 'member' : 'public' + 'members' ;
     * - 'public' (ou toute autre valeur) : 'public' seulement.
     */
    private static function visibilityClause(string $accessLevel): string
    {
        return match ($accessLevel) {
            'admin'  => '1=1',
            'member' => "visibility IN ('public', 'members')",
            default  => "visibility = 'public'",
        };
    }

    /**
     * Liste publique (FIUCC-57) — recherche, filtre catégorie, pagination,
     * filtrée par visibilité selon le niveau d'accès du visiteur.
     *
     * @param array{search?: string, category?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function publicList(array $filters, string $accessLevel, int $page, int $perPage = self::PUBLIC_PER_PAGE): array
    {
        $conditions = [self::visibilityClause($accessLevel)];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(title LIKE :search1 OR description LIKE :search2)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM documents {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'Event::publicList() :
        // PDO::ATTR_EMULATE_PREPARES à false n'accepte pas de valeur liée à
        // cet endroit, sûr ici parce que $page/$perPage sont typés int
        // (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, description, file_size, mime_type, category, visibility, created_at
             FROM documents
             {$where}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Catégories réellement représentées parmi les documents visibles à ce
     * niveau d'accès — même principe qu'Event::publicCategories().
     */
    public static function publicCategories(string $accessLevel): array
    {
        $where = self::visibilityClause($accessLevel);
        $stmt = static::db()->query(
            "SELECT DISTINCT category FROM documents
             WHERE {$where} AND category IS NOT NULL AND category != ''
             ORDER BY category ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Un document, tous champs, quelle que soit sa visibilité — utilisé à
     * la fois par le formulaire d'édition admin ET par
     * DocumentController::download() pour vérifier les droits avant de
     * streamer le fichier (jamais par publicList()/adminList(), qui ne
     * renvoient pas file_path).
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Liste admin (FIUCC-58) : tous les documents, tous niveaux de
     * visibilité confondus, avec filtre dédié sur la visibilité elle-même
     * (utile pour retrouver "tout ce qui est réservé aux admins").
     *
     * @param array{search?: string, category?: string, visibility?: string} $filters
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
        if ($category !== '') {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        $visibility = trim((string) ($filters['visibility'] ?? ''));
        if (in_array($visibility, ['public', 'members', 'admin'], true)) {
            $conditions[] = 'visibility = :visibility';
            $params['visibility'] = $visibility;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM documents {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT id, title, file_size, mime_type, category, visibility, created_at
             FROM documents
             {$where}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * @param array{title: string, description: ?string, file_path: string,
     *              file_size: int, mime_type: string, category: ?string,
     *              visibility: string, uploaded_by: ?int} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO documents (title, description, file_path, file_size, mime_type, category, visibility, uploaded_by)
             VALUES (:title, :description, :file_path, :file_size, :mime_type, :category, :visibility, :uploaded_by)'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'file_path'   => $data['file_path'],
            'file_size'   => $data['file_size'],
            'mime_type'   => $data['mime_type'],
            'category'    => $data['category'],
            'visibility'  => $data['visibility'],
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Métadonnées seules (titre/description/catégorie/visibilité) — pas le
     * fichier, changé séparément via updateFile() seulement si l'admin en
     * envoie un nouveau (voir Admin\DocumentController::update()).
     *
     * @param array{title: string, description: ?string, category: ?string, visibility: string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE documents SET title = :title, description = :description, category = :category, visibility = :visibility
             WHERE id = :id'
        );
        $stmt->execute([
            'title'       => $data['title'],
            'description' => $data['description'],
            'category'    => $data['category'],
            'visibility'  => $data['visibility'],
            'id'          => $id,
        ]);
    }

    public static function updateFile(int $id, string $filePath, int $fileSize, string $mimeType): void
    {
        $stmt = static::db()->prepare(
            'UPDATE documents SET file_path = :file_path, file_size = :file_size, mime_type = :mime_type WHERE id = :id'
        );
        $stmt->execute([
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'id'        => $id,
        ]);
    }

    /**
     * Suppression définitive — aucune table ne référence documents en clé
     * étrangère (001_initial_schema.sql). Le fichier sous storage/n'est pas
     * géré par une contrainte SQL — à l'appelant de le supprimer séparément
     * (voir Admin\DocumentController::delete()).
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
