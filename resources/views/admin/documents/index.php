<?php
/**
 * Liste des documents — admin (FIUCC-58, section 30 du cahier des
 * charges), même structure qu'admin/news/index.php (recherche, filtre,
 * pagination, info-bulles des actions). Filtre supplémentaire sur
 * visibility — utile pour retrouver "tout ce qui est réservé aux admins".
 */
$documents ??= [];
$filters ??= ['search' => '', 'category' => '', 'visibility' => ''];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$activeFilterQuery = array_filter([
    'search'     => $filters['search'],
    'category'   => $filters['category'],
    'visibility' => $filters['visibility'],
], static fn ($value) => $value !== '');

$fileIcon = static function (string $mimeType): string {
    return match ($mimeType) {
        'application/pdf' => 'fa-file-pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fa-file-powerpoint',
        'application/vnd.oasis.opendocument.text' => 'fa-file-lines',
        default => 'fa-file',
    };
};

$visibilityBadge = static function (string $visibility): string {
    $variants = [
        'public'  => 'bg-success/15 text-success',
        'members' => 'bg-gold/20 text-ink',
        'admin'   => 'bg-danger/10 text-danger',
    ];
    $classes = $variants[$visibility] ?? $variants['members'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('admin.documents.visibility.' . $visibility)) . '</span>';
};

$noticeMap = [
    'created'   => ['type' => 'success', 'message' => t('admin.documents.notice.created')],
    'updated'   => ['type' => 'success', 'message' => t('admin.documents.notice.updated')],
    'deleted'   => ['type' => 'success', 'message' => t('admin.documents.notice.deleted')],
    'not_found' => ['type' => 'error',   'message' => t('admin.documents.notice.not_found')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$paginationBase = '/admin/documents?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.documents.total', ['count' => $total])) ?></p>
        <a href="/admin/documents/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <?= e(t('admin.documents.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/documents" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="doc-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.documents.search_label')) ?></label>
            <input type="search" id="doc-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.documents.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="doc-visibility" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.documents.filter.visibility')) ?></label>
            <select id="doc-visibility" name="visibility" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.documents.filter.visibility_all')) ?></option>
                <?php foreach (['public', 'members', 'admin'] as $visibilityOption): ?>
                    <option value="<?= e($visibilityOption) ?>" <?= $filters['visibility'] === $visibilityOption ? 'selected' : '' ?>>
                        <?= e(t('admin.documents.visibility.' . $visibilityOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.documents.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/documents" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.documents.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.documents.column.document')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.documents.column.category')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.documents.column.visibility')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.documents.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($documents === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.documents.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($documents as $document): ?>
                        <?php $documentId = (int) $document['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <span class="h-10 w-10 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                        <i class="fa-solid <?= e($fileIcon((string) $document['mime_type'])) ?>" aria-hidden="true"></i>
                                    </span>
                                    <p class="font-label-lg text-label-lg text-ink truncate"><?= e($document['title']) ?></p>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= !empty($document['category']) ? e($document['category']) : '—' ?></td>
                            <td class="p-3"><?= $visibilityBadge($document['visibility']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="/documents/<?= e((string) $documentId) ?>/download" aria-label="<?= e(t('admin.documents.action.download')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-ink hover:bg-ink/10 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        </a>
                                        <?= $actionTooltip(t('admin.documents.action.download')) ?>
                                    </div>
                                    <div class="relative group">
                                        <a href="/admin/documents/<?= e((string) $documentId) ?>/edit" aria-label="<?= e(t('admin.documents.action.edit')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <?= $actionTooltip(t('admin.documents.action.edit')) ?>
                                    </div>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="delete-dialog-<?= e((string) $documentId) ?>"
                                            aria-label="<?= e(t('admin.documents.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        </button>
                                        <?= $actionTooltip(t('admin.documents.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="delete-dialog-<?= e((string) $documentId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="/admin/documents/<?= e((string) $documentId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.documents.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.documents.delete.confirm_message', ['title' => $document['title']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.documents.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.documents.delete.confirm_button')) ?></button>
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
