<?php
declare(strict_types=1);

/**
 * Bootstrap de la suite de tests (FIUCC-79) — chargé une seule fois, avant
 * toute classe de test, par phpunit.xml (`bootstrap="tests/bootstrap.php"`).
 *
 * Rôle UNIQUE de ce fichier : charger l'environnement de test et refuser de
 * continuer si ce n'est pas, sans ambiguïté, un environnement de test
 * isolé — jamais .env (développement), a fortiori jamais la production.
 * Aucune autre responsabilité ici (pas de migration, pas de seed) : voir
 * "composer test" (composer.json) pour l'enchaînement complet.
 *
 * La garde est double, et les deux conditions sont nécessaires :
 * - APP_ENV doit valoir exactement 'testing' (jamais 'local', jamais
 *   'production', jamais une valeur absente qui retomberait sur un défaut) ;
 * - le nom de la base doit se terminer par '_test' — même si APP_ENV était
 *   mal positionné par erreur, ce second filet empêche de pointer vers
 *   `congolese_community` (dev) ou tout nom de base de production.
 * Un seul des deux ne suffit pas : c'est délibéré (défense en profondeur,
 * même principe que chaque garde SQL du module électoral).
 *
 * Le fichier .env à charger n'est pas fixé en dur : par défaut
 * `.env.testing`, mais surchageable via la variable d'environnement
 * TEST_ENV_FILE — uniquement pour permettre à
 * tests/Bootstrap/DatabaseIsolationGuardTest.php de prouver, depuis un
 * process séparé, que ce garde-fou refuse bien de démarrer sur un mauvais
 * environnement. Ne jamais utiliser TEST_ENV_FILE en dehors de ce test.
 */

use App\Core\Config;
use App\Core\Env;

$root = dirname(__DIR__);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}

require $root . '/vendor/autoload.php';

$envFile = getenv('TEST_ENV_FILE');
$envFile = is_string($envFile) && $envFile !== '' ? $envFile : $root . '/.env.testing';

Env::load($envFile);
Config::load($root . '/config');
// Même fuseau qu'en production (public/index.php) : les dates écrites par
// PHP et NOW() côté MySQL (App\Core\Database) doivent concorder.
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$appEnv = Config::get('app.env');
$database = (string) Config::get('database.database');

if ($appEnv !== 'testing' || !str_ends_with($database, '_test')) {
    fwrite(STDERR, sprintf(
        "Refus de démarrer les tests : APP_ENV=%s, DB_DATABASE=%s.\n" .
        "Les tests exigent APP_ENV=testing ET un nom de base se terminant par " .
        "'_test' — jamais la base de développement, a fortiori jamais la " .
        "production. Vérifiez %s.\n",
        var_export($appEnv, true),
        var_export($database, true),
        $envFile
    ));
    exit(1);
}
