<?php
/**
 * Résultats (FIUCC-68, §22 du cahier des charges) — "Results unavailable"
 * tant que status != 'published', puis décompte par poste avec pourcentage
 * et barre simple en CSS pur (pas de bibliothèque de graphiques — vanilla
 * uniquement, un pourcentage en largeur suffit ici).
 *
 * $groups reste vide tant que $published est faux — App\Controllers\
 * ElectionController::results() ne lance même pas la requête d'agrégation
 * avant publication.
 */
$election ??= [];
$published ??= false;
$groups ??= [];
$rounds ??= ['first' => null, 'second' => null];

$electionId = (int) $election['id'];
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
            <a href="<?= e('/elections/' . $electionId) ?>" class="hover:text-white transition-colors"><?= e($election['title']) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('elections.results.breadcrumb')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white"><?= e($election['title']) ?></h1>
        <?php if ($published && !empty($election['results_published_at'])): ?>
            <p class="text-body-md text-white/90 mt-2"><?= e(t('elections.results.published_note', ['date' => local_date($election['results_published_at'])])) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="mx-auto flex flex-col gap-8">

        <?php if ($rounds['first'] !== null): ?>
            <p class="flex flex-wrap items-center gap-2 rounded-control bg-primary-light/20 p-4 text-body-md text-primary">
                <?= icon('scale-balanced') ?>
                <?= e(t('elections.rounds.is_second_round')) ?>
                <a href="<?= e('/elections/' . (int) $rounds['first']['id']) ?>" class="font-label-lg text-label-lg underline"><?= e($rounds['first']['title']) ?></a>
            </p>
        <?php endif; ?>
        <?php if ($rounds['second'] !== null): ?>
            <p class="flex flex-wrap items-center gap-2 rounded-control bg-primary-light/20 p-4 text-body-md text-primary">
                <?= icon('scale-balanced') ?>
                <?= e(t('elections.rounds.has_second_round')) ?>
                <a href="<?= e('/elections/' . (int) $rounds['second']['id']) ?>" class="font-label-lg text-label-lg underline"><?= e($rounds['second']['title']) ?></a>
            </p>
        <?php endif; ?>

        <?php if (!$published): ?>
            <div class="bg-white rounded-card border border-border p-8 flex flex-col items-center gap-3 text-center">
                <span class="h-14 w-14 rounded-full bg-muted/[1%] text-muted flex items-center justify-center">
                    <?= icon('lock', 'text-2xl') ?>
                </span>
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('elections.results.unavailable_title')) ?></h2>
                <p class="text-body-md text-muted"><?= e(t('elections.results.unavailable_message')) ?></p>
            </div>
        <?php else: ?>
            <?= component('election_results_body', ['groups' => $groups, 'isSecondRound' => $election['first_round_id'] !== null]) ?>
        <?php endif; ?>

        <a href="<?= e('/elections/' . $electionId) ?>" class="inline-flex items-center gap-2 text-primary font-label-lg font-label-lg hover:underline self-start">
            <?= icon('arrow-left', 'text-xs') ?>
            <?= e(t('elections.results.back_to_election')) ?>
        </a>
    </div>
</div>
</div>
