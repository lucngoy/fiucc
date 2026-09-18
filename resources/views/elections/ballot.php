<?php
/**
 * Bulletin de vote multi-postes (FIUCC-66, §21 du cahier des charges) —
 * une seule page, tous les postes, un seul formulaire. Voir le commentaire
 * de classe d'App\Controllers\VoteController pour le raisonnement complet
 * (pourquoi pas un assistant pas-à-pas, où va l'état entre les écrans).
 *
 * "Vote blanc" est une option explicite du groupe de boutons radio de
 * chaque poste, jamais l'absence de sélection — impossible de soumettre un
 * poste sans réponse (required + revalidation serveur dans
 * VoteController::collectSelections()) : un poste non répondu est un
 * blocage, jamais une conversion silencieuse en vote blanc.
 */
$election ??= [];
$groups ??= [];
$errors ??= [];
$closingWarning ??= null;

$electionId = (int) $election['id'];
$globalError = $errors['_global'] ?? null;
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
            <span class="text-white"><?= e(t('elections.ballot.breadcrumb')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white"><?= e(t('elections.ballot.title')) ?></h1>
        <p class="text-body-md text-white/90 mt-2 max-w-2xl"><?= e(t('elections.ballot.intro')) ?></p>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="max-w-layout mx-auto flex flex-col gap-8">

        <?php if ($globalError !== null): ?>
            <?= component('alert', ['type' => 'error', 'message' => $globalError]) ?>
        <?php endif; ?>

        <?php if ($closingWarning !== null): ?>
            <?= component('alert', ['type' => 'warning', 'message' => $closingWarning]) ?>
        <?php endif; ?>

        <form method="POST" action="<?= e('/elections/' . $electionId . '/vote') ?>" class="flex flex-col gap-8">
            <?= csrf_field() ?>

            <?php foreach ($groups as $positionId => $group): ?>
                <fieldset class="bg-white rounded-card border border-border p-6 flex flex-col gap-4">
                    <legend class="font-headline-sm text-headline-sm text-ink px-1"><?= e($group['position']['name']) ?></legend>
                    <?php if (isset($errors[$positionId])): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($errors[$positionId]) ?></p>
                    <?php endif; ?>

                    <div class="flex flex-col gap-2">
                        <?php foreach ($group['candidates'] as $candidate): ?>
                            <label class="flex items-center gap-3 p-3 rounded-control border border-border hover:bg-white cursor-pointer transition-colors">
                                <input type="radio" name="position_<?= e((string) $positionId) ?>" value="<?= e((string) $candidate['id']) ?>" required
                                    class="h-4 w-4 text-primary focus:ring-primary-light/50">
                                <span class="text-body-md text-ink"><?= e($candidate['full_name']) ?></span>
                            </label>
                        <?php endforeach; ?>

                        <label class="flex items-center gap-3 p-3 rounded-control border border-border hover:bg-white cursor-pointer transition-colors">
                            <input type="radio" name="position_<?= e((string) $positionId) ?>" value="blank" required
                                class="h-4 w-4 text-primary focus:ring-primary-light/50">
                            <span class="text-body-md text-muted"><?= e(t('elections.ballot.blank_vote')) ?></span>
                        </label>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <button type="submit" class="h-12 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors">
                <?= e(t('elections.ballot.review_cta')) ?>
            </button>
        </form>
    </div>
</div>
</div>
