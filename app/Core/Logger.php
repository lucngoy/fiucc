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

        $path = BASE_PATH . '/storage/logs/' . $channel . '.log';
        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
