<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Un jeton par session, stable jusqu'à la déconnexion (pas de rotation à
 * chaque requête — évite les faux échecs avec plusieurs onglets ouverts).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($expected) || !is_string($token) || $token === '') {
            return false;
        }

        // hash_equals plutôt que == : comparaison en temps constant.
        return hash_equals($expected, $token);
    }
}
