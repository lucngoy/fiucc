<?php
declare(strict_types=1);

/**
 * 10 membres fictifs (cahier des charges, section 53). Chaque membre a son
 * propre compte users (email @final.edu.tr) — mot de passe aléatoire non
 * communiqué, ces comptes ne servent qu'à peupler l'annuaire et les listes,
 * pas à se connecter (voir 001_test_users.php pour les comptes de test).
 */

$members = [
    ['full_name' => 'Grace Mbala', 'student_id' => 'FIU-20231001', 'faculty' => 'Engineering', 'department' => 'Computer Engineering', 'year' => 2, 'city_of_origin' => 'Kinshasa', 'arrival_year' => 2023],
    ['full_name' => 'Joseph Kalonji', 'student_id' => 'FIU-20221002', 'faculty' => 'Business Administration', 'department' => 'International Business', 'year' => 3, 'city_of_origin' => 'Lubumbashi', 'arrival_year' => 2022],
    ['full_name' => 'Aline Tshisekedi', 'student_id' => 'FIU-20241003', 'faculty' => 'Arts and Sciences', 'department' => 'Psychology', 'year' => 1, 'city_of_origin' => 'Goma', 'arrival_year' => 2024],
    ['full_name' => 'Patrick Ilunga', 'student_id' => 'FIU-20211004', 'faculty' => 'Engineering', 'department' => 'Civil Engineering', 'year' => 4, 'city_of_origin' => 'Kinshasa', 'arrival_year' => 2021],
    ['full_name' => 'Chantal Mwamba', 'student_id' => 'FIU-20231005', 'faculty' => 'Communication', 'department' => 'Media and Journalism', 'year' => 2, 'city_of_origin' => 'Kananga', 'arrival_year' => 2023],
    ['full_name' => 'Emmanuel Kabongo', 'student_id' => 'FIU-20221006', 'faculty' => 'Business Administration', 'department' => 'Finance', 'year' => 3, 'city_of_origin' => 'Kinshasa', 'arrival_year' => 2022],
    ['full_name' => 'Nadine Bakajika', 'student_id' => 'FIU-20241007', 'faculty' => 'Tourism and Hotel Management', 'department' => 'Tourism Management', 'year' => 1, 'city_of_origin' => 'Bukavu', 'arrival_year' => 2024],
    ['full_name' => 'David Mukendi', 'student_id' => 'FIU-20201008', 'faculty' => 'Engineering', 'department' => 'Electrical Engineering', 'year' => 5, 'city_of_origin' => 'Mbuji-Mayi', 'arrival_year' => 2020],
    ['full_name' => 'Sarah Ngoyi', 'student_id' => 'FIU-20231009', 'faculty' => 'Arts and Sciences', 'department' => 'English Language and Literature', 'year' => 2, 'city_of_origin' => 'Kinshasa', 'arrival_year' => 2023],
    ['full_name' => 'Christian Lumumba', 'student_id' => 'FIU-20221010', 'faculty' => 'Business Administration', 'department' => 'Marketing', 'year' => 3, 'city_of_origin' => 'Kisangani', 'arrival_year' => 2022],
];

$userStmt = $pdo->prepare(
    "INSERT INTO users (email, password_hash, role)
     VALUES (:email, :password_hash, 'member')
     ON DUPLICATE KEY UPDATE password_hash = password_hash"
);

$lookupUserStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');

$memberStmt = $pdo->prepare(
    "INSERT INTO members (
        user_id, membership_number, full_name, student_id, city_of_origin,
        faculty, department, year, arrival_year, status_type, status, email_verified_at
    ) VALUES (
        :user_id, :membership_number, :full_name, :student_id, :city_of_origin,
        :faculty, :department, :year, :arrival_year, 'student', 'validated', NOW()
    )
    ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        faculty = VALUES(faculty),
        department = VALUES(department)"
);

$number = 1;

foreach ($members as $member) {
    $slug = strtolower(str_replace(' ', '.', $member['full_name']));
    $email = "demo.{$slug}@final.edu.tr";

    $userStmt->execute([
        'email'         => $email,
        'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
    ]);

    $lookupUserStmt->execute(['email' => $email]);
    $userId = (int) $lookupUserStmt->fetchColumn();

    $memberStmt->execute([
        'user_id'           => $userId,
        'membership_number' => sprintf('FIUCC-%04d', $number),
        'full_name'         => $member['full_name'],
        'student_id'        => $member['student_id'],
        'city_of_origin'    => $member['city_of_origin'],
        'faculty'           => $member['faculty'],
        'department'        => $member['department'],
        'year'              => $member['year'],
        'arrival_year'      => $member['arrival_year'],
    ]);

    echo "OK  {$member['full_name']} ({$email})\n";
    $number++;
}
