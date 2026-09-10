<?php
/**
 * Aperçu admin du dépouillement (FIUCC-71) — voir le commentaire
 * d'Admin\ElectionController::results(). Accessible dès 'closed', pas
 * seulement 'published' : c'est précisément la fenêtre où le bureau doit
 * pouvoir vérifier avant de décider de publier.
 */
$election ??= [];
$groups ??= [];

$electionId = (int) $election['id'];
$isPublished = $election['status'] === 'published';
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections/<?= e((string) $electionId) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <?= e($election['title']) ?>
    </a>

    <?php if (!$isPublished): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.results.preview_notice')]) ?>
    <?php endif; ?>

    <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.elections.results.title')) ?></h1>

    <?= component('election_results_body', ['groups' => $groups]) ?>
</div>
