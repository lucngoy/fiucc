<?php
/**
 * Réseau alumni (FIUCC-86, section "Espace Alumni" du cahier des charges).
 * Chaque ligne vient déjà rédigée de Member::alumniList() (is_public = 1
 * filtre l'inclusion elle-même) — cette vue n'a aucun contrôle de
 * confidentialité à faire, seulement à afficher ce qu'elle reçoit, même
 * discipline que directory/index.php (FIUCC-82).
 *
 * Carte : parcours (faculté/département), promotion (année de diplôme),
 * ville actuelle, domaine professionnel — exactement les quatre champs du
 * cahier, en plus de la photo et du nom déjà communs à tout profil.
 */
$members ??= [];
$filters ??= ['search' => ''];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

// Même seuil et même raisonnement que directory/index.php (FIUCC-82) :
// un réseau alumni tout neuf n'a l'air cassé que faute d'explication.
$sparseThreshold = 5;

$activeFilterQuery = array_filter(['search' => $filters['search']], static fn ($value) => $value !== '');

$paginationBase = '/alumni?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('alumni.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('alumni.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('alumni.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <form method="GET" action="/alumni" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
        <label for="alumni-search" class="sr-only"><?= e(t('alumni.search_label')) ?></label>
        <input type="search" id="alumni-search" name="search" value="<?= e($filters['search']) ?>"
            placeholder="<?= e(t('alumni.search_placeholder')) ?>"
            class="h-10 min-w-[220px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('alumni.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/alumni" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('alumni.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <p class="text-body-md text-muted mb-6"><?= e(t('alumni.total', ['count' => $total])) ?></p>

    <?php if ($members === []): ?>
        <div class="max-w-xl mx-auto">
            <?php if ($activeFilterQuery !== []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('alumni.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t('alumni.empty.default')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($total < $sparseThreshold): ?>
            <div class="mb-6">
                <?= component('alert', ['type' => 'info', 'message' => t('alumni.sparse_notice')]) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($members as $member): ?>
                <?php
                $parcours = trim(implode(' — ', array_filter([$member['faculty'], $member['department']], static fn ($v) => !empty($v))));
                $currentPlace = trim(implode(', ', array_filter([$member['city'], $member['country']], static fn ($v) => !empty($v))));
                ?>
                <div class="bg-surface rounded-card border border-border overflow-hidden p-4 flex gap-4">
                    <?php if (!empty($member['photo'])): ?>
                        <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover shrink-0">
                    <?php else: ?>
                        <span class="h-16 w-16 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-label-lg text-label-lg shrink-0">
                            <?= e(initials($member['full_name'])) ?>
                        </span>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-label-lg text-label-lg text-ink truncate">
                            <a href="<?= e('/directory/' . $member['id']) ?>" class="hover:text-primary hover:underline"><?= e($member['full_name']) ?></a>
                        </h3>
                        <?php if ($parcours !== ''): ?>
                            <p class="text-label-caps font-label-caps text-muted truncate"><?= e($parcours) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($member['graduation_year'])): ?>
                            <p class="text-body-sm text-muted"><?= e(t('alumni.card.promotion', ['year' => (string) $member['graduation_year']])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($member['profession'])): ?>
                            <p class="text-body-sm text-ink truncate">
                                <?= e($member['profession']) ?><?php if (!empty($member['company'])): ?> · <?= e($member['company']) ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($currentPlace !== ''): ?>
                            <p class="text-body-sm text-muted truncate"><?= e($currentPlace) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => $paginationBase,
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
