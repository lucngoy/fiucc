<?php
declare(strict_types=1);

/**
 * Applique les migrations de database/migrations/*.sql dans l'ordre du nom
 * de fichier, une seule fois chacune (table de suivi `migrations`).
 * Contrairement aux seeders, s'exécute aussi en production — une migration
 * est un changement de schéma, pas une donnée de test.
 *
 * Usage : php database/migrate.php
 *         php database/migrate.php --env=testing   (cible .env.testing — FIUCC-79)
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);

define('BASE_PATH', $root);

require $root . '/vendor/autoload.php';

$envSuffix = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--env=')) {
        $envSuffix = substr($arg, strlen('--env='));
    }
}

Env::load($root . '/.env' . ($envSuffix !== null ? '.' . $envSuffix : ''));
Config::load($root . '/config');

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(191) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_name (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

$ranAny = false;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Applique {$name}...\n";
    $pdo->exec((string) file_get_contents($file));

    $stmt = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:name)');
    $stmt->execute(['name' => $name]);

    $ranAny = true;
}

echo $ranAny ? "Migrations à jour.\n" : "Rien à appliquer, déjà à jour.\n";
