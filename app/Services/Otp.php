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
     * @return array{ok: bool, error: string|null}
     */
    public static function send(int $userId, string $toEmail, string $toName): array
    {
        $cooldown = (int) Config::get('security.otp_resend_cooldown_seconds', 60);
        $lastSentAt = EmailVerification::lastSentAt($userId);

        if ($lastSentAt !== null) {
            $wait = strtotime($lastSentAt) + $cooldown - time();

            if ($wait > 0) {
                return ['ok' => false, 'error' => "Please wait {$wait} second(s) before requesting a new code."];
            }
        }

        $maxPerHour = (int) Config::get('security.otp_max_resends_per_hour', 5);

        if (EmailVerification::countRecentFor($userId, 60) >= $maxPerHour) {
            return ['ok' => false, 'error' => 'You have requested too many codes recently. Please try again later.'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttlMinutes = (int) Config::get('security.otp_ttl_minutes', 15);

        EmailVerification::create($userId, password_hash($code, PASSWORD_DEFAULT), $ttlMinutes);

        Mail::send(
            $toEmail,
            $toName,
            'Your verification code',
            'Verify your email',
            "<p>Your verification code is: <strong>{$code}</strong></p>"
                . "<p>This code expires in {$ttlMinutes} minutes and can only be used once.</p>",
            [$code]
        );

        return ['ok' => true, 'error' => null];
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public static function verify(int $userId, string $submittedCode): array
    {
        $record = EmailVerification::latestActiveFor($userId);

        if ($record === null) {
            return ['ok' => false, 'error' => 'No verification code found. Please request a new one.'];
        }

        $maxAttempts = (int) Config::get('security.otp_max_attempts', 5);
        $attempts = (int) $record['attempts'];

        if ($attempts >= $maxAttempts) {
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'This code has expired. Please request a new one.'];
        }

        if (!password_verify($submittedCode, $record['code_hash'])) {
            EmailVerification::incrementAttempts((int) $record['id']);

            $remaining = $maxAttempts - ($attempts + 1);

            if ($remaining <= 0) {
                return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
            }

            return ['ok' => false, 'error' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        EmailVerification::markConsumed((int) $record['id']);

        return ['ok' => true, 'error' => null];
    }
}
