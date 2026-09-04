<?php
declare(strict_types=1);

/**
 * FIUCC Portal — point d'entrée unique.
 * Tout le trafic passe par ce fichier (voir public/.htaccess).
 */

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// 1. Environnement et configuration
Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$isLocal = Config::get('app.env') === 'local';

// 2. Fuseau horaire
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

// 3. Erreurs — journalisées toujours, affichées uniquement en local (CLAUDE.md, règle 10)
error_reporting(E_ALL);
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($isLocal): void {
    error_log(sprintf(
        '%s: %s in %s:%d%s%s',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        PHP_EOL,
        $exception->getTraceAsString()
    ));

    http_response_code(500);

    if ($isLocal) {
        echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }

    echo 'Something went wrong. Please try again later.';
});

// 4. Session sécurisée
session_set_cookie_params([
    'lifetime' => (int) Config::get('security.session_lifetime', 120) * 60,
    'path'     => '/',
    'domain'   => '',
    'secure'   => !$isLocal,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// TODO FIUCC-15 :
// 5. charger routes/web.php et routes/admin.php
// 6. dispatcher la requête via App\Core\Router

echo 'FIUCC Portal — bootstrap OK (environment: '
    . htmlspecialchars((string) Config::get('app.env'), ENT_QUOTES, 'UTF-8')
    . ')';
