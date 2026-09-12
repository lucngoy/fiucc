<?php
/**
 * Liste des élections — admin (FIUCC-59, sections 16-25 du cahier des
 * charges), même structure qu'admin/events/index.php. Pas de bouton
 * "Supprimer" ici : seule une élection 'draft' sans aucun poste peut être
 * supprimée (App\Models\Election::delete()), l'action vit sur la page de
 * détail (admin/elections/show.php) où le nombre de postes est visible,
 * pas dans cette liste.
 */
$elections ??= [];
$filters ??= ['search' => '', 'status' => ''];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$activeFilterQuery = array_filter([
    'search' => $filters['search'],
    'status' => $filters['status'],
], static fn ($value) => $value !== '');

$statusBadge = static function (string $status): string {
    $variants = [
        'draft'       => 'bg-muted/[1%] text-muted',
        'candidacies' => 'bg-gold/20 text-ink',
        'open'        => 'bg-success/[4%] text-success',
        'closed'      => 'bg-primary/10 text-primary',
        'published'   => 'bg-primary/10 text-primary',
    ];
    $classes = $variants[$status] ?? $variants['draft'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('admin.elections.status.' . $status)) . '</span>';
};

$noticeMap = [
    'created'        => ['type' => 'success', 'message' => t('admin.elections.notice.created')],
    'deleted'        => ['type' => 'success', 'message' => t('admin.elections.notice.deleted')],
    'not_found'      => ['type' => 'error',   'message' => t('admin.elections.notice.not_found')],
];

$paginationBase = '/admin/elections?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.elections.total', ['count' => $total])) ?></p>
        <a href="/admin/elections/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <?= icon('plus') ?>
            <?= e(t('admin.elections.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/elections" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="election-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.elections.search_label')) ?></label>
            <input type="search" id="election-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.elections.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="election-status" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.elections.filter.status')) ?></label>
            <select id="election-status" name="status" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.elections.filter.status_all')) ?></option>
                <?php foreach (['draft', 'candidacies', 'open', 'closed', 'published'] as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>>
                        <?= e(t('admin.elections.status.' . $statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/elections" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.elections.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.elections.column.election')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.elections.column.dates')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.elections.column.positions')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.elections.column.status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($elections === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($elections as $election): ?>
                        <tr class="border-b border-border last:border-0 align-top hover:bg-surface transition-colors cursor-pointer"
                            onclick="window.location='/admin/elections/<?= e((string) $election['id']) ?>'">
                            <td class="p-3">
                                <p class="font-label-lg text-label-lg text-ink"><?= e($election['title']) ?></p>
                            </td>
                            <td class="p-3 text-body-sm text-ink">
                                <?= e(local_date($election['start_at'])) ?> — <?= e(local_date($election['end_at'])) ?>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e((string) $election['position_count']) ?></td>
                            <td class="p-3"><?= $statusBadge($election['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?= component('pagination', ['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => $paginationBase]) ?>
</div>
