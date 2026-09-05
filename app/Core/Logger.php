<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logger fichier minimal — un canal = un fichier storage/logs/{channel}.log.
 * Pour la rotation quotidienne (ex. les erreurs applicatives), le canal
 * inclut la date : Logger::error('app-' . date('Y-m-d'), ...).
 *
 * Distinct de App\Models\ActivityLog : ActivityLog est le journal d'audit en
 * base (actions sensibles, section 48 du cahier des charges — admin login,
 * validation de membre, élection ouverte/fermée...), consultable par
 * l'administration. Logger est la sortie technique fichier (erreurs, envois
 * d'email simulés en local...), jamais destinée à un écran d'administration.
 */
final class Logger
{
    /**
     * Filet de sécurité par motif — ne remplace pas un masquage explicite
     * (ex. App\Services\Mail::send() $secrets), le complète pour ce qu'on
     * oublierait de déclarer. Appliqué à toute ligne, sur tous les canaux.
     */
    private const PATTERN_6_DIGITS = '/\b\d{6}\b/';
    private const PATTERN_LONG_HEX = '/\b[0-9a-fA-F]{32,}\b/';
    private const PATTERN_LABELLED = '/\b(password|token|code)\b((?:\s+is)?\s*[:=]\s*)\S+/i';

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'info', $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'warning', $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'error', $message, $context);
    }

    private static function write(string $channel, string $level, string $message, array $context): void
    {
        $contextSuffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, $contextSuffix);
        $line = self::redact($line);

        $path = BASE_PATH . '/storage/logs/' . $channel . '.log';
        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Masque par motif ce qui ressemble à un secret : code à 6 chiffres
     * isolé (OTP), chaîne hexadécimale de 32 caractères ou plus (token de
     * vote, CSRF...), et tout ce qui suit "password"/"token"/"code" après
     * un délimiteur explicite (":", "=", "is:") — pas juste le mot lui-même,
     * pour ne pas mutiler une phrase normale ("code expires in...").
     */
    private static function redact(string $text): string
    {
        $text = preg_replace(self::PATTERN_6_DIGITS, '[REDACTED]', $text) ?? $text;
        $text = preg_replace(self::PATTERN_LONG_HEX, '[REDACTED]', $text) ?? $text;
        $text = preg_replace(self::PATTERN_LABELLED, '$1$2[REDACTED]', $text) ?? $text;

        return $text;
    }
}
