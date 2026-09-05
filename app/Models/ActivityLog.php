<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Traçabilité des actions sensibles (CLAUDE.md §48) — jamais le choix d'un
 * candidat avec l'identité d'un électeur, ce module ne sert pas au vote.
 */
final class ActivityLog extends Model
{
    public static function record(?int $userId, string $action, ?string $entity = null, ?int $entityId = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = static::db()->prepare(
            'INSERT INTO activity_logs (user_id, action, entity, entity_id, ip_address, user_agent)
             VALUES (:user_id, :action, :entity, :entity_id, :ip_address, :user_agent)'
        );

        $packedIp = @inet_pton($ip !== '' ? $ip : '0.0.0.0');

        $stmt->execute([
            'user_id'    => $userId,
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'ip_address' => $packedIp !== false ? $packedIp : inet_pton('0.0.0.0'),
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
        ]);
    }
}
