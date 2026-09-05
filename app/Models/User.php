<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, email, password_hash, role, failed_attempts, locked_until
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        return $user === false ? null : $user;
    }

    /**
     * Incrémente le compteur d'échecs et verrouille le compte si le seuil est atteint.
     */
    public static function registerFailedAttempt(int $id, int $currentAttempts, int $maxAttempts, int $lockoutMinutes): void
    {
        $newAttempts = $currentAttempts + 1;

        if ($newAttempts >= $maxAttempts) {
            $stmt = static::db()->prepare(
                'UPDATE users
                 SET failed_attempts = :attempts, locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)
                 WHERE id = :id'
            );
            $stmt->execute(['attempts' => $newAttempts, 'minutes' => $lockoutMinutes, 'id' => $id]);
            return;
        }

        $stmt = static::db()->prepare('UPDATE users SET failed_attempts = :attempts WHERE id = :id');
        $stmt->execute(['attempts' => $newAttempts, 'id' => $id]);
    }

    public static function resetFailedAttempts(int $id): void
    {
        $stmt = static::db()->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}
