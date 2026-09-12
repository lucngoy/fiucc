<?php
/**
 * Détail d'un événement (FIUCC-49, section 27 du cahier des charges) —
 * maquette fiucc_portal_events_gathering_details/code.html (section B),
 * largement simplifiée : la maquette invente un compte à rebours JS, un
 * prix ("Free"), un organisateur, une pile d'avatars d'inscrits, un
 * partage social et une carte de campus statique — rien de tout ça n'a de
 * donnée réelle derrière (pas de champ price/organizer, pas de nom
 * d'affichage sur users). Gardé uniquement ce que le schéma porte
 * réellement : titre/description/catégorie/lieu/dates/capacité (events),
 * inscrits (event_registrations, à 0 tant que FIUCC-50 n'écrit rien),
 * photos (album lié via albums.event_id, migration 007 — vide tant qu'aucun
 * album n'est créé et rempli, FIUCC-54).
 *
 * "Program & Timeline" du cahier (§27) : pas de table dédiée pour une liste
 * d'horaires structurée (event_program_items) — décision prise, pas un
 * manque (voir CLAUDE.md, "Documents de référence"). description en texte
 * libre porte le déroulé quand il y en a un, rendu ci-dessous avec les
 * retours à la ligne respectés.
 */
$event ??= [];
$displayState ??= 'upcoming';
$registeredCount ??= 0;
$photos ??= [];
$memberEligibility ??= 'not_signed_in';
$registrationStatus ??= null;
$notice ??= '';

$statusBadgeClasses = [
    'upcoming'  => 'bg-primary/[4%] text-primary',
    'ongoing'   => 'bg-success/[4%] text-success',
    'completed' => 'bg-muted/[1%] text-muted',
    'cancelled' => 'bg-danger/10 text-danger',
][$displayState] ?? 'bg-primary/[4%] text-primary';

$hasCapacity = $event['capacity'] !== null;
$capacity = $hasCapacity ? (int) $event['capacity'] : null;
$isFull = $hasCapacity && $registeredCount >= $capacity;
$isOpenForRegistration = in_array($displayState, ['upcoming', 'ongoing'], true);
$isRegistered = in_array($registrationStatus, ['going', 'attended'], true);
$canRegister = $isOpenForRegistration && !$isFull && !$isRegistered && $memberEligibility === 'eligible';

$noticeMap = [
    'registered'         => ['type' => 'success', 'message' => t('events.detail.notice.registered')],
    'already_registered' => ['type' => 'info',    'message' => t('events.detail.notice.already_registered')],
    'full'               => ['type' => 'warning', 'message' => t('events.detail.notice.full')],
    'not_found'          => ['type' => 'error',   'message' => t('events.detail.notice.not_found')],
    'unregistered'       => ['type' => 'info',    'message' => t('events.detail.notice.unregistered')],
];

$startTimestamp = strtotime((string) $event['start_date']);
$endTimestamp = !empty($event['end_date']) ? strtotime((string) $event['end_date']) : null;
$timeRange = date('H:i', $startTimestamp) . ($endTimestamp !== null ? ' – ' . date('H:i', $endTimestamp) : '');

$mapsUrl = !empty($event['location'])
    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($event['location'])
    : null;
?>
<div class="relative w-full <?= !empty($event['cover_image']) ? 'min-h-[360px] md:min-h-[440px] flex items-end' : 'bg-primary' ?>">
    <?php if (!empty($event['cover_image'])): ?>
        <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-ink/90 via-ink/60 to-ink/20"></div>
    <?php endif; ?>
    <div class="relative z-10 max-w-layout mx-auto px-4 md:px-6 <?= !empty($event['cover_image']) ? 'py-10 md:py-16' : 'pt-8 pb-10 md:pt-10' ?> w-full">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-5">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/events" class="hover:text-white transition-colors"><?= e(t('events.detail.breadcrumb')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e($event['title']) ?></span>
        </nav>
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <span class="inline-flex items-center h-7 px-3 rounded-full text-label-md font-label-md <?= e($statusBadgeClasses) ?>">
                <?= e(t('events.status.' . $displayState)) ?>
            </span>
            <?php if (!empty($event['category'])): ?>
                <span class="inline-flex items-center h-7 px-3 rounded-full bg-ink/25 text-white text-label-md font-label-md"><?= e($event['category']) ?></span>
            <?php endif; ?>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-white max-w-3xl mb-6"><?= e($event['title']) ?></h1>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-2xl">
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur rounded-control p-3">
                <i class="fa-solid fa-calendar-day text-gold text-lg" aria-hidden="true"></i>
                <div class="flex flex-col">
                    <span class="text-label-caps font-label-caps uppercase text-white/60"><?= e(t('events.detail.date')) ?></span>
                    <span class="text-label-md font-label-md text-white font-semibold"><?= e(local_date($event['start_date'])) ?></span>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur rounded-control p-3">
                <i class="fa-solid fa-clock text-gold text-lg" aria-hidden="true"></i>
                <div class="flex flex-col">
                    <span class="text-label-caps font-label-caps uppercase text-white/60"><?= e(t('events.detail.time')) ?></span>
                    <span class="text-label-md font-label-md text-white font-semibold"><?= e($timeRange) ?></span>
                </div>
            </div>
            <?php if (!empty($event['location'])): ?>
                <div class="flex items-center gap-3 bg-white/10 backdrop-blur rounded-control p-3">
                    <i class="fa-solid fa-location-dot text-gold text-lg" aria-hidden="true"></i>
                    <div class="flex flex-col min-w-0">
                        <span class="text-label-caps font-label-caps uppercase text-white/60"><?= e(t('events.detail.venue')) ?></span>
                        <span class="text-label-md font-label-md text-white font-semibold truncate"><?= e($event['location']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <div class="mb-8">
            <?= component('alert', $noticeMap[$notice]) ?>
        </div>
    <?php endif; ?>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8 flex flex-col gap-8">
            <?php if (!empty($event['description'])): ?>
                <article class="bg-surface rounded-card border border-border p-6 md:p-8">
                    <h2 class="font-headline-md text-headline-md text-ink mb-4"><?= e(t('events.detail.about')) ?></h2>
                    <div class="text-body-md text-muted leading-relaxed"><?= nl2br(e($event['description'])) ?></div>
                </article>
            <?php endif; ?>

            <?php if ($mapsUrl !== null): ?>
                <div class="bg-surface rounded-card border border-border p-6 md:p-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="h-11 w-11 rounded-full bg-primary/15 text-primary flex items-center justify-center shrink-0"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                        <div>
                            <p class="font-label-lg text-label-lg text-ink"><?= e(t('events.detail.venue')) ?></p>
                            <p class="text-body-sm text-muted"><?= e($event['location']) ?></p>
                        </div>
                    </div>
                    <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline">
                        <?= e(t('events.detail.map_link')) ?>
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                    </a>
                </div>
            <?php endif; ?>

            <div class="bg-surface rounded-card border border-border p-6 md:p-8">
                <h2 class="font-headline-md text-headline-md text-ink mb-4"><?= e(t('events.detail.photos')) ?></h2>
                <?php if ($photos === []): ?>
                    <?= component('alert', ['type' => 'info', 'message' => t('events.detail.photos_empty')]) ?>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php foreach ($photos as $photo): ?>
                            <?php $thumb = $photo['thumbnail_path'] ?: $photo['image_path']; ?>
                            <a href="<?= e('/uploads/' . $photo['image_path']) ?>" target="_blank" rel="noopener"
                               data-lightbox-trigger data-full="<?= e('/uploads/' . $photo['image_path']) ?>" data-caption="<?= e((string) ($photo['caption'] ?? '')) ?>"
                               class="aspect-square rounded-control overflow-hidden bg-white group relative block cursor-zoom-in">
                                <img src="<?= e('/uploads/' . $thumb) ?>" alt="<?= e($photo['caption'] ?? '') ?>"
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?= component('lightbox') ?>
                    <script src="/assets/js/gallery-lightbox.js" defer></script>
                <?php endif; ?>
            </div>

            <a href="/events" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline self-start">
                <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                <?= e(t('events.detail.back')) ?>
            </a>
        </div>

        <aside class="lg:col-span-4">
            <div class="bg-white rounded-card border border-border p-6 flex flex-col gap-5 lg:sticky lg:top-24">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('events.detail.registration')) ?></h2>
                <div class="flex items-center gap-2 text-body-md text-ink">
                    <i class="fa-solid fa-users text-primary" aria-hidden="true"></i>
                    <span><?= e(t('events.detail.registered', ['count' => $registeredCount])) ?></span>
                </div>
                <?php if ($hasCapacity): ?>
                    <p class="text-body-sm <?= $isFull ? 'text-danger font-semibold' : 'text-muted' ?>">
                        <?= $isFull ? e(t('events.detail.full')) : e(t('events.detail.spots_left', ['count' => $capacity - $registeredCount, 'capacity' => $capacity])) ?>
                    </p>
                <?php endif; ?>

                <?php if ($isRegistered): ?>
                    <p class="flex items-center gap-2 text-success font-label-lg text-label-lg font-semibold">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <?= e(t('events.detail.you_are_registered')) ?>
                    </p>
                    <form method="POST" action="<?= e('/events/' . $event['slug'] . '/unregister') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-full h-11 rounded-control border border-danger text-danger text-label-lg font-label-lg font-semibold hover:bg-danger/10 transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            <?= e(t('events.detail.unregister_cta')) ?>
                        </button>
                    </form>
                <?php elseif ($canRegister): ?>
                    <form method="POST" action="<?= e('/events/' . $event['slug'] . '/register') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-full h-11 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors flex items-center justify-center gap-2">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                            <?= e(t('events.detail.register_cta')) ?>
                        </button>
                    </form>
                <?php elseif (!$isOpenForRegistration): ?>
                    <p class="text-body-sm text-muted"><?= e(t('events.detail.register_closed')) ?></p>
                <?php elseif ($isFull): ?>
                    <!-- Rien de plus à afficher : la ligne "Full" juste au-dessus l'explique déjà. -->
                <?php elseif ($memberEligibility === 'not_validated'): ?>
                    <p class="text-body-sm text-muted"><?= e(t('events.detail.must_be_validated')) ?></p>
                    <a href="/profile" class="inline-flex items-center justify-center h-11 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors"><?= e(t('profile.title')) ?></a>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('events.detail.must_sign_in')) ?></p>
                    <a href="/login" class="inline-flex items-center justify-center h-11 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors"><?= e(t('auth.sign_in')) ?></a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
</div>
