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
<div class="w-full bg-primary">
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

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="max-w-2xl mx-auto flex flex-col gap-8">

        <?php if ($globalError !== null): ?>
            <?= component('alert', ['type' => 'error', 'message' => $globalError]) ?>
        <?php endif; ?>

        <?php if ($closingWarning !== null): ?>
            <?= component('alert', ['type' => 'warning', 'message' => $closingWarning]) ?>
        <?php endif; ?>

        <form method="POST" action="<?= e('/elections/' . $electionId . '/vote') ?>" class="flex flex-col gap-8">
            <?= csrf_field() ?>

            <?php foreach ($groups as $positionId => $group): ?>
                <fieldset class="bg-surface rounded-card border border-border p-6 flex flex-col gap-4">
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
