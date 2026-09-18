<?php
/**
 * Saisie du code de vote (FIUCC-64/65) — le code n'arrive jamais dans une
 * URL : ce formulaire vit sur une vraie page, dans la session réelle du
 * membre, avec un jeton CSRF valide (impossible à produire à l'avance dans
 * un email statique — voir la conversation FIUCC-64 pour le raisonnement
 * complet). Le membre colle ou tape le code reçu par email.
 *
 * Toujours affichée avec le formulaire (jamais un état "déjà authentifié") :
 * App\Controllers\VoteController::authenticate() renvoie directement vers
 * /elections/{id}/vote dès que la session l'est déjà pour cette élection —
 * cette vue n'a donc jamais à représenter cet état elle-même.
 */
$election ??= [];
$error ??= '';

$electionId = (int) $election['id'];

$errorMessages = [
    'no_token'     => t('elections.authenticate.error.no_token'),
    'already_used' => t('elections.authenticate.error.already_used'),
    'locked'       => t('elections.authenticate.error.locked'),
    'expired'      => t('elections.authenticate.error.expired'),
    'invalid'      => t('elections.authenticate.error.invalid'),
];
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
            <span class="text-white"><?= e(t('elections.authenticate.breadcrumb')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white"><?= e(t('elections.authenticate.title')) ?></h1>
    </div>
</div>

<div class="w-full bg-surface">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="mx-auto bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
        <p class="text-body-md text-ink"><?= e(t('elections.authenticate.intro')) ?></p>

        <?php if ($error !== '' && isset($errorMessages[$error])): ?>
            <?= component('alert', ['type' => 'error', 'message' => $errorMessages[$error]]) ?>
        <?php endif; ?>

        <form method="POST" action="<?= e('/elections/' . $electionId . '/authenticate') ?>" class="flex flex-col gap-3">
            <?= csrf_field() ?>
            <div class="flex flex-col gap-1.5">
                <label for="field-code" class="font-label-lg text-label-lg text-ink"><?= e(t('elections.authenticate.code_label')) ?></label>
                <input type="text" id="field-code" name="code" required autocomplete="off" autocapitalize="characters" spellcheck="false"
                    placeholder="<?= e(t('elections.authenticate.code_placeholder')) ?>"
                    class="h-12 w-full rounded-control border border-border px-3 text-body-md font-mono tracking-wider text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                <p class="text-body-sm text-muted"><?= e(t('elections.authenticate.code_help')) ?></p>
            </div>
            <button type="submit" class="h-11 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors">
                <?= e(t('elections.authenticate.submit')) ?>
            </button>
        </form>
    </div>
</div>
</div>
