<?php
declare(strict_types=1);

/**
 * Gouvernement actuel fictif (cahier des charges, section 12) : un mandat
 * marqué is_current, avec les postes types listés en exemple (President,
 * General Secretary, Vice President, Treasurer, Communication & Media...).
 * display_name/photo/instagram sont propres à government_members, jamais
 * liés à un compte membre réel ici — member_id reste NULL.
 */

$mandate = '2025-2026';

$govStmt = $pdo->prepare(
    'INSERT INTO governments (mandate, start_date, end_date, is_current)
     VALUES (:mandate, :start_date, :end_date, 1)
     ON DUPLICATE KEY UPDATE is_current = 1'
);
$govStmt->execute([
    'mandate'    => $mandate,
    'start_date' => '2025-09-01',
    'end_date'   => '2026-08-31',
]);

$governmentId = (int) $pdo->query(
    "SELECT id FROM governments WHERE mandate = " . $pdo->quote($mandate)
)->fetchColumn();

// Un seul mandat "courant" à la fois.
$pdo->prepare('UPDATE governments SET is_current = 0 WHERE id != :id')
    ->execute(['id' => $governmentId]);

$members = [
    ['role' => 'President', 'display_name' => 'Jonathan Mukendi', 'instagram' => 'jon.mukendi', 'sort_order' => 1],
    ['role' => 'Vice President', 'display_name' => 'Bénie Kanyinda', 'instagram' => 'benie.k', 'sort_order' => 2],
    ['role' => 'General Secretary', 'display_name' => 'Arsène Makasi', 'instagram' => null, 'sort_order' => 3],
    ['role' => 'Treasurer', 'display_name' => 'Christelle Kapinga', 'instagram' => 'christelle.kap', 'sort_order' => 4],
    ['role' => 'Communication & Media', 'display_name' => 'Ephraïm Mutombo', 'instagram' => 'ephraim.mtb', 'sort_order' => 5],
    ['role' => 'Social Affairs', 'display_name' => 'Naomie Tshilombo', 'instagram' => null, 'sort_order' => 6],
];

$memberStmt = $pdo->prepare(
    'INSERT INTO government_members (government_id, role, display_name, photo, instagram, sort_order)
     VALUES (:government_id, :role, :display_name, NULL, :instagram, :sort_order)
     ON DUPLICATE KEY UPDATE role = VALUES(role), sort_order = VALUES(sort_order)'
);

foreach ($members as $member) {
    // Pas de contrainte d'unicité naturelle sur government_members : on évite
    // les doublons à chaque ré-exécution du seeder en vérifiant nous-mêmes.
    $exists = $pdo->prepare(
        'SELECT 1 FROM government_members WHERE government_id = :government_id AND display_name = :display_name LIMIT 1'
    );
    $exists->execute(['government_id' => $governmentId, 'display_name' => $member['display_name']]);

    if ($exists->fetchColumn() !== false) {
        echo "SKIP {$member['display_name']} (déjà présent)\n";
        continue;
    }

    $memberStmt->execute([
        'government_id' => $governmentId,
        'role'          => $member['role'],
        'display_name'  => $member['display_name'],
        'instagram'     => $member['instagram'],
        'sort_order'    => $member['sort_order'],
    ]);

    echo "OK  {$member['role']} — {$member['display_name']}\n";
}
