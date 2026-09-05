<?php
declare(strict_types=1);

/**
 * Seeder autonome — comptes de test pour les middlewares d'authentification
 * (FIUCC-23/24). Le système complet de seeders (exécution groupée,
 * commande dédiée) est le périmètre de FIUCC-19 : ce fichier se suffit à
 * lui-même en attendant.
 *
 * Usage : php database/seeders/001_test_users.php
 * Refuse de s'exécuter si APP_ENV=production. Idempotent (ON DUPLICATE KEY
 * UPDATE) : peut être relancé sans risque, y compris pour débloquer un
 * compte verrouillé pendant un test de FIUCC-23.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__, 2);

define('BASE_PATH', $root);

require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
Config::load($root . '/config');

if (Config::get('app.env') === 'production') {
    fwrite(STDERR, "Ce seeder ne peut pas s'exécuter avec APP_ENV=production.\n");
    exit(1);
}

$pdo = Database::connection();

// Mots de passe en clair volontairement : ce sont des comptes de test connus
// de tous, jamais valides en production grâce au garde-fou ci-dessus.
$users = [
    ['email' => 'test.member@final.edu.tr', 'password' => 'Test1234!', 'role' => 'member'],
    ['email' => 'test.admin@final.edu.tr', 'password' => 'Admin1234!', 'role' => 'admin'],
];

$stmt = $pdo->prepare(
    'INSERT INTO users (email, password_hash, role)
     VALUES (:email, :password_hash, :role)
     ON DUPLICATE KEY UPDATE
         password_hash = VALUES(password_hash),
         role = VALUES(role),
         failed_attempts = 0,
         locked_until = NULL'
);

foreach ($users as $user) {
    $stmt->execute([
        'email'         => $user['email'],
        'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
        'role'          => $user['role'],
    ]);

    echo "OK  {$user['email']} ({$user['role']})\n";
}

echo "Comptes de test prêts (mots de passe : voir ce fichier — dev/local uniquement).\n";
