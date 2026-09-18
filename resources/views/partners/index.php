<?php
/**
 * Espace partenaires, côté public (FIUCC-90, cahier — "espace
 * partenaires : anciens, entreprises, sponsors d'événements"). Chaque
 * ligne vient déjà rédigée de Partner::publicList() (status = 'published'
 * filtre l'inclusion elle-même) — cette vue n'a rien à vérifier,
 * seulement à afficher ce qu'elle reçoit, même discipline que
 * directory/index.php (FIUCC-82).
 */
$partners ??= [];
$filters ??= ['type' => ''];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

$types = ['alumni', 'company', 'sponsor'];
$activeType = $filters['type'];

$paginationQuery = array_filter(['type' => $filters['type']], static fn ($v) => $v !== '');
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
            <span class="text-white"><?= e(t('partners.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('partners.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('partners.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <div class="flex flex-wrap items-center gap-2 mb-10">
        <a href="/partners" class="h-9 px-4 rounded-full text-label-lg font-label-lg transition-colors flex items-center <?= $activeType === '' ? 'bg-primary text-white' : 'bg-surface text-ink hover:bg-primary/10' ?>">
            <?= e(t('partners.filter.all')) ?>
        </a>
        <?php foreach ($types as $typeOption): ?>
            <a href="<?= e('/partners?type=' . $typeOption) ?>" class="h-9 px-4 rounded-full text-label-lg font-label-lg transition-colors flex items-center <?= $activeType === $typeOption ? 'bg-primary text-white' : 'bg-surface text-ink hover:bg-primary/10' ?>">
                <?= e(t('partners.type.' . $typeOption)) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($partners === []): ?>
        <div class="max-w-layout mx-auto">
            <?= component('alert', ['type' => 'info', 'message' => t($filters['type'] !== '' ? 'partners.empty.filtered' : 'partners.empty.default')]) ?>
        </div>
    <?php else: ?>
        <p class="text-body-md text-muted mb-6"><?= e(t('partners.total', ['count' => $total])) ?></p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($partners as $partner): ?>
                <div class="flex flex-col gap-3 bg-surface rounded-card border border-border p-5">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($partner['logo'])): ?>
                            <img src="<?= e('/uploads/' . $partner['logo']) ?>" alt="" class="h-14 w-14 rounded-control object-contain border border-border bg-white p-1.5 shrink-0">
                        <?php else: ?>
                            <span class="h-14 w-14 rounded-control bg-primary/[4%] text-primary flex items-center justify-center shrink-0">
                                <?= icon('building-columns', 'text-xl') ?>
                            </span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <h3 class="font-headline-sm text-headline-sm text-ink truncate"><?= e($partner['name']) ?></h3>
                            <p class="text-label-caps font-label-caps text-primary uppercase"><?= e(t('partners.type.' . $partner['type'])) ?></p>
                        </div>
                    </div>
                    <?php if (!empty($partner['description'])): ?>
                        <p class="text-body-sm text-muted line-clamp-3"><?= e($partner['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($partner['website_url'])): ?>
                        <a href="<?= e($partner['website_url']) ?>" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 text-body-sm text-primary hover:underline mt-auto">
                            <?= icon('arrow-up-right-from-square', 'text-xs') ?>
                            <?= e(t('partners.card.visit')) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => '/partners?' . http_build_query($paginationQuery) . '&page={page}',
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
