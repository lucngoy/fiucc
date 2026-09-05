<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'FIUCC Portal'),
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => Env::get('APP_DEBUG', false),
    'url'      => Env::get('APP_URL', 'http://localhost'),
    'locale'   => Env::get('APP_LOCALE', 'en'),
    // Utilisé en phase 4 comme sel de vote_hash — pas encore obligatoire.
    'key'      => Env::get('APP_KEY'),
    'timezone' => 'Europe/Istanbul',
];
