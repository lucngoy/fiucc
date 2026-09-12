<?php
/**
 * Rapport officiel imprimable (FIUCC-72) — voir Admin\ElectionController::
 * report() pour le raisonnement (pas de bibliothèque PDF, impression
 * native du navigateur). print:hidden (variant Tailwind natif) masque tout
 * ce qui n'a de sens qu'à l'écran (bouton, lien retour) au moment
 * d'imprimer/enregistrer en PDF — le contenu qui reste est déjà celui
 * archivé.
 *
 * $generatedAt : horodatage du tirage, pas de la clôture ni de la
 * publication — décision explicite conversation FIUCC-72, pour qu'une
 * réimpression ultérieure du même rapport reste datée distinctement si
 * plusieurs versions venaient à circuler.
 */
$election ??= [];
$groups ??= [];
$generatedAt ??= date('Y-m-d H:i:s');

$electionId = (int) $election['id'];
?>
<div class="max-w-2xl mx-auto px-6 py-10 flex flex-col gap-8">

    <div class="flex flex-col gap-3 print:hidden">
        <div class="flex items-center justify-between">
            <a href="/admin/elections/<?= e((string) $electionId) ?>/results" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors">
                <?= icon('arrow-left') ?>
                <?= e(t('admin.elections.report.back')) ?>
            </a>
            <button type="button" data-print-trigger class="inline-flex items-center gap-2 h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= icon('print') ?>
                <?= e(t('admin.elections.report.print_cta')) ?>
            </button>
        </div>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.report.background_graphics_note')]) ?>
    </div>

    <header class="flex items-center gap-4 pb-6 border-b border-border">
        <img src="/assets/images/fiucc-seal.png" alt="" class="h-14 w-14 object-contain">
        <div>
            <p class="text-label-caps font-label-caps uppercase text-muted"><?= e(t('admin.elections.report.eyebrow')) ?></p>
            <h1 class="font-headline-md text-headline-md text-ink"><?= e($election['title']) ?></h1>
        </div>
    </header>

    <div class="grid grid-cols-2 gap-4 text-body-sm">
        <div>
            <p class="text-label-caps font-label-caps uppercase text-muted mb-1"><?= e(t('admin.elections.field.start_at')) ?></p>
            <p class="text-ink"><?= e(local_date($election['start_at'])) ?></p>
        </div>
        <div>
            <p class="text-label-caps font-label-caps uppercase text-muted mb-1"><?= e(t('admin.elections.field.end_at')) ?></p>
            <p class="text-ink"><?= e(local_date($election['end_at'])) ?></p>
        </div>
        <?php if (!empty($election['results_published_at'])): ?>
            <div>
                <p class="text-label-caps font-label-caps uppercase text-muted mb-1"><?= e(t('elections.results.breadcrumb')) ?></p>
                <p class="text-ink"><?= e(local_date($election['results_published_at'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="flex flex-col gap-6">
        <?= component('election_results_body', ['groups' => $groups, 'cardBg' => 'bg-white']) ?>
    </div>

    <footer class="pt-6 border-t border-border text-body-sm text-muted">
        <?= e(t('admin.elections.report.generated_at', [
            'date' => local_date($generatedAt),
            'time' => date('H:i', strtotime($generatedAt)),
        ])) ?>
    </footer>
</div>

<script src="/assets/js/print-report.js" defer></script>
