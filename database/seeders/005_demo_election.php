<?php
declare(strict_types=1);

/**
 * Élection de démonstration (cahier des charges, section 53) : President,
 * Vice President, General Secretary, Treasurer, avec deux candidats
 * fictifs par poste tirés des membres de 002_demo_members.php.
 * Statut 'draft' : aucune infrastructure de vote n'existe encore
 * (phase Election), ce n'est qu'une donnée d'affichage.
 */

$title = 'Congolese Community Government Election 2026';

$existingElection = $pdo->prepare('SELECT id FROM elections WHERE title = :title');
$existingElection->execute(['title' => $title]);
$electionId = (int) $existingElection->fetchColumn();

if ($electionId === 0) {
    $insertElection = $pdo->prepare(
        "INSERT INTO elections (title, description, start_at, end_at, status)
         VALUES (:title, :description, :start_at, :end_at, 'draft')"
    );
    $insertElection->execute([
        'title'       => $title,
        'description' => 'Annual election of the community government — demonstration data.',
        'start_at'    => '2026-09-15 00:00:00',
        'end_at'      => '2026-09-20 23:59:59',
    ]);
    $electionId = (int) $pdo->lastInsertId();
}

echo "OK  election: {$title}\n";

$positions = ['President', 'Vice President', 'General Secretary', 'Treasurer'];
$positionIds = [];

$existingPosStmt = $pdo->prepare('SELECT id FROM election_positions WHERE election_id = :election_id AND name = :name');
$insertPosStmt = $pdo->prepare(
    'INSERT INTO election_positions (election_id, name, max_votes, sort_order)
     VALUES (:election_id, :name, 1, :sort_order)'
);

foreach ($positions as $sortOrder => $name) {
    $existingPosStmt->execute(['election_id' => $electionId, 'name' => $name]);
    $positionId = (int) $existingPosStmt->fetchColumn();

    if ($positionId === 0) {
        $insertPosStmt->execute(['election_id' => $electionId, 'name' => $name, 'sort_order' => $sortOrder]);
        $positionId = (int) $pdo->lastInsertId();
    }

    $positionIds[$name] = $positionId;
    echo "OK  position: {$name}\n";
}

// Deux candidats fictifs par poste, tirés des membres de démonstration.
$candidateNames = [
    'President'         => ['Grace Mbala', 'Joseph Kalonji'],
    'Vice President'    => ['Aline Tshisekedi', 'Patrick Ilunga'],
    'General Secretary' => ['Chantal Mwamba', 'Emmanuel Kabongo'],
    'Treasurer'         => ['Nadine Bakajika', 'David Mukendi'],
];

$memberLookup = $pdo->prepare('SELECT id FROM members WHERE full_name = :name');
$candidateStmt = $pdo->prepare(
    "INSERT INTO candidates (election_position_id, member_id, bio, manifesto, status)
     VALUES (:position_id, :member_id, :bio, :manifesto, 'approved')
     ON DUPLICATE KEY UPDATE bio = VALUES(bio)"
);

foreach ($candidateNames as $position => $names) {
    foreach ($names as $name) {
        $memberLookup->execute(['name' => $name]);
        $memberId = (int) $memberLookup->fetchColumn();

        if ($memberId === 0) {
            echo "SKIP  {$name} : membre introuvable (002_demo_members.php a-t-il été exécuté ?)\n";
            continue;
        }

        $candidateStmt->execute([
            'position_id' => $positionIds[$position],
            'member_id'   => $memberId,
            'bio'         => "{$name} is a demonstration candidate running for {$position}.",
            'manifesto'   => 'Demonstration manifesto — placeholder content for seed data.',
        ]);

        echo "OK  candidate: {$name} -> {$position}\n";
    }
}
