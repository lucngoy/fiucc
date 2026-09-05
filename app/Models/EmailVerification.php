<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EmailVerification extends Model
{
    public static function create(int $userId, string $codeHash, int $ttlMinutes): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO email_verifications (user_id, code_hash, expires_at)
             VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL :ttl MINUTE))'
        );
        $stmt->execute(['user_id' => $userId, 'code_hash' => $codeHash, 'ttl' => $ttlMinutes]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Le code le plus récent non consommé — un renvoi rend implicitement
     * caduc tout code précédent, sans avoir besoin de le supprimer.
     */
    public static function latestActiveFor(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, code_hash, attempts, expires_at
             FROM email_verifications
             WHERE user_id = :user_id AND consumed_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function countRecentFor(int $userId, int $sinceMinutesAgo): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM email_verifications
             WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute(['user_id' => $userId, 'minutes' => $sinceMinutesAgo]);

        return (int) $stmt->fetchColumn();
    }

    public static function lastSentAt(int $userId): ?string
    {
        $stmt = static::db()->prepare(
            'SELECT created_at FROM email_verifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    public static function incrementAttempts(int $id): void
    {
        $stmt = static::db()->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function markConsumed(int $id): void
    {
        $stmt = static::db()->prepare('UPDATE email_verifications SET consumed_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
