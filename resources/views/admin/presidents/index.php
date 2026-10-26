<?php
/**
 * Présidents de la RDC (migration 030), du plus récent au plus ancien,
 * comme sur la page publique. Suppression confirmée par <dialog>
 * (admin-dialogs.js), comme la FAQ.
 */
$presidents ??= [];
$notice ??= '';
$handover ??= '';

$noticeMap = [
    'created' => t('admin.presidents.notice.created'),
    'updated' => t('admin.presidents.notice.updated'),
    'deleted' => t('admin.presidents.notice.deleted'),
];
?>
<div class="flex flex-col gap-6">
    <?php if (isset($noticeMap[$notice])): ?>
        <?= component('alert', ['type' => 'success', 'message' => $noticeMap[$notice]]) ?>
    <?php endif; ?>
    <?php if ($handover !== ''): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.presidents.notice.handover', ['name' => $handover])]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.presidents.subtitle')) ?></p>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="/community/presidents" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-white border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
                <?= icon('arrow-up-right-from-square') ?>
                <?= e(t('admin.presidents.view_page')) ?>
            </a>
            <a href="/admin/presidents/create"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                <?= icon('plus') ?>
                <?= e(t('admin.presidents.add')) ?>
            </a>
        </div>
    </div>

    <?php if ($presidents === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.presidents.empty')]) ?>
    <?php else: ?>
        <ul class="flex flex-col gap-3">
            <?php foreach ($presidents as $president): ?>
                <?php
                $presidentId = (int) $president['id'];
                $photoUrl = \App\Models\President::photoUrl($president);
                $isCurrent = $president['until_year'] === null;
                ?>
                <li class="bg-white rounded-card border <?= $isCurrent ? 'border-primary' : 'border-border' ?> p-4 flex items-center gap-4">
                    <?php if ($photoUrl !== null): ?>
                        <img src="<?= e($photoUrl) ?>" alt="" width="48" height="60" class="h-[60px] w-12 rounded-control object-cover object-top border border-border shrink-0">
                    <?php else: ?>
                        <span class="h-[60px] w-12 rounded-control bg-surface border border-border text-muted flex items-center justify-center shrink-0" aria-hidden="true"><?= icon('user') ?></span>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-0.5">
                            <p class="font-label-lg text-label-lg text-ink"><?= e($president['name']) ?></p>
                            <?php if ($isCurrent): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('presidents.current')) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $president['is_published'] !== 1): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-surface border border-border text-muted text-label-md font-label-md"><?= e(t('admin.presidents.hidden')) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-body-sm text-muted tabular-nums">
                            <?= e($isCurrent
                                ? t('presidents.period_current', ['from' => (string) $president['from_year']])
                                : t('presidents.period', ['from' => (string) $president['from_year'], 'until' => (string) $president['until_year']])) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <a href="<?= e('/admin/presidents/' . $presidentId . '/edit') ?>" aria-label="<?= e(t('admin.presidents.edit')) ?>" title="<?= e(t('admin.presidents.edit')) ?>"
                           class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                            <?= icon('pen') ?>
                        </a>
                        <button type="button" data-open-dialog="president-delete-<?= e((string) $presidentId) ?>" aria-label="<?= e(t('admin.presidents.delete')) ?>" title="<?= e(t('admin.presidents.delete')) ?>"
                                class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                            <?= icon('trash') ?>
                        </button>
                    </div>
                    <dialog id="president-delete-<?= e((string) $presidentId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                        <form method="POST" action="<?= e('/admin/presidents/' . $presidentId . '/delete') ?>" class="flex flex-col gap-2 p-4 text-left">
                            <?= csrf_field() ?>
                            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.presidents.delete_title')) ?></h2>
                            <p class="text-body-sm text-muted"><?= e(t('admin.presidents.delete_message', ['name' => $president['name']])) ?></p>
                            <div class="flex justify-end gap-2 mt-1">
                                <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.presidents.cancel')) ?></button>
                                <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.presidents.delete')) ?></button>
                            </div>
                        </form>
                    </dialog>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
