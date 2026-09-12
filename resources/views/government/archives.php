<?php
/**
 * Archives des anciens gouvernements (FIUCC-42) — section "Archives" du
 * cahier des charges (anciens gouvernements). Un mandat par section, avec
 * sa grille de membres réels (App\Models\Government::pastMandatesWithMembers()),
 * même patron de carte que /community/government (pas de spotlight ici :
 * l'archive n'a pas de "membre du jour", tous les postes d'un mandat clos
 * ont le même statut historique).
 */
$mandates ??= [];
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/community" class="hover:text-white transition-colors"><?= e(t('community.title')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/community/government" class="hover:text-white transition-colors"><?= e(t('government.title')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('government.archives.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('government.archives.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('government.archives.subtitle')) ?></p>
    </div>

    <?php if ($mandates !== []): ?>
        <div class="w-full bg-primary-dark/40">
            <div class="max-w-layout mx-auto px-4 md:px-6 py-5">
                <div class="flex items-center gap-3">
                    <?= icon('clock-rotate-left', 'text-white/90 text-xl') ?>
                    <div>
                        <p class="font-headline-sm text-headline-sm text-white"><?= e(t('government.archives.stat_value', ['count' => count($mandates)])) ?></p>
                        <p class="text-label-md font-label-md text-white/90"><?= e(t('government.archives.stat_caption')) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($mandates === []): ?>
    <div class="w-full bg-white">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <?= component('alert', ['type' => 'info', 'message' => t('government.archives.empty')]) ?>
    </div>
    </div>
<?php else: ?>
    <?php foreach ($mandates as $index => $mandate): ?>
        <div class="w-full <?= $index % 2 === 0 ? 'bg-white' : 'bg-surface' ?>">
        <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                <div>
                    <span class="inline-flex items-center h-7 px-3 rounded-full bg-gold/20 text-ink text-label-md font-label-md mb-3">
                        <?= e(t('government.mandate_badge', ['mandate' => $mandate['mandate']])) ?>
                    </span>
                    <h2 class="font-headline-md text-headline-md text-ink"><?= e($mandate['mandate']) ?></h2>
                    <p class="text-body-sm text-muted mt-1"><?= e(local_date($mandate['start_date'])) ?> – <?= e(local_date($mandate['end_date'])) ?></p>
                </div>
            </div>

            <?php if ($mandate['members'] === []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('government.archives.members_empty')]) ?>
            <?php else: ?>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($mandate['members'] as $member): ?>
                        <div class="bg-white rounded-card border border-border overflow-hidden">
                            <?php if (!empty($member['photo'])): ?>
                                <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="w-full aspect-square object-cover">
                            <?php else: ?>
                                <span class="w-full aspect-square bg-primary/[4%] text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                                    <?= e(initials($member['display_name'])) ?>
                                </span>
                            <?php endif; ?>
                            <div class="p-3">
                                <span class="inline-flex items-center h-5 px-2 mb-1.5 rounded-full bg-primary/[4%] text-primary text-label-caps font-label-caps uppercase">
                                    <?= e($member['role']) ?>
                                </span>
                                <h3 class="font-label-lg text-label-lg text-ink mb-0.5"><?= e($member['display_name']) ?></h3>
                                <?php if (!empty($member['faculty'])): ?>
                                    <p class="text-label-caps font-label-caps text-muted"><?= e($member['faculty']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="w-full <?= $mandates === [] || count($mandates) % 2 === 0 ? 'bg-white' : 'bg-surface' ?>">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12">
    <a href="/community/government" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline">
        <?= icon('arrow-left') ?>
        <?= e(t('government.archives.back')) ?>
    </a>
</div>
</div>
