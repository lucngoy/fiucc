<?php
declare(strict_types=1);

/**
 * Régénère database/schema_keys.json — la structure des clés attendue par
 * App\Services\SchemaKeys — à partir d'une base construite par les seules
 * migrations (la base de test). À relancer après chaque nouvelle migration
 * qui touche une clé ; tests/Database/SchemaKeysTest.php échoue tant que
 * le fichier n'est pas à jour.
 *
 * Usage : php database/migrate.php --env=testing && php bin/dump_schema_keys.php --env=testing
 */

use App\Core\Config;
use App\Core\Env;
use App\Services\SchemaKeys;

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

$keys = SchemaKeys::read();
file_put_contents(SchemaKeys::MANIFEST, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo count($keys) . " tables écrites dans database/schema_keys.json\n";
