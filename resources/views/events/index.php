<?php
/**
 * Liste des événements (FIUCC-48, sections 26/27 du cahier des charges) —
 * maquette fiucc_portal_events_gathering_details/code.html (section A),
 * sur les tokens du projet.
 *
 * Beaucoup d'éléments décoratifs de la maquette n'ont aucune donnée réelle
 * derrière (compte à rebours JS, pile d'avatars, "248 confirmed" inventé,
 * bascule calendrier) — volontairement absents ici. Ce qui reste vient
 * tout de App\Models\Event : la bascule à venir/passé se recalcule sur les
 * dates à chaque chargement (Event::whenClause(), jamais un statut figé),
 * les compteurs d'inscription viennent de event_registrations (à 0 partout
 * tant que FIUCC-50 n'écrit rien dedans — affiché tel quel, pas masqué).
 *
 * États vides : aucun événement en base à part le seeder de démonstration
 * au lancement — soignés plutôt que génériques (t('events.empty.upcoming')
 * et t('events.empty.past') sont volontairement rassurants/différents,
 * pas juste "no results").
 */
$when ??= 'upcoming';
$filters ??= ['search' => '', 'category' => ''];
$events ??= [];
$featured ??= null;
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;
$upcomingCount ??= 0;
$pastCount ??= 0;
$categories ??= [];

$tabUrl = static function (string $targetWhen) use ($filters): string {
    $query = array_filter([
        'search'   => $filters['search'],
        'category' => $filters['category'],
    ], static fn ($value) => $value !== '');
    $query['when'] = $targetWhen;

    return '/events?' . http_build_query($query);
};

$statusBadge = static function (string $state): string {
    $variants = [
        'upcoming'  => 'bg-primary/15 text-primary',
        'ongoing'   => 'bg-success/15 text-success',
        'completed' => 'bg-muted/15 text-muted',
        'cancelled' => 'bg-danger/10 text-danger',
    ];
    $classes = $variants[$state] ?? $variants['upcoming'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md ' . $classes . '">'
        . e(t('events.status.' . $state)) . '</span>';
};

$imagePlaceholder = static function (string $iconSize = 'text-3xl'): string {
    return '<div class="absolute inset-0 bg-gradient-to-br from-primary/15 to-primary/5 flex items-center justify-center">'
        . '<i class="fa-solid fa-calendar-days ' . e($iconSize) . ' text-primary/30" aria-hidden="true"></i>'
        . '</div>';
};

$dateBadge = static function (string $startDate): string {
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
 * "N registered" + "N spots left"/"Full" — jamais affiché si capacity est
 * NULL (aucune limite déclarée par l'admin, rien à comparer).
 */
$registrationLine = static function (array $event, int $registered): string {
    $parts = '<span>' . e(t('events.card.registered', ['count' => $registered])) . '</span>';

    if ($event['capacity'] !== null) {
        $capacity = (int) $event['capacity'];
        $parts .= $registered >= $capacity
            ? '<span class="text-danger font-semibold">' . e(t('events.card.full')) . '</span>'
            : '<span>' . e(t('events.card.spots_left', ['count' => $capacity - $registered])) . '</span>';
    }

    return $parts;
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/70 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('events.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('events.title')) ?></h1>
        <p class="text-body-lg text-white/80 max-w-2xl"><?= e(t('events.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-10">
        <div class="inline-flex p-1 bg-surface rounded-control shadow-inner text-label-md font-label-md self-start">
            <a href="<?= e($tabUrl('upcoming')) ?>"
               class="px-4 py-2 rounded-control transition-colors <?= $when === 'upcoming' ? 'bg-primary text-white font-semibold shadow-card' : 'text-muted hover:text-ink' ?>">
                <?= e(t('events.tab.upcoming', ['count' => $upcomingCount])) ?>
            </a>
            <a href="<?= e($tabUrl('past')) ?>"
               class="px-4 py-2 rounded-control transition-colors <?= $when === 'past' ? 'bg-primary text-white font-semibold shadow-card' : 'text-muted hover:text-ink' ?>">
                <?= e(t('events.tab.past', ['count' => $pastCount])) ?>
            </a>
        </div>

        <form method="GET" action="/events" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input type="hidden" name="when" value="<?= e($when) ?>">
            <label for="event-search" class="sr-only"><?= e(t('events.search_label')) ?></label>
            <input type="search" id="event-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('events.search_placeholder')) ?>"
                class="h-10 min-w-[240px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
            <?php if ($categories !== []): ?>
                <label for="event-category" class="sr-only"><?= e(t('events.filter.category_all')) ?></label>
                <select id="event-category" name="category" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                    <option value=""><?= e(t('events.filter.category_all')) ?></option>
                    <?php foreach ($categories as $categoryOption): ?>
                        <option value="<?= e($categoryOption) ?>" <?= $filters['category'] === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('events.filter.apply')) ?></button>
            <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
                <a href="<?= e($tabUrl($when)) ?>" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('events.filter.reset')) ?></a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($featured !== null): ?>
        <?php
        $featuredRegistered = $featured['registered_count'];
        ?>
        <a href="<?= e('/events/' . $featured['slug']) ?>" class="group block bg-surface rounded-card border border-border overflow-hidden mb-10 grid grid-cols-1 lg:grid-cols-12 hover:shadow-card transition-shadow">
            <div class="relative min-h-[260px] lg:min-h-[360px] lg:col-span-5 overflow-hidden bg-white">
                <?php if (!empty($featured['cover_image'])): ?>
                    <img src="<?= e('/uploads/' . $featured['cover_image']) ?>" alt=""
                         class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <?php else: ?>
                    <?= $imagePlaceholder('text-6xl') ?>
                <?php endif; ?>
                <span class="absolute top-4 left-4 bg-gold text-ink text-label-caps font-label-caps uppercase px-3 py-1 rounded-full shadow-card">
                    <?= e(t('events.featured.eyebrow')) ?>
                </span>
            </div>
            <div class="lg:col-span-7 p-6 md:p-10 flex flex-col justify-center gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center h-7 px-3 rounded-full bg-primary/10 text-primary text-label-md font-label-md font-semibold"><?= e(local_date($featured['start_date'])) ?></span>
                    <?php if (!empty($featured['category'])): ?>
                        <span class="inline-flex items-center h-7 px-3 rounded-full bg-surface text-muted text-label-md font-label-md"><?= e($featured['category']) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="font-headline-lg text-headline-lg text-ink group-hover:text-primary transition-colors"><?= e($featured['title']) ?></h2>
                <?php if (!empty($featured['location'])): ?>
                    <p class="flex items-center gap-2 text-body-md text-muted">
                        <i class="fa-solid fa-location-dot text-primary" aria-hidden="true"></i>
                        <?= e($featured['location']) ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($featured['description'])): ?>
                    <p class="text-body-md text-muted line-clamp-2"><?= e($featured['description']) ?></p>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-4 text-label-md font-label-md text-muted pt-2">
                    <?= $registrationLine($featured, $featuredRegistered) ?>
                </div>
            </div>
        </a>
    <?php endif; ?>

    <?php if ($events === [] && $featured === null): ?>
        <div class="max-w-xl mx-auto">
            <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('events.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t($when === 'past' ? 'events.empty.past' : 'events.empty.upcoming')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($events as $event): ?>
                <?php
                $state = $event['display_state'];
                $registered = $event['registered_count'];
                $isPast = $state === 'completed';
                ?>
                <a href="<?= e('/events/' . $event['slug']) ?>" class="group flex flex-col bg-white rounded-card border border-border hover:shadow-card transition-shadow overflow-hidden">
                    <div class="relative h-44 w-full overflow-hidden bg-surface">
                        <?php if (!empty($event['cover_image'])): ?>
                            <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt=""
                                 class="w-full h-full object-cover <?= $isPast ? 'grayscale-[60%] opacity-80 group-hover:grayscale-0 group-hover:opacity-100' : 'group-hover:scale-105' ?> transition-all duration-500">
                        <?php else: ?>
                            <?= $imagePlaceholder() ?>
                        <?php endif; ?>
                        <?= $dateBadge($event['start_date']) ?>
                        <?php if (!empty($event['category'])): ?>
                            <div class="absolute top-3 right-3 bg-ink/75 text-white text-label-caps font-label-caps uppercase px-2.5 py-1 rounded-control backdrop-blur-sm">
                                <?= e($event['category']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6 flex flex-col flex-grow">
                        <div class="mb-2"><?= $statusBadge($state) ?></div>
                        <h3 class="font-headline-sm text-headline-sm text-ink mb-2 group-hover:text-primary transition-colors"><?= e($event['title']) ?></h3>
                        <?php if (!empty($event['location'])): ?>
                            <div class="flex items-center gap-1.5 text-muted text-label-md font-label-md mb-2">
                                <i class="fa-solid fa-location-dot text-primary text-xs" aria-hidden="true"></i>
                                <span><?= e($event['location']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($event['description'])): ?>
                            <p class="text-body-sm text-muted line-clamp-2 mb-4 flex-grow"><?= e($event['description']) ?></p>
                        <?php endif; ?>
                        <div class="flex items-center gap-3 text-label-md font-label-md text-muted mt-auto pt-2 border-t border-border">
                            <?= $registrationLine($event, $registered) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $paginationQuery = array_filter(['when' => $when, 'search' => $filters['search'], 'category' => $filters['category']], static fn ($v) => $v !== '');
    ?>
    <div class="mt-10">
        <?= component('pagination', [
            'currentPage' => $currentPage,
            'totalPages'  => $totalPages,
            'baseUrl'     => '/events?' . http_build_query($paginationQuery) . '&page={page}',
        ]) ?>
    </div>
</div>
</div>
