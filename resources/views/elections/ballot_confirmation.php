<?php
/**
 * Confirmation de vote (FIUCC-66, §21 : "Your vote has been successfully
 * recorded"). Rendue directement en réponse au POST final, sans
 * redirection — CLAUDE.md règle 13 : le message reste générique, jamais un
 * rappel des choix faits, même si le serveur les connaissait il y a un
 * instant dans cette même requête.
 *
 * $receipt : App\Models\Vote::castBallot() l'a écrit dans
 * voting_tokens.receipt_code, jamais dans votes — ne prouve que la
 * participation, ne révèle jamais le contenu (voir migration 014).
 */
$election ??= [];
$receipt ??= '';
?>
<div class="w-full bg-white min-h-[70vh] flex items-center">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 w-full">
    <div class="max-w-md mx-auto bg-surface rounded-card border border-border p-8 flex flex-col items-center gap-5 text-center">
        <span class="h-16 w-16 rounded-full bg-success/[4%] text-success flex items-center justify-center">
            <i class="fa-solid fa-circle-check text-3xl" aria-hidden="true"></i>
        </span>
        <div>
            <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('elections.ballot.confirmation_title')) ?></h1>
            <p class="text-body-md text-muted mt-2"><?= e(t('elections.ballot.confirmation_message')) ?></p>
        </div>

        <div class="w-full bg-white rounded-control border border-border p-4">
            <p class="text-label-caps font-label-caps uppercase text-muted mb-1"><?= e(t('elections.ballot.receipt_label')) ?></p>
            <p class="font-mono text-headline-sm text-headline-sm text-ink tracking-wider"><?= e($receipt) ?></p>
        </div>

        <p class="text-body-sm text-muted"><?= e(t('elections.ballot.receipt_help')) ?></p>

        <a href="/" class="inline-flex items-center justify-center h-11 px-6 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors">
            <?= e(t('elections.ballot.back_home')) ?>
        </a>
    </div>
</div>
</div>
