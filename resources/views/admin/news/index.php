<?php
/**
 * Liste des actualités — admin (FIUCC-56, section 29 du cahier des
 * charges), même structure qu'admin/events/index.php (recherche, filtre
 * catégorie, pagination, info-bulles des actions).
 */
$articles ??= [];
$filters ??= ['search' => '', 'category' => ''];
$categories ??= [];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$activeFilterQuery = array_filter([
    'search'   => $filters['search'],
    'category' => $filters['category'],
], static fn ($value) => $value !== '');

$publishBadge = static function (?string $publishedAt): string {
    if ($publishedAt === null) {
        return '<span class="inline-flex items-center h-6 px-2.5 rounded-full bg-muted/15 text-muted text-label-md font-label-md">' . e(t('admin.news.status.draft')) . '</span>';
    }

    $isFuture = strtotime($publishedAt) > time();
    $classes = $isFuture ? 'bg-gold/20 text-ink' : 'bg-success/15 text-success';
    $label = $isFuture ? t('admin.news.status.scheduled') : t('admin.news.status.published');

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">' . e($label) . '</span>';
};

$noticeMap = [
    'created'   => ['type' => 'success', 'message' => t('admin.news.notice.created')],
    'updated'   => ['type' => 'success', 'message' => t('admin.news.notice.updated')],
    'deleted'   => ['type' => 'success', 'message' => t('admin.news.notice.deleted')],
    'not_found' => ['type' => 'error',   'message' => t('admin.news.notice.not_found')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$paginationBase = '/admin/news?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.news.total', ['count' => $total])) ?></p>
        <a href="/admin/news/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <?= e(t('admin.news.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/news" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="news-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.news.search_label')) ?></label>
            <input type="search" id="news-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.news.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="news-category" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.news.filter.category')) ?></label>
            <select id="news-category" name="category" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.news.filter.category_all')) ?></option>
                <?php foreach ($categories as $categoryOption): ?>
                    <option value="<?= e($categoryOption) ?>" <?= $filters['category'] === $categoryOption ? 'selected' : '' ?>>
                        <?= e(t('news.category.' . $categoryOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.news.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/news" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.news.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.news.column.article')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.news.column.category')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.news.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.news.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($articles === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.news.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <?php $articleId = (int) $article['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($article['cover_image'])): ?>
                                        <img src="<?= e('/uploads/' . $article['cover_image']) ?>" alt="" class="h-10 w-14 rounded-control object-cover border border-border shrink-0">
                                    <?php else: ?>
                                        <span class="h-10 w-14 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <i class="fa-solid fa-newspaper" aria-hidden="true"></i>
                                        </span>
                                    <?php endif; ?>
                                    <p class="font-label-lg text-label-lg text-ink truncate"><?= e($article['title']) ?></p>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e(t('news.category.' . $article['category'])) ?></td>
                            <td class="p-3"><?= $publishBadge($article['published_at']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="/admin/news/<?= e((string) $articleId) ?>/edit" aria-label="<?= e(t('admin.news.action.edit')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <?= $actionTooltip(t('admin.news.action.edit')) ?>
                                    </div>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="delete-dialog-<?= e((string) $articleId) ?>"
                                            aria-label="<?= e(t('admin.news.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        </button>
                                        <?= $actionTooltip(t('admin.news.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="delete-dialog-<?= e((string) $articleId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="/admin/news/<?= e((string) $articleId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.news.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.news.delete.confirm_message', ['title' => $article['title']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.news.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.news.delete.confirm_button')) ?></button>
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
