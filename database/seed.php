<?php
declare(strict_types=1);

/**
 * Exécute tous les seeders de database/seeders/*.php dans l'ordre du nom de
 * fichier. Chaque seeder est un script qui utilise directement $pdo (fourni
 * ici) — pas son propre bootstrap.
 *
 * Refuse de s'exécuter si APP_ENV=production : les seeders créent des
 * comptes de test et des données de démonstration, jamais de la donnée réelle.
 *
 * Usage : php database/seed.php
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);

define('BASE_PATH', $root);

require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
Config::load($root . '/config');

if (Config::get('app.env') === 'production') {
    fwrite(STDERR, "Les seeders ne peuvent pas s'exécuter avec APP_ENV=production.\n");
    exit(1);
}

$pdo = Database::connection();

$files = glob(__DIR__ . '/seeders/*.php');
sort($files);

foreach ($files as $file) {
    echo '== ' . basename($file) . " ==\n";
    require $file;
}

echo "Seed terminé.\n";
