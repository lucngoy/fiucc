<?php
/**
 * Questions fréquentes (migration 029) dans leur ordre d'affichage public.
 * Suppression confirmée par <dialog> (admin-dialogs.js), comme le
 * gouvernement.
 */
$items ??= [];
$notice ??= '';

$noticeMap = [
    'created' => t('admin.faq.notice.created'),
    'updated' => t('admin.faq.notice.updated'),
    'deleted' => t('admin.faq.notice.deleted'),
];
$lastIndex = count($items) - 1;
?>
<div class="flex flex-col gap-6">
    <?php if (isset($noticeMap[$notice])): ?>
        <?= component('alert', ['type' => 'success', 'message' => $noticeMap[$notice]]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.faq.subtitle')) ?></p>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="/new-at-final#faq" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-white border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
                <?= icon('arrow-up-right-from-square') ?>
                <?= e(t('admin.faq.view_page')) ?>
            </a>
            <a href="/admin/faq/create"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                <?= icon('plus') ?>
                <?= e(t('admin.faq.add')) ?>
            </a>
        </div>
    </div>

    <?php if ($items === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.faq.empty')]) ?>
    <?php else: ?>
        <ul class="flex flex-col gap-3">
            <?php foreach ($items as $index => $item): ?>
                <?php $itemId = (int) $item['id']; ?>
                <li id="faq-<?= e((string) $itemId) ?>" class="bg-white rounded-card border border-border p-4 flex flex-col sm:flex-row sm:items-start gap-3">
                    <div class="flex sm:flex-col items-center gap-1 shrink-0">
                        <?php foreach (['up', 'down'] as $direction): ?>
                            <?php $disabled = ($direction === 'up' && $index === 0) || ($direction === 'down' && $index === $lastIndex); ?>
                            <form method="POST" action="<?= e('/admin/faq/' . $itemId . '/move') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="direction" value="<?= e($direction) ?>">
                                <button type="submit" <?= $disabled ? 'disabled' : '' ?>
                                    aria-label="<?= e(t('admin.faq.move_' . $direction)) ?>"
                                    class="h-8 w-8 rounded-control bg-surface text-primary hover:bg-primary/15 disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center transition-colors">
                                    <?= icon('chevron-down', $direction === 'up' ? 'rotate-180' : '') ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <p class="font-label-lg text-label-lg text-ink"><?= e($item['question']) ?></p>
                            <?php if ((int) $item['is_published'] !== 1): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-surface border border-border text-muted text-label-md font-label-md"><?= e(t('admin.faq.hidden')) ?></span>
                            <?php endif; ?>
                            <?php if (($item['question_en'] ?? '') === ''): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('admin.faq.no_translation')) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-body-sm text-muted line-clamp-2"><?= e($item['answer']) ?></p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <a href="<?= e('/admin/faq/' . $itemId . '/edit') ?>" aria-label="<?= e(t('admin.faq.edit')) ?>" title="<?= e(t('admin.faq.edit')) ?>"
                           class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                            <?= icon('pen') ?>
                        </a>
                        <button type="button" data-open-dialog="faq-delete-<?= e((string) $itemId) ?>" aria-label="<?= e(t('admin.faq.delete')) ?>" title="<?= e(t('admin.faq.delete')) ?>"
                                class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                            <?= icon('trash') ?>
                        </button>
                    </div>
                    <dialog id="faq-delete-<?= e((string) $itemId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                        <form method="POST" action="<?= e('/admin/faq/' . $itemId . '/delete') ?>" class="flex flex-col gap-2 p-4 text-left">
                            <?= csrf_field() ?>
                            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.faq.delete_title')) ?></h2>
                            <p class="text-body-sm text-muted"><?= e(t('admin.faq.delete_message', ['question' => $item['question']])) ?></p>
                            <div class="flex justify-end gap-2 mt-1">
                                <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.faq.cancel')) ?></button>
                                <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.faq.delete')) ?></button>
                            </div>
                        </form>
                    </dialog>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
