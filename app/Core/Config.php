<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Charge les fichiers de config/ (un tableau par fichier) et expose une
 * lecture en notation pointée : Config::get('database.host').
 */
final class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $directory): void
    {
        if (self::$loaded) {
            return;
        }

        foreach (glob(rtrim($directory, '/') . '/*.php') as $file) {
            self::$items[basename($file, '.php')] = require $file;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
