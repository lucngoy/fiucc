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
    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
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
