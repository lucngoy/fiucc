<?php
/**
 * Sauvegardes — admin (FIUCC-80, section 41 du cahier des charges). Liste
 * directement storage/backups/ (App\Services\Backup::list()) : pas de
 * pagination, un site communautaire n'accumule pas des centaines
 * d'archives entre deux nettoyages manuels.
 */
$backups ??= [];
$notice ??= '';

$noticeMap = [
    'created'   => ['type' => 'success', 'message' => t('admin.backups.notice.created')],
    'deleted'   => ['type' => 'success', 'message' => t('admin.backups.notice.deleted')],
    'not_found' => ['type' => 'error',   'message' => t('admin.backups.notice.not_found')],
    'failed'    => ['type' => 'error',   'message' => t('admin.backups.notice.failed')],
];

$formatSize = static function (int $bytes): string {
    return number_format($bytes / 1024 / 1024, 1) . ' Mo';
};

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <p class="text-body-md text-muted"><?= e(t('admin.backups.total', ['count' => count($backups)])) ?></p>
            <p class="text-body-sm text-muted"><?= e(t('admin.backups.help')) ?></p>
        </div>
        <form method="POST" action="/admin/backups">
            <?= csrf_field() ?>
            <button type="submit"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= icon('database') ?>
                <?= e(t('admin.backups.create_button')) ?>
            </button>
        </form>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.backups.column.file')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.backups.column.size')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.backups.column.date')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.backups.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($backups === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.backups.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                        <?php $dialogId = 'delete-dialog-' . preg_replace('/[^a-zA-Z0-9]/', '', $backup['filename']); ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <span class="h-10 w-10 rounded-control bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                        <?= icon('database') ?>
                                    </span>
                                    <p class="font-label-lg text-label-lg text-ink break-all"><?= e($backup['filename']) ?></p>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e($formatSize($backup['size'])) ?></td>
                            <td class="p-3 text-body-sm text-ink"><?= e(local_date(date('Y-m-d H:i:s', $backup['createdAt']))) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="relative group">
                                        <a href="<?= e('/admin/backups/' . $backup['filename']) ?>" aria-label="<?= e(t('admin.backups.action.download')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-ink hover:bg-ink/10 flex items-center justify-center transition-colors">
                                            <?= icon('download') ?>
                                        </a>
                                        <?= $actionTooltip(t('admin.backups.action.download')) ?>
                                    </div>
                                    <div class="relative group">
                                        <button type="button" data-open-dialog="<?= e($dialogId) ?>"
                                            aria-label="<?= e(t('admin.backups.action.delete')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <?= icon('trash') ?>
                                        </button>
                                        <?= $actionTooltip(t('admin.backups.action.delete')) ?>
                                    </div>
                                </div>
                                <dialog id="<?= e($dialogId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                    <form method="POST" action="<?= e('/admin/backups/' . $backup['filename'] . '/delete') ?>" class="flex flex-col gap-2 p-4 text-left">
                                        <?= csrf_field() ?>
                                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.backups.delete.confirm_title')) ?></h2>
                                        <p class="text-body-sm text-muted"><?= e(t('admin.backups.delete.confirm_message', ['filename' => $backup['filename']])) ?></p>
                                        <div class="flex justify-end gap-2 mt-1">
                                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.backups.action.cancel')) ?></button>
                                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.backups.delete.confirm_button')) ?></button>
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
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
<script src="/assets/js/admin-tooltip.js" defer></script>
