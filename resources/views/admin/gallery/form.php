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
            <!-- Sans JavaScript : envoi groupé classique. Avec admin-gallery-upload.js :
                 aperçus retirables, puis envoi photo par photo — une photo en échec
                 reste dans la liste pour être renvoyée, sans tout resélectionner. -->
            <form method="POST" action="/admin/gallery/<?= e((string) $albumId) ?>/photos" enctype="multipart/form-data"
                  data-gallery-upload
                  data-redirect="<?= e('/admin/gallery/' . $albumId . '/edit?notice=uploaded') ?>"
                  data-label-waiting="<?= e(t('admin.gallery.upload.status.waiting')) ?>"
                  data-label-sending="<?= e(t('admin.gallery.upload.status.sending')) ?>"
                  data-label-done="<?= e(t('admin.gallery.upload.status.done')) ?>"
                  data-label-failed="<?= e(t('admin.gallery.upload.status.failed')) ?>"
                  data-label-remove="<?= e(t('admin.gallery.upload.remove')) ?>"
                  data-label-count="<?= e(t('admin.gallery.upload.count')) ?>"
                  data-label-submit="<?= e(t('admin.gallery.upload.submit')) ?>"
                  data-label-retry="<?= e(t('admin.gallery.upload.retry')) ?>"
                  data-label-kept="<?= e(t('admin.gallery.upload.kept')) ?>"
                  data-label-network="<?= e(t('admin.gallery.upload.network_error')) ?>"
                  data-label-invalid-type="<?= e(t('upload.invalid_type')) ?>"
                  class="flex flex-col gap-4">
                <?= csrf_field() ?>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <label for="gallery-photos" data-upload-picker
                           class="hidden cursor-pointer inline-flex items-center gap-2 h-10 px-4 rounded-control bg-white border border-primary text-primary text-label-lg font-label-lg hover:bg-primary-light/10 focus-within:ring-2 focus-within:ring-primary transition-colors shrink-0">
                        <?= icon('images') ?>
                        <?= e(t('admin.gallery.upload.choose')) ?>
                    </label>
                    <input type="file" id="gallery-photos" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <button type="submit" data-upload-submit class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark disabled:opacity-50 disabled:pointer-events-none transition-colors shrink-0">
                        <?= e(t('admin.gallery.upload.submit')) ?>
                    </button>
                </div>
                <p data-upload-summary class="hidden text-body-sm text-muted" aria-live="polite"></p>
                <ul data-upload-queue class="hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3"></ul>
                <template data-upload-item>
                    <li class="relative flex flex-col rounded-control border border-border bg-surface overflow-hidden" data-item>
                        <img data-item-preview src="" alt="" class="w-full aspect-square object-cover bg-surface">
                        <button type="button" data-item-remove
                                class="absolute top-1.5 right-1.5 h-8 w-8 rounded-full bg-white/90 text-danger shadow-card hover:bg-white flex items-center justify-center transition-colors disabled:hidden">
                            <?= icon('xmark') ?>
                        </button>
                        <div class="p-2 flex flex-col gap-1">
                            <p data-item-name class="text-body-sm text-ink truncate"></p>
                            <span data-item-status="waiting" class="hidden self-start inline-flex items-center h-6 px-2 rounded-full bg-white border border-border text-muted text-label-md font-label-md"></span>
                            <span data-item-status="sending" class="hidden self-start inline-flex items-center h-6 px-2 rounded-full bg-primary-light/30 text-primary text-label-md font-label-md"></span>
                            <span data-item-status="done" class="hidden self-start inline-flex items-center gap-1 h-6 px-2 rounded-full bg-success/10 text-success text-label-md font-label-md"><?= icon('check') ?><span data-item-status-text></span></span>
                            <span data-item-status="failed" class="hidden self-start inline-flex items-center h-6 px-2 rounded-full bg-danger/10 text-danger text-label-md font-label-md"></span>
                            <p data-item-reason class="hidden text-body-sm text-danger"></p>
                        </div>
                    </li>
                </template>
            </form>
        </div>

        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.gallery.manage.title')) ?></h2>

            <?php if ($photos === []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('admin.gallery.manage.empty')]) ?>
            <?php else: ?>
                <form method="POST" action="/admin/gallery/<?= e((string) $albumId) ?>/photos/manage" class="flex flex-col gap-4">
                    <?= csrf_field() ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.gallery.manage.drag_help')) ?></p>
                    <!-- Disposition masonry (colonnes CSS) : chaque photo garde ses
                         proportions, une carte courte ne s'étire pas sur la hauteur de
                         sa voisine. L'ordre se lit donc colonne par colonne (de haut en
                         bas, puis colonne suivante) — le numéro sur chaque vignette fait
                         foi.
                         Ordre par glisser-déposer (public/assets/js/admin-gallery-sort.js) :
                         le JS réécrit orders[id] = position à chaque déplacement, le
                         contrôleur (managePhotos) reçoit le même champ qu'avant. Les
                         flèches gardent un chemin clavier/mobile, où le drag HTML5
                         n'est pas disponible. -->
                    <ul data-gallery-sort class="columns-2 sm:columns-3 lg:columns-4 gap-4"
                        data-label-move-left="<?= e(t('admin.gallery.manage.move_left')) ?>"
                        data-label-move-right="<?= e(t('admin.gallery.manage.move_right')) ?>">
                        <?php foreach ($photos as $position => $photo): ?>
                            <?php $photoId = (int) $photo['id']; ?>
                            <li data-photo draggable="true"
                                class="group relative mb-4 flex flex-col break-inside-avoid rounded-card border border-border bg-white overflow-hidden cursor-grab active:cursor-grabbing transition-shadow hover:shadow-card">
                                <input type="hidden" data-order name="orders[<?= e((string) $photoId) ?>]" value="<?= e((string) $position) ?>">
                                <div class="relative bg-surface">
                                    <img src="<?= e('/uploads/' . ($photo['thumbnail_path'] ?: $photo['image_path'])) ?>" alt=""
                                        draggable="false" class="block w-full h-auto select-none">
                                    <span data-position class="absolute top-2 left-2 min-w-[24px] h-6 px-1.5 rounded-full bg-ink/70 text-white text-label-md font-label-md leading-6 text-center"><?= e((string) ($position + 1)) ?></span>
                                    <span class="absolute top-2 right-2 h-7 w-7 rounded-full bg-white/90 text-muted flex items-center justify-center" aria-hidden="true">
                                        <?= icon('grip-vertical', 'text-sm') ?>
                                    </span>
                                </div>
                                <div class="flex flex-col gap-2 p-2">
                                    <label for="caption-<?= e((string) $photoId) ?>" class="sr-only"><?= e(t('admin.gallery.manage.caption')) ?></label>
                                    <input type="text" id="caption-<?= e((string) $photoId) ?>" name="captions[<?= e((string) $photoId) ?>]"
                                        value="<?= e((string) ($photo['caption'] ?? '')) ?>" placeholder="<?= e(t('admin.gallery.manage.caption')) ?>"
                                        class="h-9 w-full rounded-control border border-border px-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1">
                                            <button type="button" data-move="-1" title="<?= e(t('admin.gallery.manage.move_left')) ?>" aria-label="<?= e(t('admin.gallery.manage.move_left')) ?>"
                                                class="h-8 w-8 rounded-control border border-border text-muted flex items-center justify-center hover:bg-surface hover:text-ink transition-colors">
                                                <?= icon('arrow-left', 'text-xs') ?>
                                            </button>
                                            <button type="button" data-move="1" title="<?= e(t('admin.gallery.manage.move_right')) ?>" aria-label="<?= e(t('admin.gallery.manage.move_right')) ?>"
                                                class="h-8 w-8 rounded-control border border-border text-muted flex items-center justify-center hover:bg-surface hover:text-ink transition-colors">
                                                <?= icon('arrow-right', 'text-xs') ?>
                                            </button>
                                        </div>
                                        <label class="flex items-center gap-1.5 text-label-caps font-label-caps text-danger uppercase cursor-pointer">
                                            <input type="checkbox" name="delete[]" value="<?= e((string) $photoId) ?>"
                                                class="h-4 w-4 rounded border-border text-danger focus:outline-none focus:ring-2 focus:ring-danger/30">
                                            <?= e(t('admin.gallery.manage.delete')) ?>
                                        </label>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
<script src="/assets/js/admin-gallery-sort.js" defer></script>
<!-- Versionné (asset()) : avec une version en cache d'encoded-uploads.js, l'envoi
     photo par photo n'aurait pas sa fonction d'encodage. -->
<script src="<?= e(asset('/assets/js/admin-gallery-upload.js')) ?>" defer></script>
