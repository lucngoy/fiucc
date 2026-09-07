<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Message flash — survit une seule redirection, via la session. Pour des
 * données qui ne tiennent pas dans un ?notice=code fixe (ex. liste de
 * fichiers en échec lors d'un upload par lot, FIUCC-54) : jamais dans
 * l'URL (pas de noms de fichiers dans la barre d'adresse, l'historique ou
 * les logs d'accès), lu une seule fois puis effacé.
 */
final class Flash
{
    public static function set(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function get(string $key): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }
}
