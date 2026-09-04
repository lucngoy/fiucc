<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lecture minimale d'un fichier .env — pas de dépendance à vlucas/phpdotenv.
 */
final class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path)) {
            throw new RuntimeException("Fichier .env introuvable : {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $rawValue] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            self::$variables[$key] = self::parseValue(trim($rawValue));
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$variables[$key] ?? $default;
    }

    /**
     * @throws RuntimeException si la variable est absente ou vide
     */
    public static function require(string $key): mixed
    {
        $value = self::$variables[$key] ?? null;

        if ($value === null || $value === '') {
            throw new RuntimeException("Variable d'environnement obligatoire manquante : {$key}");
        }

        return $value;
    }

    private static function parseValue(string $value): string|bool|null
    {
        // valeur entre guillemets : conservée telle quelle, sans conversion
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            if (strlen($value) >= 2 && str_ends_with($value, $quote)) {
                return substr($value, 1, -1);
            }
            return $value;
        }

        // commentaire en fin de ligne pour une valeur non-quotée
        $hashPos = strpos($value, '#');
        if ($hashPos !== false) {
            $value = rtrim(substr($value, 0, $hashPos));
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}
