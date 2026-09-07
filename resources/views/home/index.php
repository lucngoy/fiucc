<?php
/**
 * Page d'accueil (FIUCC-46, sections 6 à 10 du cahier des charges) et ses
 * statistiques dynamiques (FIUCC-47, section 10) — remplace la page témoin
 * temporaire de FIUCC-17. Style rapproché de la maquette
 * fiucc_portal_home_page/code.html, mais sur les tokens du projet (bg-primary,
 * bg-surface, bg-white en bandes alternées — pas la palette Material isolée
 * de la maquette) et strictement borné aux sections 6-10 : pas de "Community
 * Lifecycle Strip" ni de bandeau CTA de clôture, qui n'existent pas dans le
 * cahier des charges.
 *
 * Toutes les données sont réelles (App\Controllers\HomeController) :
 * - statistiques (FIUCC-47) : Member::registeredStudentsCount(),
 *   Event::organizedCount()/activityCategoriesCount(),
 *   Government::yearsSinceFounding() — jamais une valeur écrite en dur ;
 * - événements et actualités : Event::upcoming()/News::latest(), vides
 *   honnêtement si rien à montrer, pas de contenu inventé ;
 * - "Memories" : Event::recentPhotos() (event_images, seule table de
 *   photos du schéma) — vide aujourd'hui, aucune photo n'a encore été
 *   déposée.
 *
 * Les liens vers /events, /events/{slug}, /news, /news/{slug} et /gallery
 * pointent vers des routes déjà déclarées (routes/web.php) dont les
 * contrôleurs ne sont pas encore construits (FIUCC-48/49 et tickets
 * séparés) : 404 propre en attendant, même principe que partout ailleurs
 * dans ce projet (ex. l'archive Government avant FIUCC-42).
 */
$stats ??= ['students' => 0, 'events' => 0, 'activities' => 0, 'years' => 0];
$upcomingEvents ??= [];
$latestNews ??= [];
$photos ??= [];

$newsCategoryLabel = static function (string $category): string {
    return t('home.news.category.' . $category);
};

$eventDateBadge = static function (string $startDate): string {
    $timestamp = strtotime($startDate);
    $monthsFr = [1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin',
        7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.'];
    $month = \App\Core\Lang::locale() === 'fr' ? $monthsFr[(int) date('n', $timestamp)] : strtoupper(date('M', $timestamp));

    return '<div class="absolute top-3 left-3 bg-gold text-ink rounded-control px-2.5 py-1 text-center shadow-card">'
        . '<span class="block text-label-caps font-label-caps uppercase leading-none">' . e($month) . '</span>'
        . '<span class="block font-headline-sm text-headline-sm leading-tight">' . e(date('d', $timestamp)) . '</span>'
        . '</div>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-24 text-center flex flex-col items-center">
        <h1 class="font-headline-lg text-headline-lg text-white max-w-3xl mb-4"><?= e(t('home.hero.title')) ?></h1>
        <p class="text-body-lg text-white/80 max-w-2xl mb-8"><?= e(t('home.hero.tagline')) ?></p>
        <div class="flex flex-col sm:flex-row items-center gap-4">
            <?= component('button', ['label' => t('community.cta.register'), 'variant' => 'accent', 'href' => '/register']) ?>
            <?= component('button', ['label' => t('home.hero.cta_discover'), 'variant' => 'inverse', 'href' => '#events']) ?>
        </div>
    </div>
</div>

<div class="w-full bg-white">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8">
            <?php
            $statItems = [
                ['icon' => 'fa-solid fa-user-graduate', 'value' => number_format($stats['students']), 'caption' => t('home.stats.students', ['count' => $stats['students']])],
                ['icon' => 'fa-solid fa-calendar-days', 'value' => number_format($stats['events']), 'caption' => t('home.stats.events', ['count' => $stats['events']])],
                ['icon' => 'fa-solid fa-bolt', 'value' => number_format($stats['activities']), 'caption' => t('home.stats.activities', ['count' => $stats['activities']])],
                ['icon' => 'fa-solid fa-cake-candles', 'value' => number_format($stats['years']), 'caption' => t('home.stats.years', ['count' => $stats['years']])],
            ];
            ?>
            <?php foreach ($statItems as $stat): ?>
                <div class="flex flex-col items-center text-center gap-2 p-4 rounded-card bg-surface">
                    <i class="<?= e($stat['icon']) ?> text-primary text-2xl" aria-hidden="true"></i>
                    <span class="font-headline-lg text-headline-lg text-primary"><?= e($stat['value']) ?></span>
                    <span class="text-label-md font-label-md text-muted"><?= e($stat['caption']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="w-full bg-surface">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="max-w-2xl mx-auto text-center">
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-3"><?= e(t('home.about.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-4"><?= e(t('home.about.title')) ?></h2>
            <p class="text-body-md text-muted mb-8"><?= e(t('home.about.body')) ?></p>
            <?= component('button', ['label' => t('home.about.cta'), 'variant' => 'primary', 'href' => '/community']) ?>
        </div>
    </div>
</div>

<div class="w-full bg-white" id="events">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-10">
            <div>
                <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('home.events.eyebrow')) ?></p>
                <h2 class="font-headline-md text-headline-md text-ink"><?= e(t('home.events.title')) ?></h2>
            </div>
            <a href="/events" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline shrink-0">
                <?= e(t('home.events.see_all')) ?>
            </a>
        </div>

        <?php if ($upcomingEvents === []): ?>
            <div class="max-w-xl mx-auto">
                <?= component('alert', ['type' => 'info', 'message' => t('home.events.empty')]) ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="flex flex-col bg-surface rounded-card border border-border overflow-hidden">
                        <div class="relative h-40 w-full bg-primary/10">
                            <?php if (!empty($event['cover_image'])): ?>
                                <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fa-solid fa-calendar-days text-primary/40 text-3xl" aria-hidden="true"></i>
                                </div>
                            <?php endif; ?>
                            <?= $eventDateBadge($event['start_date']) ?>
                        </div>
                        <div class="p-5 flex flex-col flex-1">
                            <?php if (!empty($event['location'])): ?>
                                <p class="flex items-center gap-1.5 text-label-md font-label-md text-muted mb-2">
                                    <i class="fa-solid fa-location-dot text-primary" aria-hidden="true"></i>
                                    <?= e($event['location']) ?>
                                </p>
                            <?php endif; ?>
                            <h3 class="font-headline-sm text-headline-sm text-ink mb-2"><?= e($event['title']) ?></h3>
                            <?php if (!empty($event['description'])): ?>
                                <p class="text-body-sm text-muted mb-4 flex-1"><?= e(mb_strimwidth($event['description'], 0, 120, '…')) ?></p>
                            <?php endif; ?>
                            <a href="<?= e('/events/' . $event['slug']) ?>" class="inline-flex items-center gap-1.5 text-primary font-label-lg text-label-lg font-semibold hover:underline mt-auto">
                                <?= e(t('home.events.view')) ?>
                                <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="w-full bg-surface">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-10">
            <div>
                <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('home.news.eyebrow')) ?></p>
                <h2 class="font-headline-md text-headline-md text-ink"><?= e(t('home.news.title')) ?></h2>
            </div>
            <a href="/news" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline shrink-0">
                <?= e(t('home.news.see_all')) ?>
            </a>
        </div>

        <?php if ($latestNews === []): ?>
            <div class="max-w-xl mx-auto">
                <?= component('alert', ['type' => 'info', 'message' => t('home.news.empty')]) ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($latestNews as $news): ?>
                    <a href="<?= e('/news/' . $news['slug']) ?>" class="flex flex-col bg-white rounded-card border border-border overflow-hidden hover:shadow-card transition-shadow">
                        <div class="h-32 w-full bg-primary/10 flex items-center justify-center">
                            <?php if (!empty($news['cover_image'])): ?>
                                <img src="<?= e('/uploads/' . $news['cover_image']) ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-newspaper text-primary/40 text-2xl" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <span class="inline-flex items-center h-5 px-2 mb-2 rounded-full bg-primary/15 text-primary text-label-caps font-label-caps uppercase self-start">
                                <?= e($newsCategoryLabel($news['category'])) ?>
                            </span>
                            <h3 class="font-label-lg text-label-lg text-ink mb-1"><?= e($news['title']) ?></h3>
                            <p class="text-label-caps font-label-caps text-muted mt-auto"><?= e(local_date($news['published_at'])) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="w-full bg-white">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('home.gallery.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('home.gallery.title')) ?></h2>
            <p class="text-body-md text-muted"><?= e(t('home.gallery.subtitle')) ?></p>
        </div>

        <?php if ($photos === []): ?>
            <div class="max-w-xl mx-auto mb-8">
                <?= component('alert', ['type' => 'info', 'message' => t('home.gallery.empty')]) ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-10">
                <?php foreach ($photos as $photo): ?>
                    <div class="aspect-square rounded-card overflow-hidden bg-surface">
                        <img src="<?= e('/uploads/' . $photo['image_path']) ?>" alt="<?= e($photo['caption'] ?? $photo['event_title']) ?>" class="w-full h-full object-cover">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-center">
            <?= component('button', ['label' => t('home.gallery.cta'), 'variant' => 'secondary', 'href' => '/gallery']) ?>
        </div>
    </div>
</div>
