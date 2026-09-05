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

if (!function_exists('csrf_field')) {
    /**
     * Champ caché à inclure dans tout <form method="POST">. Vérifié sans
     * exception par App\Core\Router (voir CsrfMiddleware) — aucune route
     * POST ne peut l'omettre.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(\App\Core\Csrf::token()) . '">';
    }
}

if (!function_exists('t')) {
    /**
     * Traduction (App\Core\Lang) — anglais par défaut, français si choisi
     * via le switch de langue. Ne jamais écrire de texte d'interface en dur
     * dans une vue (CLAUDE.md) : passer par t().
     */
    function t(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}
