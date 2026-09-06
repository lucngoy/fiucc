<?php
declare(strict_types=1);

/**
 * Gouvernement actuel fictif (cahier des charges, section 12) : un mandat
 * marqué is_current, avec les postes types listés en exemple (President,
 * General Secretary, Vice President, Treasurer, Communication & Media...).
 * display_name/photo/instagram/faculty sont propres à government_members,
 * jamais liés à un compte membre réel ici — member_id reste NULL.
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
    ['role' => 'President', 'faculty' => 'Faculty of Engineering', 'display_name' => 'Jonathan Mukendi', 'instagram' => 'jon.mukendi', 'sort_order' => 1],
    ['role' => 'Vice President', 'faculty' => 'Faculty of Law', 'display_name' => 'Bénie Kanyinda', 'instagram' => 'benie.k', 'sort_order' => 2],
    ['role' => 'General Secretary', 'faculty' => 'Faculty of Economics & Administrative Sciences', 'display_name' => 'Arsène Makasi', 'instagram' => null, 'sort_order' => 3],
    ['role' => 'Treasurer', 'faculty' => 'Faculty of Economics & Administrative Sciences', 'display_name' => 'Christelle Kapinga', 'instagram' => 'christelle.kap', 'sort_order' => 4],
    ['role' => 'Communication & Media', 'faculty' => 'Faculty of Architecture & Fine Arts', 'display_name' => 'Ephraïm Mutombo', 'instagram' => 'ephraim.mtb', 'sort_order' => 5],
    ['role' => 'Social Affairs', 'faculty' => 'Faculty of Educational Sciences', 'display_name' => 'Naomie Tshilombo', 'instagram' => null, 'sort_order' => 6],
];

$insertStmt = $pdo->prepare(
    'INSERT INTO government_members (government_id, role, faculty, display_name, photo, instagram, sort_order)
     VALUES (:government_id, :role, :faculty, :display_name, NULL, :instagram, :sort_order)'
);
$updateStmt = $pdo->prepare(
    'UPDATE government_members
     SET role = :role, faculty = :faculty, instagram = :instagram, sort_order = :sort_order
     WHERE government_id = :government_id AND display_name = :display_name'
);

foreach ($members as $member) {
    // Pas de contrainte d'unicité naturelle sur government_members : on
    // distingue nous-mêmes création et mise à jour (ré-exécutable sans
    // doublonner, et backfill les champs ajoutés après la première seed).
    $exists = $pdo->prepare(
        'SELECT 1 FROM government_members WHERE government_id = :government_id AND display_name = :display_name LIMIT 1'
    );
    $exists->execute(['government_id' => $governmentId, 'display_name' => $member['display_name']]);

    $params = [
        'government_id' => $governmentId,
        'role'          => $member['role'],
        'faculty'       => $member['faculty'],
        'display_name'  => $member['display_name'],
        'instagram'     => $member['instagram'],
        'sort_order'    => $member['sort_order'],
    ];

    if ($exists->fetchColumn() !== false) {
        $updateStmt->execute($params);
        echo "UPDATE {$member['role']} — {$member['display_name']}\n";
        continue;
    }

    $insertStmt->execute($params);
    echo "OK  {$member['role']} — {$member['display_name']}\n";
}
