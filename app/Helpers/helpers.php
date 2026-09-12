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
     * Traduction (App\Core\Lang) — français par défaut (cahier des charges
     * §38), anglais si choisi via le switch de langue. Ne jamais écrire de
     * texte d'interface en dur dans une vue (CLAUDE.md) : passer par t().
     */
    function t(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}

if (!function_exists('local_date')) {
    /**
     * Date lisible dans la langue active — App\Core\Lang::locale(). Pas
     * d'extension intl sur cet environnement : mois français via une table
     * fixe plutôt que strftime() (déprécié depuis PHP 8.1, dépendant de la
     * locale système, peu fiable d'un serveur à l'autre).
     */
    function local_date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return '';
        }

        if (\App\Core\Lang::locale() !== 'fr') {
            return date('F j, Y', $timestamp);
        }

        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        return (int) date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    }
}

if (!function_exists('meta_description')) {
    /**
     * Dérive une meta description (FIUCC-75, section 36 du cahier des
     * charges) à partir d'un champ de contenu long (description d'un
     * événement, d'un album...) — jamais tronquée au milieu d'un mot, et
     * jamais l'introduction d'une balise HTML si le champ en contenait
     * (strip_tags avant tout). Repli sur seo.default_description si le
     * contenu est vide, jamais une chaîne vide dans <meta name="description">.
     */
    function meta_description(?string $text, int $maxLength = 160): string
    {
        $text = trim(strip_tags((string) $text));

        if ($text === '') {
            return t('seo.default_description');
        }

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated) . '…';
    }
}

if (!function_exists('initials')) {
    /**
     * Repli d'avatar quand aucune photo n'est disponible (profil, page
     * gouvernement...) — deux lettres maximum par défaut.
     */
    function initials(string $fullName, int $max = 2): string
    {
        $result = '';

        foreach (preg_split('/\s+/', trim($fullName)) as $part) {
            if ($part !== '') {
                $result .= mb_strtoupper(mb_substr($part, 0, 1));
            }
        }

        return mb_substr($result, 0, $max);
    }
}
