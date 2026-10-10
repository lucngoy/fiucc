<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Messages du formulaire /contact (migration 021) : copie en base de chaque
 * message envoyé par email — limitation de débit par IP, et aucun message
 * perdu si l'envoi SMTP échoue (mail_sent = 0, visible dans le back office).
 */
final class ContactMessage extends Model
{
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, ip_address)
             VALUES (:name, :email, :subject, :message, :ip_address)'
        );
        $stmt->execute([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'subject'    => $data['subject'],
            'message'    => $data['message'],
            'ip_address' => $data['ip_address'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    public static function markSent(int $id): void
    {
        $stmt = static::db()->prepare('UPDATE contact_messages SET mail_sent = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function countRecentFromIp(string $ip, int $minutes): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM contact_messages
             WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue('ip', $ip);
        $stmt->bindValue('minutes', $minutes, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function latest(int $limit): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, name, email, subject, message, mail_sent, created_at
             FROM contact_messages ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
