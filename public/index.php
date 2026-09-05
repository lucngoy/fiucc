<?php
declare(strict_types=1);

/**
 * FIUCC Portal — point d'entrée unique.
 * Tout le trafic passe par ce fichier (voir public/.htaccess).
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\Router;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// 1. Handlers d'erreurs — enregistrés AVANT tout code pouvant lever (Env, Config).
//    Repli sûr par défaut : rien affiché, tout journalisé. Reconfiguré une fois
//    app.env connu (voir plus bas). Sans ça, une exception levée pendant le
//    chargement de la config n'est interceptée par personne et Apache renvoie
//    un 500 nu, sans passer par storage/logs/.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log');

$bootstrap = ['local' => false];

$renderErrorPage = static function (string $detail) use (&$bootstrap): void {
    if (!headers_sent()) {
        http_response_code(500);
    }

    if ($bootstrap['local']) {
        echo '<pre>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo 'Something went wrong. Please try again later.';
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($renderErrorPage): void {
    error_log(sprintf(
        '%s: %s in %s:%d%s%s',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL,
        $exception->getTraceAsString()
    ));

    $renderErrorPage((string) $exception);
});

// Filet de sécurité pour les erreurs fatales que set_error_handler ne reçoit
// jamais (parse error dans un fichier inclus, dépassement mémoire, timeout).
register_shutdown_function(static function () use ($renderErrorPage): void {
    $error = error_get_last();

    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    $detail = sprintf('%s in %s:%d', $error['message'], $error['file'], $error['line']);
    error_log('Fatal: ' . $detail);

    $renderErrorPage($detail);
});

// Fuseau sûr le temps de charger la config — sinon les erreurs levées par
// Env::load()/Config::load() sont horodatées avec le fuseau par défaut de
// php.ini au lieu de celui de l'app. Réappliqué avec la vraie valeur juste après.
date_default_timezone_set('UTC');

// 2. Environnement et configuration
Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$isLocal = Config::get('app.env') === 'local';
$bootstrap['local'] = $isLocal;
ini_set('display_errors', $isLocal ? '1' : '0');

// 3. Fuseau horaire réel
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

// 4. Session sécurisée
session_name((string) Config::get('security.session_name', 'fiucc_session'));
session_set_cookie_params([
    'lifetime' => (int) Config::get('security.session_lifetime', 120) * 60,
    'path'     => '/',
    'domain'   => '',
    'secure'   => !$isLocal,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// 5. Routing
$router = new Router();
$router->loadRoutes(BASE_PATH . '/routes/web.php');
$router->loadRoutes(BASE_PATH . '/routes/admin.php', ['admin']);

// Le script peut être servi depuis un sous-dossier (ex. /web/fiucc-portal/public
// en dev sans vhost dédié) : on retire ce préfixe pour router sur le chemin réel.
$basePath = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));

$router->dispatch((string) $_SERVER['REQUEST_METHOD'], (string) $_SERVER['REQUEST_URI'], $basePath);
