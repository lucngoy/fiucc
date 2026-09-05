<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Ralentissement progressif par (compte, IP) — voir migration 002.
 * Jamais de blocage : uniquement un compteur consulté pour calculer un
 * délai, plafonné, appliqué côté contrôleur avant la réponse.
 */
final class LoginThrottle extends Model
{
    public static function failedAttempts(int $userId, string $ip): int
    {
        $stmt = static::db()->prepare(
            'SELECT failed_attempts FROM login_throttle WHERE user_id = :user_id AND ip_address = :ip'
        );
        $stmt->execute(['user_id' => $userId, 'ip' => self::packIp($ip)]);

        $row = $stmt->fetch();

        return $row === false ? 0 : (int) $row['failed_attempts'];
    }

    public static function registerFailure(int $userId, string $ip): void
    {
        $stmt = static::db()->prepare(
            'INSERT INTO login_throttle (user_id, ip_address, failed_attempts, last_attempt_at)
             VALUES (:user_id, :ip, 1, NOW())
             ON DUPLICATE KEY UPDATE
                 failed_attempts = failed_attempts + 1,
                 last_attempt_at = NOW()'
        );
        $stmt->execute(['user_id' => $userId, 'ip' => self::packIp($ip)]);
    }

    public static function reset(int $userId, string $ip): void
    {
        $stmt = static::db()->prepare(
            'DELETE FROM login_throttle WHERE user_id = :user_id AND ip_address = :ip'
        );
        $stmt->execute(['user_id' => $userId, 'ip' => self::packIp($ip)]);
    }

    private static function packIp(string $ip): string
    {
        $packed = @inet_pton($ip !== '' ? $ip : '0.0.0.0');

        return $packed !== false ? $packed : (string) inet_pton('0.0.0.0');
    }
}
