<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'host'     => Env::require('DB_HOST'),
    'port'     => (int) Env::get('DB_PORT', 3306),
    'database' => Env::require('DB_DATABASE'),
    'username' => Env::require('DB_USERNAME'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset'  => Env::get('DB_CHARSET', 'utf8mb4'),
];
