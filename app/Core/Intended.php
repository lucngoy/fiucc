<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Page où l'utilisateur se trouvait avant d'être renvoyé vers /login
 * (session expirée, lien protégé ouvert sans être connecté) — pour l'y
 * ramener une fois reconnecté, au lieu de toujours /profile ou /admin.
 *
 * Mémorisée en session par les middlewares d'accès au moment de la
 * redirection, jamais passée dans l'URL de /login : rien à falsifier dans
 * un lien envoyé à quelqu'un. Et chaque URL relue (mémorisée ou Referer)
 * passe par localPath() — jamais une redirection ouverte vers un autre
 * domaine.
 */
final class Intended
{
    private const KEY = '_intended_url';
    /** Au-delà, la page mémorisée n'a plus de rapport avec la reconnexion. */
    private const MAX_AGE_SECONDS = 3600;
    /** Jamais une destination après connexion : boucle ou page sans objet. */
    private const EXCLUDED_PREFIXES = ['/login', '/logout', '/register', '/verify-email', '/password'];

    /**
     * Requête refusée faute de session. GET : la page demandée elle-même.
     * POST (formulaire envoyé après expiration) : la page du formulaire,
     * via le Referer — rejouer le POST lui-même n'aurait aucun sens, ses
     * données sont perdues avec la session.
     */
    public static function remember(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $url = $method === 'GET'
            ? (string) ($_SERVER['REQUEST_URI'] ?? '')
            : (string) ($_SERVER['HTTP_REFERER'] ?? '');

        $path = self::localPath($url);

        if ($path === null || self::isExcluded($path)) {
            return;
        }

        $_SESSION[self::KEY] = ['url' => $path, 'at' => time()];
    }

    /**
     * Destination après connexion, consommée (une seule fois). Un admin
     * n'est ramené que dans /admin, un membre jamais dans /admin : une page
     * mémorisée d'une autre session sur le même navigateur ne doit pas
     * mener quelqu'un vers un écran qu'il ne peut pas ouvrir.
     */
    public static function pull(string $role): ?string
    {
        $entry = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        if (!is_array($entry) || time() - (int) ($entry['at'] ?? 0) > self::MAX_AGE_SECONDS) {
            return null;
        }

        $path = self::localPath((string) ($entry['url'] ?? ''));

        if ($path === null) {
            return null;
        }

        $inAdmin = $path === '/admin' || str_starts_with($path, '/admin/') || str_starts_with($path, '/admin?');

        return ($role === 'admin') === $inAdmin ? $path : null;
    }

    /**
     * Bouton « Retour » des pages d'erreur : la page d'où l'on vient, si
     * elle est sur ce site et n'est pas la page d'erreur elle-même.
     */
    public static function backUrl(): ?string
    {
        $path = self::localPath((string) ($_SERVER['HTTP_REFERER'] ?? ''));

        if ($path === null || $path === ($_SERVER['REQUEST_URI'] ?? null)) {
            return null;
        }

        return $path;
    }

    /**
     * Chemin local (chemin + requête) ou null. Accepte un chemin relatif à
     * la racine, ou une URL absolue vers ce même hôte. Refuse « //x » et
     * « /\x » (que les navigateurs lisent comme un autre domaine), tout
     * caractère de contrôle, et les URL démesurées.
     */
    public static function localPath(string $url): ?string
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        if ($url[0] !== '/') {
            $parts = parse_url($url);
            $host = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

            if ($parts === false || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || $host === '' || $host !== ($_SERVER['HTTP_HOST'] ?? null)) {
                return null;
            }

            $url = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        if ($url === '' || $url[0] !== '/' || (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\'))) {
            return null;
        }

        return $url;
    }

    private static function isExcluded(string $path): bool
    {
        $route = strtok($path, '?');

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($route === $prefix || str_starts_with((string) $route, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
