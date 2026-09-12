<?php
/**
 * Saisie du code de vote (FIUCC-64/65) — le code n'arrive jamais dans une
 * URL : ce formulaire vit sur une vraie page, dans la session réelle du
 * membre, avec un jeton CSRF valide (impossible à produire à l'avance dans
 * un email statique — voir la conversation FIUCC-64 pour le raisonnement
 * complet). Le membre colle ou tape le code reçu par email.
 *
 * $authorized : une saisie correcte a déjà eu lieu cette session pour cette
 * élection — pas de bulletin vers lequel enchaîner pour l'instant
 * (FIUCC-66+, pas construit), donc simple confirmation plutôt qu'un lien
 * vers une page qui n'existe pas.
 */
$election ??= [];
$error ??= '';
$authorized ??= false;

$electionId = (int) $election['id'];

$errorMessages = [
    'no_token'     => t('elections.authenticate.error.no_token'),
    'already_used' => t('elections.authenticate.error.already_used'),
    'locked'       => t('elections.authenticate.error.locked'),
    'expired'      => t('elections.authenticate.error.expired'),
    'invalid'      => t('elections.authenticate.error.invalid'),
];
?>
<div class="w-full bg-primary">
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

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="max-w-md mx-auto bg-surface rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
        <?php if ($authorized): ?>
            <div class="flex items-start gap-3">
                <span class="h-11 w-11 rounded-full bg-success/[4%] text-success flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </span>
                <div>
                    <p class="font-label-lg text-label-lg text-ink"><?= e(t('elections.authenticate.authorized_title')) ?></p>
                    <p class="text-body-sm text-muted mt-1"><?= e(t('elections.authenticate.authorized_message')) ?></p>
                </div>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
</div>
</div>
