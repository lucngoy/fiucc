<?php
/**
 * Liste des événements — admin (FIUCC-51, section 26/27 du cahier des
 * charges), même structure que admin/members/index.php (recherche, filtre
 * statut, pagination). Les 4 statuts affichés sont ceux réellement stockés
 * (events.status : draft/published/archived/cancelled), pas les états
 * calculés vus par le public (upcoming/ongoing/completed/cancelled,
 * Event::displayState()) — deux vocabulaires différents, volontairement.
 */
$events ??= [];
$filters ??= ['search' => '', 'status' => ''];
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';

$currentUrl = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/events');

$activeFilterQuery = array_filter([
    'search' => $filters['search'],
    'status' => $filters['status'],
], static fn ($value) => $value !== '');

$statusBadge = static function (string $status): string {
    $variants = [
        'draft'     => 'bg-muted/[1%] text-muted',
        'published' => 'bg-success/[4%] text-success',
        'archived'  => 'bg-primary/10 text-primary',
        'cancelled' => 'bg-danger/10 text-danger',
    ];
    $classes = $variants[$status] ?? $variants['draft'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md ' . $classes . '">'
        . e(t('admin.events.status.' . $status)) . '</span>';
};

$noticeMap = [
    'created'    => ['type' => 'success', 'message' => t('admin.events.notice.created')],
    'updated'    => ['type' => 'success', 'message' => t('admin.events.notice.updated')],
    'archived'   => ['type' => 'success', 'message' => t('admin.events.notice.archived')],
    'unarchived' => ['type' => 'success', 'message' => t('admin.events.notice.unarchived')],
    'deleted'    => ['type' => 'success', 'message' => t('admin.events.notice.deleted')],
    'not_found'  => ['type' => 'error',   'message' => t('admin.events.notice.not_found')],
];

/**
 * Info-bulle des boutons icône de la colonne Actions — même mécanique que
 * admin/members/index.php ($actionTooltip) : un <span> en CSS pur
 * (group-hover, pas de JS), plus rapide et plus cohérent avec la charte que
 * l'attribut title natif du navigateur.
 */
$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$paginationBase = '/admin/events?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.events.total', ['count' => $total])) ?></p>
        <a href="/admin/events/create"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <?= icon('plus') ?>
            <?= e(t('admin.events.create_button')) ?>
        </a>
    </div>

    <form method="GET" action="/admin/events" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="event-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.events.search_label')) ?></label>
            <input type="search" id="event-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.events.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="event-status" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.events.filter.status')) ?></label>
            <select id="event-status" name="status" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.events.filter.status_all')) ?></option>
                <?php foreach (['draft', 'published', 'archived', 'cancelled'] as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>>
                        <?= e(t('admin.events.status.' . $statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.events.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/events" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.events.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.events.column.event')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.events.column.date')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.events.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.events.column.capacity')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.events.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($events === []): ?>
                    <tr>
                        <td colspan="5" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.events.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php $eventId = (int) $event['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($event['cover_image'])): ?>
                                        <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt="" class="h-10 w-14 rounded-control object-cover border border-border shrink-0">
                                    <?php else: ?>
                                        <span class="h-10 w-14 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <?= icon('calendar-days') ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="font-label-lg text-label-lg text-ink truncate"><?= e($event['title']) ?></p>
                                        <?php if (!empty($event['location'])): ?>
                                            <p class="text-body-sm text-muted truncate"><?= e($event['location']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e(local_date($event['start_date'])) ?></td>
                            <td class="p-3"><?= $statusBadge($event['status']) ?></td>
                            <td class="p-3 text-body-sm text-ink">
                                <?= $event['capacity'] !== null ? e((string) $event['capacity']) : e(t('admin.events.capacity.unlimited')) ?>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="/admin/events/<?= e((string) $eventId) ?>/edit" aria-label="<?= e(t('admin.events.action.edit')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                            <?= icon('pen') ?>
                                        </a>
                                        <?= $actionTooltip(t('admin.events.action.edit')) ?>
                                    </div>
                                    <?php if ($event['status'] === 'archived'): ?>
                                        <div class="relative group">
                                            <form method="POST" action="/admin/events/<?= e((string) $eventId) ?>/unarchive">
                                                <?= csrf_field() ?>
                                                <button type="submit" aria-label="<?= e(t('admin.events.action.unarchive')) ?>"
                                                    class="h-9 w-9 rounded-control bg-surface text-ink hover:bg-ink/10 flex items-center justify-center transition-colors">
                                                    <?= icon('box-open') ?>
                                                </button>
                                            </form>
                                            <?= $actionTooltip(t('admin.events.action.unarchive')) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="relative group">
                                            <form method="POST" action="/admin/events/<?= e((string) $eventId) ?>/archive">
                                                <?= csrf_field() ?>
                                                <button type="submit" aria-label="<?= e(t('admin.events.action.archive')) ?>"
                                                    class="h-9 w-9 rounded-control bg-surface text-ink hover:bg-ink/10 flex items-center justify-center transition-colors">
                                                    <?= icon('box-archive') ?>
                                                </button>
                                            </form>
                                            <?= $actionTooltip(t('admin.events.action.archive')) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="delete-dialog-<?= e((string) $eventId) ?>"
                                            aria-label="<?= e(t('admin.events.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <?= icon('trash') ?>
                                        </button>
                                        <?= $actionTooltip(t('admin.events.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="delete-dialog-<?= e((string) $eventId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="/admin/events/<?= e((string) $eventId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.events.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.events.delete.confirm_message', ['title' => $event['title']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.events.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.events.delete.confirm_button')) ?></button>
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
