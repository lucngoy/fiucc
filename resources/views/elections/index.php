<?php
/**
 * Liste publique des élections — manquait depuis FIUCC-62/63, voir le
 * commentaire d'App\Controllers\ElectionController::index(). Jamais de
 * 'draft' (Election::publicList()), pas de pagination (peu d'élections
 * réelles pour une seule communauté).
 */
$elections ??= [];

// Fond clair (carte sur bg-surface), pas la bande primaire du bandeau —
// contrairement au badge de resources/views/elections/show.php, qui vit
// lui sur bg-primary et utilise donc bg-white/15.
$statusBadge = static function (string $status): string {
    $variants = [
        'candidacies' => 'bg-gold/20 text-ink',
        'candidacies_closed' => 'bg-gold/20 text-ink',
        'open'        => 'bg-success/[4%] text-success',
        'closed'      => 'bg-muted/[1%] text-muted',
        'published'   => 'bg-primary/10 text-primary',
    ];
    $classes = $variants[$status] ?? 'bg-muted/[1%] text-muted';

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('elections.status.' . $status)) . '</span>';
};
?>
<div class="relative w-full overflow-hidden bg-primary">
    <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <style>
            /* simple SVG animations: floating lines and pulsing dots */
            @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            @keyframes pulse { 0% { r: 2; opacity: 0.9 } 50% { r: 4; opacity: 0.5 } 100% { r: 2; opacity: 0.9 } }
            .float { transform-origin: 400px 450px; animation: floatY 6s ease-in-out infinite; }
            .pulse circle { animation: pulse 2.5s ease-in-out infinite; }
        </style>
        <g class="float" stroke="#3B82F6" stroke-width="1">
            <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
            <path d="M340 20 L420 220 L520 140" />
            <path d="M420 220 L460 320 L280 360 L340 20" />
            <path d="M280 360 L120 60" />
            <path d="M460 320 L620 380 L520 500 L280 360" />
            <path d="M520 500 L560 560 L400 620 L280 360" />
            <path d="M560 560 L700 620 L620 760 L400 620" />
            <path d="M620 760 L640 800 L480 860 L400 620" />
        </g>
        <g class="pulse" fill="#60A5FA">
            <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
            <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
            <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
            <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
            <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
            <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
            <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
        </g>
    </svg>
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('elections.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('elections.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('elections.subtitle')) ?></p>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">

    <?php if ($elections === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('elections.empty')]) ?>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($elections as $election): ?>
                <a href="<?= e('/elections/' . (int) $election['id']) ?>"
                   class="bg-white rounded-card border border-border p-6 flex flex-col gap-3 hover:shadow-card transition-shadow">
                    <div>
                        <?= $statusBadge($election['status']) ?>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($election['title']) ?></h2>
                    <?php if (!empty($election['description'])): ?>
                        <p class="text-body-sm text-muted line-clamp-2"><?= e($election['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 text-body-sm text-muted mt-auto pt-2 border-t border-border">
                        <?= icon('calendar-day', 'text-primary') ?>
                        <span><?= e(local_date($election['start_at'])) ?> – <?= e(local_date($election['end_at'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>
