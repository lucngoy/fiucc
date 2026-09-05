<?php
declare(strict_types=1);

/**
 * Comptes de test pour les middlewares d'authentification (FIUCC-23/24).
 * Exécuté par database/seed.php, qui fournit $pdo et le garde-fou prod —
 * ce fichier n'a pas son propre bootstrap.
 * Idempotent (ON DUPLICATE KEY UPDATE) : relançable pour débloquer un
 * compte pendant un test.
 */

// Mots de passe en clair volontairement : comptes de test connus de tous,
// jamais valides en production grâce au garde-fou de database/seed.php.
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
