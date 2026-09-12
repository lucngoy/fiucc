<?php
declare(strict_types=1);

/**
 * Passage automatique en alumni (FIUCC-85, section "Espace Alumni" du
 * cahier — "passage en alumni... automatique via graduation_year") en
 * ligne de commande — même raisonnement que bin/backup.php : ce projet
 * n'a aucune infrastructure de tâches planifiées aujourd'hui (voir
 * CLAUDE.md), l'ajouter dépasserait ce ticket. Pour une entrée cron à
 * documenter plutôt qu'à déployer :
 *   php /chemin/vers/fiucc-portal/bin/promote_alumni.php
 *
 * Le passage manuel (graduation_year absent ou faux) reste un bouton du
 * back office (Admin\MemberController::markAlumni()) — ce script ne
 * couvre que le cas "automatique".
 *
 * Usage : php bin/promote_alumni.php
 *         php bin/promote_alumni.php --env=testing   (cible .env.testing)
 */

use App\Core\Config;
use App\Core\Env;
use App\Models\Member;

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

$promoted = Member::promoteGraduatedStudents();

echo "{$promoted} membre(s) passé(s) alumni." . PHP_EOL;
