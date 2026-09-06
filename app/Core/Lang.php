<?php
declare(strict_types=1);

namespace App\Core;

/**
 * i18n minimale : resources/lang/{locale}.php retourne un tableau associatif
 * à plat ("nav.home" => "Home"). Repli sur l'anglais si la clé manque dans
 * la langue courante, puis sur la clé elle-même si absente partout — jamais
 * d'erreur visible pour une traduction manquante.
 *
 * Locale active : session (choix explicite via le switch de langue) sinon
 * config/app.php (app.locale, depuis APP_LOCALE). Le français est la langue
 * par défaut de l'interface (cahier des charges §38 : "Version initiale :
 * Français" — prime sur toute autre indication) ; l'anglais est prévu dès
 * cette version initiale, pas ajouté après coup.
 */
final class Lang
{
    private const DEFAULT_LOCALE = 'fr';
    private const SUPPORTED = ['en', 'fr'];

    private static array $strings = [];

    public static function locale(): string
    {
        $locale = (string) ($_SESSION['locale'] ?? Config::get('app.locale', self::DEFAULT_LOCALE));

        return self::isSupported($locale) ? $locale : self::DEFAULT_LOCALE;
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function get(string $key, array $replace = []): string
    {
        $locale = self::locale();
        $value = self::load($locale)[$key] ?? self::load(self::DEFAULT_LOCALE)[$key] ?? $key;

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
        }

        return $value;
    }

    private static function load(string $locale): array
    {
        if (!isset(self::$strings[$locale])) {
            $path = BASE_PATH . '/resources/lang/' . $locale . '.php';
            self::$strings[$locale] = is_file($path) ? require $path : [];
        }

        return self::$strings[$locale];
    }
}
