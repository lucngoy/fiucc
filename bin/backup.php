<?php
declare(strict_types=1);

/**
 * Sauvegarde base + uploads + documents en ligne de commande (FIUCC-80) —
 * même service (App\Services\Backup) que le bouton du back office
 * (Admin\BackupController), pour une entrée cron à documenter plutôt qu'à
 * déployer : ce projet n'a aucune infrastructure de tâches planifiées
 * aujourd'hui (voir CLAUDE.md), l'ajouter dépasserait ce ticket. Un
 * hébergeur qui propose des tâches planifiées peut appeler cette commande
 * directement, ex. une fois par nuit :
 *   php /chemin/vers/fiucc-portal/bin/backup.php
 *
 * Usage : php bin/backup.php
 *         php bin/backup.php --env=testing   (cible .env.testing)
 */

use App\Core\Config;
use App\Core\Env;
use App\Services\Backup;

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

try {
    $result = Backup::create();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Échec de la sauvegarde : ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$sizeMb = number_format($result['size'] / 1024 / 1024, 1);
echo "Sauvegarde créée : {$result['filename']} ({$sizeMb} Mo)" . PHP_EOL;
