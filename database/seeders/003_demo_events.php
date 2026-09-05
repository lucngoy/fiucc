<?php
declare(strict_types=1);

/**
 * 5 événements fictifs (cahier des charges, section 53).
 */

$adminLookup = $pdo->query("SELECT id FROM users WHERE email = 'test.admin@final.edu.tr'");
$adminId = (int) $adminLookup->fetchColumn();
$adminId = $adminId > 0 ? $adminId : null;

$events = [
    ['title' => 'Congolese Cultural Day 2026', 'category' => 'culture', 'location' => 'Grand Auditorium, FIU Campus', 'start_date' => '2026-10-12 12:00:00', 'end_date' => '2026-10-12 18:00:00', 'status' => 'published', 'description' => 'An afternoon of traditional music, food and cultural exhibitions celebrating the Congolese heritage of our community.'],
    ['title' => 'Academic Welcome and Orientation', 'category' => 'academic', 'location' => 'Student Center Hall B', 'start_date' => '2026-09-20 10:00:00', 'end_date' => '2026-09-20 13:00:00', 'status' => 'published', 'description' => 'Orientation session for newly arrived Congolese students: course registration, housing and consular procedures.'],
    ['title' => 'Inter-University Soccer Championship', 'category' => 'sports', 'location' => 'FIU Sports Complex', 'start_date' => '2026-11-04 15:00:00', 'end_date' => '2026-11-04 17:00:00', 'status' => 'published', 'description' => 'Annual friendly tournament against neighbouring university associations.'],
    ['title' => 'Mentorship Pairing Evening', 'category' => 'mentorship', 'location' => 'Library Study Hall', 'start_date' => '2026-05-12 17:00:00', 'end_date' => '2026-05-12 19:00:00', 'status' => 'archived', 'description' => 'Senior students and alumni paired with newcomers for the semester.'],
    ['title' => 'General Assembly 2026', 'category' => 'governance', 'location' => 'Room 204, Faculty of Business', 'start_date' => '2026-09-15 18:00:00', 'end_date' => '2026-09-15 20:00:00', 'status' => 'published', 'description' => 'Annual general assembly ahead of the government election: budget review and open floor.'],
];

$stmt = $pdo->prepare(
    'INSERT INTO events (title, slug, description, category, location, start_date, end_date, status, created_by)
     VALUES (:title, :slug, :description, :category, :location, :start_date, :end_date, :status, :created_by)
     ON DUPLICATE KEY UPDATE
        description = VALUES(description),
        status = VALUES(status)'
);

foreach ($events as $event) {
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $event['title']), '-'));

    $stmt->execute([
        'title'       => $event['title'],
        'slug'        => $slug,
        'description' => $event['description'],
        'category'    => $event['category'],
        'location'    => $event['location'],
        'start_date'  => $event['start_date'],
        'end_date'    => $event['end_date'],
        'status'      => $event['status'],
        'created_by'  => $adminId,
    ]);

    echo "OK  {$event['title']}\n";
}
