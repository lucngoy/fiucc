<?php
/**
 * Tableau de bord — admin (FIUCC-23 du cahier des charges). Six chiffres,
 * rien de plus : la section 23 ne demande ni graphique ni flux d'activité,
 * et rien de tel n'est construit ailleurs à réutiliser ici (voir
 * Admin\DashboardController). Chaque carte mène vers l'écran correspondant
 * quand il existe (tout, sauf actualités/élections — mêmes filtres qu'un
 * lien direct depuis la sidebar, pas un raccourci supplémentaire à
 * maintenir séparément).
 */
$stats ??= [
    'registered_members' => 0,
    'pending_members'    => 0,
    'upcoming_events'    => 0,
    'published_news'     => 0,
    'active_elections'   => 0,
    'votes'              => 0,
];

$cards = [
    ['key' => 'registered_members', 'icon' => 'users',                'href' => '/admin/members'],
    ['key' => 'pending_members',    'icon' => 'hourglass-half',       'href' => '/admin/members?status=pending'],
    ['key' => 'upcoming_events',    'icon' => 'calendar-days',        'href' => '/admin/events'],
    ['key' => 'published_news',     'icon' => 'newspaper',            'href' => '/admin/news'],
    ['key' => 'active_elections',   'icon' => 'check-to-slot',        'href' => '/admin/elections'],
    ['key' => 'votes',              'icon' => 'square-poll-vertical', 'href' => null],
];
?>
<div class="flex flex-col gap-6">
    <p class="text-body-md text-muted"><?= e(t('admin.dashboard.subtitle')) ?></p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($cards as $card): ?>
            <?php
            $value = (int) $stats[$card['key']];
            $label = t('admin.dashboard.stats.' . $card['key']);
            $href = $card['href'];
            $tag = $href !== null ? 'a' : 'div';
            ?>
            <<?= $tag ?> <?= $href !== null ? 'href="' . e($href) . '"' : '' ?>
                class="flex items-center gap-4 bg-white rounded-card border border-border shadow-card p-5 <?= $href !== null ? 'hover:border-primary/40 transition-colors' : '' ?>">
                <span class="h-12 w-12 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <?= icon($card['icon'], 'text-xl') ?>
                </span>
                <span class="flex flex-col min-w-0">
                    <span class="font-headline-md text-headline-md text-ink"><?= e(number_format($value)) ?></span>
                    <span class="text-label-md font-label-md text-muted truncate"><?= e($label) ?></span>
                </span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
</div>
