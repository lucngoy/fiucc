<?php
declare(strict_types=1);

/**
 * Suppression des photos de passeport conservées au-delà du délai
 * (App\Services\PassportScanRetention — 90 jours après la décision admin).
 * Même principe que bin/promote_alumni.php : à appeler par le cron de
 * l'hébergeur, une fois par jour suffit :
 *   php /chemin/vers/fiucc-portal/bin/purge_passport_scans.php
 *
 * La même purge tourne aussi à chaque ouverture de la liste des membres
 * côté admin — ce script garantit le délai même sans activité admin.
 *
 * Usage : php bin/purge_passport_scans.php
 *         php bin/purge_passport_scans.php --env=testing   (cible .env.testing)
 */

use App\Core\Config;
use App\Core\Env;
use App\Services\PassportScanRetention;

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

$purged = PassportScanRetention::purgeExpired();

echo "{$purged} photo(s) de passeport supprimée(s)." . PHP_EOL;
