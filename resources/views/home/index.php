<?php
/**
 * Page d'accueil (FIUCC-46, sections 6 à 10 du cahier des charges) et ses
 * statistiques dynamiques (FIUCC-47, section 10) — remplace la page témoin
 * temporaire de FIUCC-17. Structure rapprochée de la maquette
 * fiucc_portal_home_page/code.html (hero, bande stats, à propos, événements,
 * parcours du membre, actualités, souvenirs, bandeau d'inscription), sur les
 * tokens du projet (bg-primary/bg-surface/bg-white en bandes alternées —
 * pas la palette Material isolée de la maquette, Font Awesome pas Material
 * Symbols).
 *
 * "The FIUCC Member Journey" (section maquette absente du cahier §6-10) :
 * pas un texte générique repris tel quel — adapté au cycle de vie du membre
 * réellement décrit en tête de CLAUDE.md (arrivée → découverte → recensement
 * → événements → élections → alumni/mentor), avec les vraies routes du site
 * quand elles existent.
 *
 * Toutes les données dynamiques restent réelles (App\Controllers\HomeController) :
 * - statistiques (FIUCC-47) : Member::registeredStudentsCount(),
 *   Event::organizedCount()/activityCategoriesCount(),
 *   Government::yearsSinceFounding() — jamais une valeur écrite en dur,
 *   y compris dans la rangée compacte du hero et le bandeau d'inscription
 *   final, qui réutilisent les mêmes chiffres plutôt que d'en inventer
 *   d'autres comme la maquette ("450+") ;
 * - événements et actualités : Event::upcoming()/News::latest(), vides
 *   honnêtement si rien à montrer, pas de contenu inventé ;
 * - "Memories" : Event::recentPhotos() (album_photos, via l'album lié à un
 *   événement — migration 007, décision CLAUDE.md) — vide aujourd'hui,
 *   aucune photo n'a encore été déposée.
 *
 * Photo "à propos" : même principe que /community (FIUCC-40) — espace
 * réservé tant que public/assets/images/home/about.jpg n'existe pas,
 * bascule automatique dès qu'il est déposé, sans retoucher le code.
 *
 * Les liens vers /events, /events/{slug}, /news, /news/{slug}, /gallery,
 * /new-at-final, /elections et /alumni pointent vers des routes déjà
 * déclarées (routes/web.php) dont les contrôleurs ne sont pas encore
 * construits (FIUCC-48/49 et tickets séparés) : 404 propre en attendant,
 * même principe que partout ailleurs dans ce projet (ex. l'archive
 * Government avant FIUCC-42).
 */
$stats ??= ['students' => 0, 'events' => 0, 'activities' => 0, 'years' => 0];
$upcomingEvents ??= [];
$latestNews ??= [];
$photos ??= [];

$newsCategoryLabel = static function (string $category): string {
    return t('home.news.category.' . $category);
};

$aboutPhotoCandidates = ['assets/images/home/about.jpg', 'assets/images/home/about.jpeg', 'assets/images/home/about.png', 'assets/images/home/about.heic'];
$aboutPhotoPath = null;
foreach ($aboutPhotoCandidates as $candidate) {
    if (is_file(BASE_PATH . '/public/' . $candidate)) {
        $aboutPhotoPath = $candidate;
        break;
    }
}
$hasAboutPhoto = $aboutPhotoPath !== null;

$journeySteps = [
    ['icon' => 'fa-solid fa-compass', 'label' => t('home.journey.step1_label'), 'title' => t('home.journey.step1_title'), 'body' => t('home.journey.step1_body')],
    ['icon' => 'fa-solid fa-user-plus', 'label' => t('home.journey.step2_label'), 'title' => t('home.journey.step2_title'), 'body' => t('home.journey.step2_body')],
    ['icon' => 'fa-solid fa-people-group', 'label' => t('home.journey.step3_label'), 'title' => t('home.journey.step3_title'), 'body' => t('home.journey.step3_body')],
    ['icon' => 'fa-solid fa-square-check', 'label' => t('home.journey.step4_label'), 'title' => t('home.journey.step4_title'), 'body' => t('home.journey.step4_body')],
    ['icon' => 'fa-solid fa-hands-holding-circle', 'label' => t('home.journey.step5_label'), 'title' => t('home.journey.step5_title'), 'body' => t('home.journey.step5_body')],
];

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

/**
 * Image par défaut des cartes (événement, actualité) quand cover_image est
 * vide — une icône, pas le sceau de la communauté (pas un logo à cette
 * échelle répétée sur autant de cartes). "absolute inset-0" plutôt que
 * "w-full h-full" : rattaché au bord du conteneur positionné (relative)
 * plutôt qu'à la hauteur du contenu, la case occupe systématiquement tout
 * l'espace réservé à l'image — jamais la hauteur de l'icône elle-même.
 * Chaque appelant doit donc être "relative" (déjà le cas partout où c'est
 * utilisé ci-dessous).
 */
$imagePlaceholder = static function (string $iconClass, string $iconSize = 'text-3xl'): string {
    return '<div class="absolute inset-0 bg-gradient-to-br from-primary/15 to-primary/5 flex items-center justify-center">'
        . '<i class="' . e($iconClass) . ' ' . e($iconSize) . ' text-primary/30" aria-hidden="true"></i>'
        . '</div>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-24 text-center flex flex-col items-center">
        <span class="inline-flex items-center gap-2 h-8 px-4 rounded-full bg-gold text-ink text-label-md font-label-md mb-6">
            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
            <?= e(t('home.hero.badge')) ?>
        </span>
        <h1 class="font-headline-lg text-headline-lg text-white max-w-3xl mb-4"><?= e(t('home.hero.title')) ?></h1>
        <p class="text-body-lg text-white/80 max-w-2xl mb-8"><?= e(t('home.hero.tagline')) ?></p>
        <div class="flex flex-row sm:flex-row items-center gap-4 mb-10">
            <?= component('button', ['label' => t('community.cta.register'), 'variant' => 'accent', 'href' => '/register']) ?>
            <?= component('button', ['label' => t('home.hero.cta_discover'), 'variant' => 'inverse', 'href' => '#events']) ?>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-white/80 text-label-md font-label-md pt-6">
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-gold" aria-hidden="true"></span>
                <?= e(t('home.stats.students', ['count' => $stats['students']])) ?>
            </span>
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-light" aria-hidden="true"></span>
                <?= e(t('home.stats.events', ['count' => $stats['events']])) ?>
            </span>
            <span class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-gold" aria-hidden="true"></span>
                <?= e(t('home.stats.years', ['count' => $stats['years']])) ?>
            </span>
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
                <div class="flex flex-col border border-border items-center text-center gap-2 p-4 rounded-card bg-surface">
                    <i class="<?= e($stat['icon']) ?> text-primary text-2xl" aria-hidden="true"></i>
                    <span class="font-headline-lg text-headline-lg text-primary"><?= e($stat['value']) ?></span>
                    <span class="text-label-md font-label-md text-muted"><?= e($stat['caption']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="w-full bg-surface" id="about">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
            <?php if ($hasAboutPhoto): ?>
                <img src="<?= e('/' . $aboutPhotoPath) ?>" alt="<?= e(t('home.about.title')) ?>"
                     class="w-full h-72 md:h-96 object-cover rounded-card border border-border shadow-card">
            <?php else: ?>
                <div class="w-full h-72 md:h-96 rounded-card border border-dashed border-border bg-white flex items-center justify-center">
                    <span class="text-body-sm text-muted"><?= e(t('home.about.photo_pending')) ?></span>
                </div>
            <?php endif; ?>
            <div>
                <p class="inline-flex items-center gap-2 font-label-caps text-label-caps uppercase text-primary tracking-wider mb-4">
                    <i class="fa-solid fa-flag text-xs" aria-hidden="true"></i>
                    <?= e(t('home.about.eyebrow')) ?>
                </p>
                <h2 class="font-headline-md text-headline-md text-ink mb-5"><?= e(t('home.about.title')) ?></h2>
                <p class="text-body-md text-muted leading-relaxed mb-4"><?= e(t('home.about.body_1')) ?></p>
                <p class="text-body-md text-muted leading-relaxed mb-6"><?= e(t('home.about.body_2')) ?></p>
                <div class="flex flex-wrap items-center gap-6">
                    <a href="/community" class="group inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline">
                        <span><?= e(t('home.about.cta')) ?></span>
                        <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition-transform" aria-hidden="true"></i>
                    </a>
                    <span class="text-border" aria-hidden="true">•</span>
                    <a href="/community/government" class="inline-flex items-center gap-2 text-muted hover:text-ink font-label-lg text-label-lg transition-colors">
                        <i class="fa-solid fa-gavel text-xs" aria-hidden="true"></i>
                        <span><?= e(t('home.about.constitution')) ?></span>
                    </a>
                </div>
            </div>
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
            <?= component('alert', ['type' => 'info', 'message' => t('home.events.empty')]) ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="group flex flex-col bg-white rounded-card border border-border hover:shadow-card transition-shadow overflow-hidden relative">
                        <div class="relative h-44 w-full overflow-hidden bg-surface">
                            <?php if (!empty($event['cover_image'])): ?>
                                <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt=""
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <?= $imagePlaceholder('fa-solid fa-calendar-days', 'text-4xl') ?>
                            <?php endif; ?>
                            <?= $eventDateBadge($event['start_date']) ?>
                            <?php if (!empty($event['category'])): ?>
                                <div class="absolute top-3 right-3 bg-ink/75 text-white text-label-caps font-label-caps uppercase px-2.5 py-1 rounded-control backdrop-blur-sm">
                                    <?= e($event['category']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6 flex flex-col flex-grow">
                            <?php if (!empty($event['location'])): ?>
                                <div class="flex items-center gap-1.5 text-muted text-label-md font-label-md mb-2">
                                    <i class="fa-solid fa-location-dot text-primary text-xs" aria-hidden="true"></i>
                                    <span><?= e($event['location']) ?></span>
                                </div>
                            <?php endif; ?>
                            <h3 class="font-headline-sm text-headline-sm text-ink mb-2 group-hover:text-primary transition-colors">
                                <?= e($event['title']) ?>
                            </h3>
                            <?php if (!empty($event['description'])): ?>
                                <p class="text-body-sm text-muted line-clamp-2 mb-5 flex-grow"><?= e($event['description']) ?></p>
                            <?php endif; ?>
                            <a href="<?= e('/events/' . $event['slug']) ?>" class="inline-flex items-center gap-1 text-primary font-label-lg text-label-lg font-semibold group-hover:underline mt-auto">
                                <span><?= e(t('home.events.view')) ?></span>
                                <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="w-full bg-primary/5">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('home.journey.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('home.journey.title')) ?></h2>
            <p class="text-body-md text-muted"><?= e(t('home.journey.subtitle')) ?></p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
            <?php foreach ($journeySteps as $step): ?>
                <div class="flex flex-col items-center text-center p-5 rounded-card bg-white border border-border">
                    <span class="h-12 w-12 rounded-full bg-primary/15 text-primary flex items-center justify-center mb-3">
                        <i class="<?= e($step['icon']) ?> text-lg" aria-hidden="true"></i>
                    </span>
                    <p class="text-label-caps font-label-caps uppercase text-primary mb-1"><?= e($step['label']) ?></p>
                    <h3 class="font-headline-sm text-headline-sm text-ink mb-1"><?= e($step['title']) ?></h3>
                    <p class="text-body-sm text-muted"><?= e($step['body']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
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
            <?= component('alert', ['type' => 'info', 'message' => t('home.news.empty')]) ?>
        <?php else: ?>
            <?php
            // Article vedette (le plus récent) + liste compacte pour les
            // suivants — même répartition que la maquette (7/5 colonnes),
            // pas une grille uniforme : le dernier communiqué mérite plus
            // de place que les autres.
            $featuredNews = $latestNews[0];
            $compactNews = array_slice($latestNews, 1);
            ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <a href="<?= e('/news/' . $featuredNews['slug']) ?>" class="group lg:col-span-7 flex flex-col bg-white rounded-card border border-border hover:shadow-card transition-shadow p-6">
                    <div class="relative aspect-video w-full rounded-control overflow-hidden mb-5">
                        <?php if (!empty($featuredNews['cover_image'])): ?>
                            <img src="<?= e('/uploads/' . $featuredNews['cover_image']) ?>" alt=""
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <?= $imagePlaceholder('fa-solid fa-newspaper', 'text-5xl') ?>
                        <?php endif; ?>
                        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-control bg-primary text-white text-label-caps font-label-caps uppercase tracking-wider">
                            <?= e($newsCategoryLabel($featuredNews['category'])) ?>
                        </span>
                    </div>
                    <p class="text-label-md font-label-md text-muted mb-2"><?= e(local_date($featuredNews['published_at'])) ?></p>
                    <h3 class="font-headline-md text-headline-md text-ink mb-3 group-hover:text-primary transition-colors">
                        <?= e($featuredNews['title']) ?>
                    </h3>
                    <?php if (!empty($featuredNews['excerpt'])): ?>
                        <p class="text-body-md text-muted leading-relaxed"><?= e($featuredNews['excerpt']) ?></p>
                    <?php endif; ?>
                </a>
                <div class="lg:col-span-5 flex flex-col gap-4">
                    <?php foreach ($compactNews as $news): ?>
                        <a href="<?= e('/news/' . $news['slug']) ?>" class="group p-4 rounded-card bg-white border border-border hover:shadow-card transition-shadow flex gap-4 items-start">
                            <div class="relative h-20 w-20 rounded-control overflow-hidden shrink-0">
                                <?php if (!empty($news['cover_image'])): ?>
                                    <img src="<?= e('/uploads/' . $news['cover_image']) ?>" alt=""
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                <?php else: ?>
                                    <?= $imagePlaceholder('fa-solid fa-newspaper', 'text-xl') ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-label-caps font-label-caps uppercase text-primary font-bold mb-1"><?= e($newsCategoryLabel($news['category'])) ?></span>
                                <h4 class="font-headline-sm text-headline-sm text-ink group-hover:text-primary transition-colors line-clamp-2 mb-1">
                                    <?= e($news['title']) ?>
                                </h4>
                                <span class="text-muted text-label-md font-label-md"><?= e(local_date($news['published_at'])) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
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
            <div class="mb-8">
                <?= component('alert', ['type' => 'info', 'message' => t('home.gallery.empty')]) ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-10">
                <?php foreach ($photos as $photo): ?>
                    <div class="group relative aspect-square rounded-card shadow-card overflow-hidden bg-surface">
                        <img src="<?= e('/uploads/' . $photo['image_path']) ?>" alt="<?= e($photo['caption'] ?? $photo['event_title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute inset-0 bg-primary/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <i class="fa-solid fa-expand text-white text-2xl" aria-hidden="true"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-center">
            <?= component('button', ['label' => t('home.gallery.cta'), 'variant' => 'secondary', 'href' => '/gallery']) ?>
        </div>
    </div>
</div>

<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20 text-center flex flex-col items-center">
        <h2 class="font-headline-md text-headline-md text-white mb-3"><?= e(t('home.cta.title')) ?></h2>
        <p class="text-body-lg text-white/80 max-w-2xl mb-8"><?= e(t('home.cta.body', ['count' => $stats['students']])) ?></p>
        <?= component('button', ['label' => t('community.cta.register'), 'variant' => 'accent', 'href' => '/register']) ?>
    </div>
</div>
