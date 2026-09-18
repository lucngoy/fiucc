<?php
/**
 * Récapitulatif avant validation définitive (FIUCC-66, §21 : "Please
 * review your choices before submitting"). Les choix voyagent jusqu'ici en
 * POST depuis le bulletin, et repartent vers la confirmation finale en
 * champs cachés dans CE formulaire — jamais en session serveur (voir
 * App\Controllers\VoteController, commentaire de classe). Fermer l'onglet
 * ici ne perd rien côté serveur : rien n'y était gardé.
 *
 * Retour : le bouton "précédent" du navigateur restaure normalement la
 * saisie du bulletin (comportement natif) — pas besoin de reconstruire un
 * pré-remplissage explicite pour ça.
 */
$election ??= [];
$rows ??= [];
$closingWarning ??= null;

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
        <h1 class="font-headline-lg text-headline-lg text-white"><?= e(t('elections.ballot.review_title')) ?></h1>
        <p class="text-body-md text-white/90 mt-2 max-w-2xl"><?= e(t('elections.ballot.review_intro')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="mx-auto flex flex-col gap-6">

        <?php if ($closingWarning !== null): ?>
            <?= component('alert', ['type' => 'warning', 'message' => $closingWarning]) ?>
        <?php endif; ?>

        <div class="bg-surface rounded-card border border-border divide-y divide-border overflow-hidden">
            <?php foreach ($rows as $row): ?>
                <div class="flex items-center justify-between gap-4 p-4">
                    <span class="font-label-lg text-label-lg text-ink"><?= e($row['position_name']) ?></span>
                    <span class="text-body-md text-muted"><?= e($row['choice_label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="<?= e('/elections/' . $electionId . '/vote') ?>" class="flex flex-col sm:flex-row gap-3">
            <?= csrf_field() ?>
            <?php foreach ($rows as $positionId => $row): ?>
                <input type="hidden" name="position_<?= e((string) $positionId) ?>" value="<?= e((string) $row['raw_value']) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="confirm" value="1">

            <a href="<?= e('/elections/' . $electionId . '/vote') ?>"
               class="flex-1 inline-flex items-center justify-center h-12 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors">
                <?= e(t('elections.ballot.modify_cta')) ?>
            </a>
            <button type="submit" class="flex-1 h-12 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors">
                <?= e(t('elections.ballot.submit_cta')) ?>
            </button>
        </form>
    </div>
</div>
</div>
