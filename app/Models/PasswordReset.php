<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Liens "mot de passe oublié" (migration 019) — voir
 * App\Services\PasswordResetLink pour le cycle complet.
 */
final class PasswordReset extends Model
{
    public static function create(int $userId, string $tokenHash, int $ttlMinutes): void
    {
        $stmt = static::db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL :ttl MINUTE))'
        );
        $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash, 'ttl' => $ttlMinutes]);
    }

    /**
     * Lien encore utilisable : ni consommé, ni expiré. L'expiration est
     * comparée côté MySQL (NOW()), même horloge que celle qui l'a posée.
     */
    public static function findUsable(string $tokenHash): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Consommation atomique : la garde est dans le WHERE, pas seulement
     * dans findUsable() — deux soumissions simultanées du même lien ne
     * peuvent pas réussir toutes les deux.
     */
    public static function consume(int $id): bool
    {
        $stmt = static::db()->prepare(
            'UPDATE password_resets SET used_at = NOW()
             WHERE id = :id AND used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Après tout changement de mot de passe (lien, profil ou admin) : un
     * ancien lien encore en circulation ne doit plus rien pouvoir changer.
     */
    public static function invalidateAllFor(int $userId): void
    {
        $stmt = static::db()->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public static function countRecentFor(int $userId, int $sinceMinutesAgo): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM password_resets
             WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->execute(['user_id' => $userId, 'minutes' => $sinceMinutesAgo]);

        return (int) $stmt->fetchColumn();
    }
}
