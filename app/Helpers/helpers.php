<?php
declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Échappement HTML par défaut dans les vues — jamais de sortie brute (CLAUDE.md, règle 5).
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('component')) {
    /**
     * Rend un composant réutilisable de resources/views/partials/{name}.php.
     */
    function component(string $name, array $data = []): string
    {
        return \App\Core\View::partial('partials.' . $name, $data);
    }
}
