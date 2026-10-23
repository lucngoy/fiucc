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
 *
 * Sans accès en ligne de commande (InfinityFree) : même logique depuis le
 * back office, /admin/migrations (App\Services\Migrator).
 */

use App\Core\Config;
use App\Core\Env;
use App\Services\Migrator;

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

$result = Migrator::applyPending(static function (string $name): void {
    echo "Applique {$name}...\n";
});

if ($result['failed'] !== null) {
    fwrite(STDERR, "Échec de {$result['failed']} : {$result['error']}\n");
    exit(1);
}

echo $result['applied'] !== [] ? "Migrations à jour.\n" : "Rien à appliquer, déjà à jour.\n";
