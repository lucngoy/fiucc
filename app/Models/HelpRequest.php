<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Demandes d'aide adressées au gouvernement (migration 028, page /help).
 * Lues par les admins et par le membre auteur, jamais par d'autres membres.
 */
final class HelpRequest extends Model
{
    public const CATEGORIES = ['housing', 'health', 'administrative', 'financial', 'academic', 'safety', 'other'];
    public const STATUSES = ['open', 'in_progress', 'closed'];
    public const ADMIN_PER_PAGE = 25;

    /**
     * @param array{member_id: int, category: string, subject: string, message: string} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO help_requests (member_id, category, subject, message)
             VALUES (:member_id, :category, :subject, :message)'
        );
        $stmt->execute([
            'member_id' => $data['member_id'],
            'category'  => $data['category'],
            'subject'   => $data['subject'],
            'message'   => $data['message'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Demande avec l'auteur (nom, email, numéro de membre) : fiche admin et
     * envoi de la réponse.
     */
    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT h.*, m.full_name, m.membership_number, m.whatsapp, m.phone, m.user_id, u.email
             FROM help_requests h
             INNER JOIN members m ON m.id = h.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE h.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forMember(int $memberId): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, category, subject, status, response, created_at, updated_at, closed_at
             FROM help_requests WHERE member_id = :member_id
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['member_id' => $memberId]);

        return $stmt->fetchAll();
    }

    public static function countRecentForMember(int $memberId, int $hours): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM help_requests
             WHERE member_id = :member_id AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)'
        );
        $stmt->bindValue('member_id', $memberId, \PDO::PARAM_INT);
        $stmt->bindValue('hours', $hours, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Liste admin, les demandes ouvertes d'abord puis les plus récentes.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(?string $status, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $where = '';
        $params = [];

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $where = 'WHERE h.status = :status';
            $params['status'] = $status;
        }

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM help_requests h {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = static::db()->prepare(
            "SELECT h.id, h.category, h.subject, h.status, h.created_at, h.updated_at, m.full_name
             FROM help_requests h
             INNER JOIN members m ON m.id = h.member_id
             {$where}
             ORDER BY FIELD(h.status, 'open', 'in_progress', 'closed'), h.created_at DESC, h.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function countByStatus(string $status): int
    {
        $stmt = static::db()->prepare('SELECT COUNT(*) FROM help_requests WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Statut et réponse du bureau. closed_at suit le statut : posé à la
     * fermeture, effacé si la demande est rouverte.
     */
    public static function updateHandling(int $id, string $status, ?string $response, int $adminUserId): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        $stmt = static::db()->prepare(
            "UPDATE help_requests
             SET status = :status, response = :response, handled_by = :admin_id,
                 closed_at = CASE WHEN :closing = 1 THEN COALESCE(closed_at, NOW()) ELSE NULL END
             WHERE id = :id"
        );
        $stmt->execute([
            'status'   => $status,
            'response' => $response,
            'admin_id' => $adminUserId,
            'closing'  => $status === 'closed' ? 1 : 0,
            'id'       => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * « AIDE-2026-0042 » : année de création et id sur 4 chiffres.
     */
    public static function reference(array $request): string
    {
        return sprintf('AIDE-%s-%04d', date('Y', strtotime((string) $request['created_at'])), (int) $request['id']);
    }
}
