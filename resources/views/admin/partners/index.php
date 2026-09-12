<?php
/**
 * Liste des partenaires — admin (FIUCC-90), même structure
 * qu'admin/documents/index.php (recherche, filtres, pagination,
 * info-bulles des actions).
 */
$partners ??= [];
$filters ??= ['search' => '', 'type' => '', 'status' => ''];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$activeFilterQuery = array_filter([
    'search' => $filters['search'],
    'type'   => $filters['type'],
    'status' => $filters['status'],
], static fn ($value) => $value !== '');

$typeBadge = static function (string $type): string {
    $variants = [
        'alumni'  => 'bg-gold/20 text-ink',
        'company' => 'bg-primary/10 text-primary',
        'sponsor' => 'bg-success/[4%] text-success',
    ];
    $classes = $variants[$type] ?? $variants['company'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('partners.type.' . $type)) . '</span>';
};

$statusBadge = static function (string $status): string {
    $classes = $status === 'published' ? 'bg-success/[4%] text-success' : 'bg-muted/[1%] text-muted';

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('admin.partners.status.' . $status)) . '</span>';
};

$noticeMap = [
    'created'   => ['type' => 'success', 'message' => t('admin.partners.notice.created')],
    'updated'   => ['type' => 'success', 'message' => t('admin.partners.notice.updated')],
    'deleted'   => ['type' => 'success', 'message' => t('admin.partners.notice.deleted')],
    'not_found' => ['type' => 'error',   'message' => t('admin.partners.notice.not_found')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$paginationBase = '/admin/partners?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.partners.total', ['count' => $total])) ?></p>
        <a href="/admin/partners/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <?= icon('plus') ?>
            <?= e(t('admin.partners.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/partners" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="partner-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.partners.search_label')) ?></label>
            <input type="search" id="partner-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.partners.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="partner-type" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.partners.filter.type')) ?></label>
            <select id="partner-type" name="type" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.partners.filter.type_all')) ?></option>
                <?php foreach (['alumni', 'company', 'sponsor'] as $typeOption): ?>
                    <option value="<?= e($typeOption) ?>" <?= $filters['type'] === $typeOption ? 'selected' : '' ?>>
                        <?= e(t('partners.type.' . $typeOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="partner-status" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.partners.filter.status')) ?></label>
            <select id="partner-status" name="status" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.partners.filter.status_all')) ?></option>
                <?php foreach (['draft', 'published'] as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>>
                        <?= e(t('admin.partners.status.' . $statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.partners.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/partners" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.partners.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.partners.column.partner')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.partners.column.type')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.partners.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.partners.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($partners === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.partners.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($partners as $partner): ?>
                        <?php $partnerId = (int) $partner['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($partner['logo'])): ?>
                                        <img src="<?= e('/uploads/' . $partner['logo']) ?>" alt="" class="h-10 w-10 rounded-control object-contain border border-border bg-surface p-1 shrink-0">
                                    <?php else: ?>
                                        <span class="h-10 w-10 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <?= icon('building-columns') ?>
                                        </span>
                                    <?php endif; ?>
                                    <p class="font-label-lg text-label-lg text-ink truncate"><?= e($partner['name']) ?></p>
                                </div>
                            </td>
                            <td class="p-3"><?= $typeBadge($partner['type']) ?></td>
                            <td class="p-3"><?= $statusBadge($partner['status']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="/admin/partners/<?= e((string) $partnerId) ?>/edit" aria-label="<?= e(t('admin.partners.action.edit')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                            <?= icon('pen') ?>
                                        </a>
                                        <?= $actionTooltip(t('admin.partners.action.edit')) ?>
                                    </div>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="delete-dialog-<?= e((string) $partnerId) ?>"
                                            aria-label="<?= e(t('admin.partners.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <?= icon('trash') ?>
                                        </button>
                                        <?= $actionTooltip(t('admin.partners.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="delete-dialog-<?= e((string) $partnerId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="/admin/partners/<?= e((string) $partnerId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.partners.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.partners.delete.confirm_message', ['name' => $partner['name']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.partners.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.partners.delete.confirm_button')) ?></button>
                                        </div>
                                    </form>
                                </dialog>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?= component('pagination', ['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => $paginationBase]) ?>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
<script src="/assets/js/admin-tooltip.js" defer></script>
