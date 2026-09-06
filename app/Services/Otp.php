<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\EmailVerification;

/**
 * Code de vérification email (section 14 du cahier des charges) : 6 chiffres,
 * stocké hashé (password_hash — même primitive que les mots de passe, pas
 * une deuxième méthode de hachage pour un même besoin), expire après
 * security.otp_ttl_minutes, invalidé après security.otp_max_attempts essais
 * incorrects. Renvoi limité par un délai (otp_resend_cooldown_seconds) et un
 * plafond horaire (otp_max_resends_per_hour).
 */
final class Otp
{
    /**
     * @return array{ok: bool, error: string|null, reason: string|null}
     *               reason vaut 'cooldown' ou 'rate_limited' en cas d'échec —
     *               distingue le cas déjà visible côté vue (bouton désactivé
     *               + compte à rebours, inutile de le répéter en alerte) du
     *               cas réellement nouveau à signaler (plafond horaire atteint,
     *               attendre le cooldown ne suffira pas).
     */
    public static function send(int $userId, string $toEmail, string $toName): array
    {
        $wait = self::secondsUntilResendAllowed($userId);

        if ($wait > 0) {
            return [
                'ok' => false,
                'error' => t('verify.resend_cooldown_wait', ['seconds' => $wait]),
                'reason' => 'cooldown',
            ];
        }

        $maxPerHour = (int) Config::get('security.otp_max_resends_per_hour', 5);

        if (EmailVerification::countRecentFor($userId, 60) >= $maxPerHour) {
            return [
                'ok' => false,
                'error' => t('verify.resend_rate_limited'),
                'reason' => 'rate_limited',
            ];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = (int) Config::get('security.otp_ttl_minutes', 15);

        EmailVerification::create($userId, password_hash($code, PASSWORD_DEFAULT), $ttlMinutes);

        Mail::send(
            $toEmail,
            $toName,
            t('mail.otp.subject'),
            t('mail.otp.heading'),
            t('mail.otp.body', ['code' => $code, 'minutes' => $ttlMinutes]),
            [$code]
        );

        return ['ok' => true, 'error' => null, 'reason' => null];
    }

    /**
     * Secondes restantes avant qu'un renvoi soit accepté (0 si déjà autorisé)
     * — utilisé pour afficher un compte à rebours côté vue, sans dupliquer
     * la règle de cooldown déjà appliquée dans send().
     */
    public static function secondsUntilResendAllowed(int $userId): int
    {
        $cooldown = (int) Config::get('security.otp_resend_cooldown_seconds', 60);
        $lastSentAt = EmailVerification::lastSentAt($userId);

        if ($lastSentAt === null) {
            return 0;
        }

        return max(0, strtotime($lastSentAt) + $cooldown - time());
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public static function verify(int $userId, string $submittedCode): array
    {
        $record = EmailVerification::latestActiveFor($userId);

        if ($record === null) {
            return ['ok' => false, 'error' => t('verify.error_no_code')];
        }

        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        $attempts = (int) $record['attempts'];

        if ($attempts >= $maxAttempts) {
            return ['ok' => false, 'error' => t('verify.error_too_many_attempts')];
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            return ['ok' => false, 'error' => t('verify.error_expired')];
        }

        if (!password_verify($submittedCode, $record['code_hash'])) {
            EmailVerification::incrementAttempts((int) $record['id']);

            $remaining = $maxAttempts - ($attempts + 1);

            if ($remaining <= 0) {
                return ['ok' => false, 'error' => t('verify.error_too_many_attempts')];
            }

            return ['ok' => false, 'error' => t('verify.error_incorrect_remaining', ['remaining' => $remaining])];
        }

        EmailVerification::markConsumed((int) $record['id']);

        return ['ok' => true, 'error' => null];
    }
}
