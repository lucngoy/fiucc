<?php
/**
 * Liste publique des élections — manquait depuis FIUCC-62/63, voir le
 * commentaire d'App\Controllers\ElectionController::index(). Jamais de
 * 'draft' (Election::publicList()), pas de pagination (peu d'élections
 * réelles pour une seule communauté).
 */
$elections ??= [];

// Fond clair (carte sur bg-surface), pas la bande primaire du bandeau —
// contrairement au badge de resources/views/elections/show.php, qui vit
// lui sur bg-primary et utilise donc bg-white/15.
$statusBadge = static function (string $status): string {
    $variants = [
        'candidacies' => 'bg-gold/20 text-ink',
        'open'        => 'bg-success/[4%] text-success',
        'closed'      => 'bg-muted/[1%] text-muted',
        'published'   => 'bg-primary/10 text-primary',
    ];
    $classes = $variants[$status] ?? 'bg-muted/[1%] text-muted';

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('elections.status.' . $status)) . '</span>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('elections.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('elections.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('elections.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">

    <?php if ($elections === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('elections.empty')]) ?>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($elections as $election): ?>
                <a href="<?= e('/elections/' . (int) $election['id']) ?>"
                   class="bg-surface rounded-card border border-border p-6 flex flex-col gap-3 hover:shadow-card transition-shadow">
                    <div>
                        <?= $statusBadge($election['status']) ?>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($election['title']) ?></h2>
                    <?php if (!empty($election['description'])): ?>
                        <p class="text-body-sm text-muted line-clamp-2"><?= e($election['description']) ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 text-body-sm text-muted mt-auto pt-2 border-t border-border">
                        <i class="fa-solid fa-calendar-day text-primary" aria-hidden="true"></i>
                        <span><?= e(local_date($election['start_at'])) ?> – <?= e(local_date($election['end_at'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>
