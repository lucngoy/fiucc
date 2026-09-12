<?php
/**
 * Formulaire d'album (FIUCC-54) — métadonnées (titre/description/événement
 * lié/statut) partagées par create()/edit(), même principe que
 * admin/events/form.php. La gestion des photos (upload par lot + légendes/
 * ordre/suppression) n'apparaît qu'en édition : un album doit exister avant
 * d'y uploader quoi que ce soit.
 */
$album ??= null;
$photos ??= [];
$old ??= [];
$errors ??= [];
$statuses ??= ['draft', 'published'];
$events ??= [];
$linkedEventIds ??= [];
$uploadResult ??= null;
$notice ??= '';

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $album !== null;
$albumId = $isEdit ? (int) $album['id'] : null;
$action = $isEdit ? '/admin/gallery/' . $albumId : '/admin/gallery';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};

$noticeMap = [
    'created'        => ['type' => 'success', 'message' => t('admin.gallery.notice.created')],
    'updated'        => ['type' => 'success', 'message' => t('admin.gallery.notice.updated')],
    'photos_updated' => ['type' => 'success', 'message' => t('admin.gallery.notice.photos_updated')],
];
?>
<div>
    <a href="/admin/gallery" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.gallery.title')) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice]) && $notice !== 'uploaded'): ?>
        <div class="mb-6"><?= component('alert', $noticeMap[$notice]) ?></div>
    <?php endif; ?>

    <?php if ($uploadResult !== null): ?>
        <div class="mb-6 flex flex-col gap-3">
            <?php if ($uploadResult['succeeded'] > 0): ?>
                <?= component('alert', ['type' => 'success', 'message' => t('admin.gallery.upload.succeeded', ['count' => $uploadResult['succeeded']])]) ?>
            <?php endif; ?>
            <?php if ($uploadResult['failures'] !== []): ?>
                <div class="rounded-control border border-danger/30 bg-danger/5 p-4">
                    <p class="text-body-sm text-danger font-label-lg mb-2"><?= e(t('admin.gallery.upload.failed', ['count' => count($uploadResult['failures'])])) ?></p>
                    <ul class="flex flex-col gap-1">
                        <?php foreach ($uploadResult['failures'] as $failure): ?>
                            <li class="text-body-sm text-ink">
                                <span class="font-label-md"><?= e($failure['filename']) ?></span> — <?= e($failure['reason']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6 mb-8">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'title', 'label' => t('admin.gallery.field.title'), 'required' => true,
            'value' => $field('title'), 'error' => $error('title'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-description" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.gallery.field.description')) ?></label>
            <textarea id="field-description" name="description" rows="4"
                class="<?= e($inputClasses($error('description'))) ?> !h-auto py-2"><?= e($field('description')) ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="flex flex-col gap-1.5">
                <label for="field-event" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.gallery.field.event')) ?></label>
                <select id="field-event" name="event_id" class="<?= e($inputClasses($error('event_id'))) ?> bg-white">
                    <option value=""><?= e(t('admin.gallery.field.event_none')) ?></option>
                    <?php foreach ($events as $eventOption): ?>
                        <?php
                        $eventId = (int) $eventOption['id'];
                        $isLinkedElsewhere = in_array($eventId, $linkedEventIds, true);
                        ?>
                        <option value="<?= e((string) $eventId) ?>"
                            <?= (int) $field('event_id') === $eventId ? 'selected' : '' ?>
                            <?= $isLinkedElsewhere ? 'disabled' : '' ?>>
                            <?= e($eventOption['title']) ?><?= $isLinkedElsewhere ? ' ' . e(t('admin.gallery.field.event_linked_suffix')) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('event_id') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('event_id')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.gallery.field.event_help')) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="field-status" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.gallery.field.status')) ?></label>
                <select id="field-status" name="status" class="<?= e($inputClasses($error('status'))) ?> bg-white">
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $field('status') === $statusOption ? 'selected' : '' ?>>
                            <?= e(t('admin.gallery.status.' . $statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('status') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('status')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.gallery.field.status_help.' . ($field('status') ?: 'draft'))) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/gallery" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.gallery.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.gallery.submit.update' : 'admin.gallery.submit.create')) ?>
            </button>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4 mb-8">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.gallery.upload.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.gallery.upload.help')) ?></p>
            <form method="POST" action="/admin/gallery/<?= e((string) $albumId) ?>/photos" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <?= csrf_field() ?>
                <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                    class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
                    <?= e(t('admin.gallery.upload.submit')) ?>
                </button>
            </form>
        </div>

        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.gallery.manage.title')) ?></h2>

            <?php if ($photos === []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('admin.gallery.manage.empty')]) ?>
            <?php else: ?>
                <form method="POST" action="/admin/gallery/<?= e((string) $albumId) ?>/photos/manage" class="flex flex-col gap-4">
                    <?= csrf_field() ?>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($photos as $photo): ?>
                            <?php $photoId = (int) $photo['id']; ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-control border border-border">
                                <img src="<?= e('/uploads/' . ($photo['thumbnail_path'] ?: $photo['image_path'])) ?>" alt=""
                                    class="h-16 w-24 rounded-control object-cover border border-border shrink-0">
                                <div class="flex-1 min-w-0">
                                    <label for="caption-<?= e((string) $photoId) ?>" class="sr-only"><?= e(t('admin.gallery.manage.caption')) ?></label>
                                    <input type="text" id="caption-<?= e((string) $photoId) ?>" name="captions[<?= e((string) $photoId) ?>]"
                                        value="<?= e((string) ($photo['caption'] ?? '')) ?>" placeholder="<?= e(t('admin.gallery.manage.caption')) ?>"
                                        class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="flex flex-col gap-1">
                                        <label for="order-<?= e((string) $photoId) ?>" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.gallery.manage.order')) ?></label>
                                        <input type="number" id="order-<?= e((string) $photoId) ?>" name="orders[<?= e((string) $photoId) ?>]"
                                            value="<?= e((string) $photo['sort_order']) ?>" min="0"
                                            class="h-10 w-20 rounded-control border border-border px-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                                    </div>
                                    <label class="flex flex-col items-center gap-1 text-label-caps font-label-caps text-danger uppercase cursor-pointer">
                                        <?= e(t('admin.gallery.manage.delete')) ?>
                                        <input type="checkbox" name="delete[]" value="<?= e((string) $photoId) ?>"
                                            class="h-4 w-4 rounded border-border text-danger focus:outline-none focus:ring-2 focus:ring-danger/30">
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                            <?= e(t('admin.gallery.manage.submit')) ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
