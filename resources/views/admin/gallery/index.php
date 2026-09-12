<?php
/**
 * Liste des albums — admin (FIUCC-54, section 28 du cahier des charges),
 * même structure que admin/events/index.php (recherche, pagination,
 * info-bulles des actions). Statuts réels (draft/published), pas de
 * bascule à venir/passé — un album n'a pas de cycle de vie temporel comme
 * un événement.
 */
$albums ??= [];
$filters ??= ['search' => ''];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$currentUrl = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/gallery');

$activeFilterQuery = array_filter([
    'search' => $filters['search'],
], static fn ($value) => $value !== '');

$statusBadge = static function (string $status): string {
    $variants = [
        'draft'     => 'bg-muted/[1%] text-muted',
        'published' => 'bg-success/[4%] text-success',
    ];
    $classes = $variants[$status] ?? $variants['draft'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md ' . $classes . '">'
        . e(t('admin.gallery.status.' . $status)) . '</span>';
};

$noticeMap = [
    'created'   => ['type' => 'success', 'message' => t('admin.gallery.notice.created')],
    'deleted'   => ['type' => 'success', 'message' => t('admin.gallery.notice.deleted')],
    'not_found' => ['type' => 'error',   'message' => t('admin.gallery.notice.not_found')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$paginationBase = '/admin/gallery?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.gallery.total', ['count' => $total])) ?></p>
        <a href="/admin/gallery/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <?= e(t('admin.gallery.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/gallery" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="album-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.gallery.search_label')) ?></label>
            <input type="search" id="album-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.gallery.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.gallery.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/gallery" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.gallery.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.gallery.column.album')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.gallery.column.event')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.gallery.column.photos')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.gallery.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.gallery.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($albums === []): ?>
                    <tr>
                        <td colspan="5" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.gallery.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($albums as $album): ?>
                        <?php $albumId = (int) $album['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($album['cover_thumbnail'])): ?>
                                        <img src="<?= e('/uploads/' . $album['cover_thumbnail']) ?>" alt="" class="h-10 w-14 rounded-control object-cover border border-border shrink-0">
                                    <?php else: ?>
                                        <span class="h-10 w-14 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-images" aria-hidden="true"></i>
                                        </span>
                                    <?php endif; ?>
                                    <p class="font-label-lg text-label-lg text-ink truncate"><?= e($album['title']) ?></p>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink">
                                <?= !empty($album['event_title']) ? e($album['event_title']) : '<span class="text-muted">' . e(t('admin.gallery.column.event_none')) . '</span>' ?>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e((string) $album['photo_count']) ?></td>
                            <td class="p-3"><?= $statusBadge($album['status']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="/admin/gallery/<?= e((string) $albumId) ?>/edit" aria-label="<?= e(t('admin.gallery.action.edit')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <?= $actionTooltip(t('admin.gallery.action.edit')) ?>
                                    </div>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="delete-dialog-<?= e((string) $albumId) ?>"
                                            aria-label="<?= e(t('admin.gallery.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        </button>
                                        <?= $actionTooltip(t('admin.gallery.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="delete-dialog-<?= e((string) $albumId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="/admin/gallery/<?= e((string) $albumId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.gallery.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.gallery.delete.confirm_message', ['title' => $album['title']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.gallery.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.gallery.delete.confirm_button')) ?></button>
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
