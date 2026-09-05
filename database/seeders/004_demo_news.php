<?php
declare(strict_types=1);

/**
 * 5 articles fictifs (cahier des charges, section 53).
 */

$authorLookup = $pdo->query("SELECT id FROM users WHERE email = 'test.admin@final.edu.tr'");
$authorId = (int) $authorLookup->fetchColumn();
$authorId = $authorId > 0 ? $authorId : null;

$articles = [
    ['title' => 'Registration Opens for the 2026 Community Census', 'category' => 'announcement', 'excerpt' => "All Congolese students at Final International University are invited to register for the annual community census.", 'published_at' => '2026-08-20 09:00:00'],
    ['title' => 'Congolese Cultural Day: Save the Date', 'category' => 'event', 'excerpt' => "This year's Cultural Day will take place on 12 October at the Grand Auditorium.", 'published_at' => '2026-08-25 09:00:00'],
    ['title' => 'Government Election 2026: Candidacies Open', 'category' => 'announcement', 'excerpt' => 'Candidacies for President, Vice President, General Secretary and Treasurer are now open.', 'published_at' => '2026-08-28 09:00:00'],
    ['title' => 'Congolese Team Wins Friendly Tournament', 'category' => 'general', 'excerpt' => 'Our football team brought home the cup after a close final against a neighbouring association.', 'published_at' => '2026-05-05 09:00:00'],
    ['title' => 'Mentorship Programme: 15 New Pairs Formed', 'category' => 'general', 'excerpt' => 'Fifteen new students were paired with senior mentors for the semester.', 'published_at' => '2026-05-14 09:00:00'],
];

$stmt = $pdo->prepare(
    'INSERT INTO news (title, slug, excerpt, content, category, author_id, published_at)
     VALUES (:title, :slug, :excerpt, :content, :category, :author_id, :published_at)
     ON DUPLICATE KEY UPDATE excerpt = VALUES(excerpt), published_at = VALUES(published_at)'
);

foreach ($articles as $article) {
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $article['title']), '-'));

    $stmt->execute([
        'title'        => $article['title'],
        'slug'         => $slug,
        'excerpt'      => $article['excerpt'],
        'content'      => $article['excerpt'] . ' (Demonstration content.)',
        'category'     => $article['category'],
        'author_id'    => $authorId,
        'published_at' => $article['published_at'],
    ]);

    echo "OK  {$article['title']}\n";
}
