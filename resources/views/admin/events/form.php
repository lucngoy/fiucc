<?php
/**
 * Formulaire de création/édition d'événement (FIUCC-51) — un seul gabarit
 * pour create() et edit()/update(), comme profile/edit.php. Pas de champ
 * "slug" (déduit du titre, App\Models\Event::uniqueSlug()) ni "programme"
 * structuré (CLAUDE.md — décision prise : description en texte libre).
 */
$event ??= null;
$old ??= [];
$errors ??= [];
$statuses ??= ['draft', 'published', 'archived', 'cancelled'];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $event !== null;
$action = $isEdit ? '/admin/events/' . (int) $event['id'] : '/admin/events';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="/admin/events" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <?= e(t('admin.events.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'title', 'label' => t('admin.events.field.title'), 'required' => true,
            'value' => $field('title'), 'error' => $error('title'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-description" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.events.field.description')) ?></label>
            <textarea id="field-description" name="description" rows="6"
                class="<?= e($inputClasses($error('description'))) ?> !h-auto py-2"><?= e($field('description')) ?></textarea>
            <?php if ($error('description') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('description')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.events.field.description_help')) ?></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <?= component('form_field', [
                'name' => 'category', 'label' => t('admin.events.field.category'),
                'value' => $field('category'), 'error' => $error('category'),
                'placeholder' => t('admin.events.field.category_placeholder'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'location', 'label' => t('admin.events.field.location'),
                'value' => $field('location'), 'error' => $error('location'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'capacity', 'label' => t('admin.events.field.capacity'), 'type' => 'number',
                'value' => $field('capacity'), 'error' => $error('capacity'), 'help' => t('admin.events.field.capacity_help'),
            ]) ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <?= component('form_field', [
                'name' => 'start_date', 'label' => t('admin.events.field.start_date'), 'type' => 'datetime-local', 'required' => true,
                'value' => $field('start_date'), 'error' => $error('start_date'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'end_date', 'label' => t('admin.events.field.end_date'), 'type' => 'datetime-local',
                'value' => $field('end_date'), 'error' => $error('end_date'), 'help' => t('admin.events.field.end_date_help'),
            ]) ?>

            <div class="flex flex-col gap-1.5">
                <label for="field-status" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.events.field.status')) ?></label>
                <select id="field-status" name="status" class="<?= e($inputClasses($error('status'))) ?> bg-white">
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $field('status') === $statusOption ? 'selected' : '' ?>>
                            <?= e(t('admin.events.status.' . $statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('status') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('status')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.events.field.status_help.' . ($field('status') ?: 'draft'))) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col gap-5 pt-2 border-t border-border">
            <div class="flex items-center gap-5">
                <?php if ($isEdit && !empty($event['cover_image'])): ?>
                    <img src="<?= e('/uploads/' . $event['cover_image']) ?>" alt="" class="h-16 w-24 rounded-control object-cover border border-border">
                <?php else: ?>
                    <span class="h-16 w-24 rounded-control bg-primary/10 text-primary flex items-center justify-center">
                        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                    </span>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-cover-image" class="font-label-lg text-label-lg text-ink">
                        <?= e(t($isEdit ? 'admin.events.field.cover_image_new' : 'admin.events.field.cover_image')) ?>
                    </label>
                    <input type="file" id="field-cover-image" name="cover_image" accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <?php if ($error('cover_image') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('cover_image')) ?></p>
                    <?php else: ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.events.field.cover_image_help')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/events" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.events.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.events.submit.update' : 'admin.events.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
