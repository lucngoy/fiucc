<?php
/**
 * Formulaire de document (FIUCC-58) — un seul gabarit pour create()/
 * edit()/update(), même principe qu'admin/events/form.php. Le fichier est
 * obligatoire à la création, facultatif à l'édition (remplace l'existant
 * seulement s'il est fourni — voir Admin\DocumentController::update()).
 */
$document ??= null;
$old ??= [];
$errors ??= [];
$visibilities ??= ['public', 'members', 'admin'];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $document !== null;
$action = $isEdit ? '/admin/documents/' . (int) $document['id'] : '/admin/documents';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="/admin/documents" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.documents.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'title', 'label' => t('admin.documents.field.title'), 'required' => true,
            'value' => $field('title'), 'error' => $error('title'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-description" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.documents.field.description')) ?></label>
            <textarea id="field-description" name="description" rows="3" maxlength="500"
                class="<?= e($inputClasses($error('description'))) ?> !h-auto py-2"><?= e($field('description')) ?></textarea>
            <?php if ($error('description') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('description')) ?></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <?= component('form_field', [
                'name' => 'category', 'label' => t('admin.documents.field.category'),
                'value' => $field('category'), 'error' => $error('category'),
                'placeholder' => t('admin.documents.field.category_placeholder'),
            ]) ?>

            <div class="flex flex-col gap-1.5">
                <label for="field-visibility" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.documents.field.visibility')) ?></label>
                <select id="field-visibility" name="visibility" class="<?= e($inputClasses($error('visibility'))) ?> bg-white">
                    <?php foreach ($visibilities as $visibilityOption): ?>
                        <option value="<?= e($visibilityOption) ?>" <?= $field('visibility') === $visibilityOption ? 'selected' : '' ?>>
                            <?= e(t('admin.documents.visibility.' . $visibilityOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('visibility') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('visibility')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.documents.field.visibility_help.' . ($field('visibility') ?: 'members'))) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col gap-1.5 pt-2 border-t border-border">
            <?php if ($isEdit): ?>
                <p class="text-body-sm text-ink">
                    <?= icon('paperclip', 'text-muted mr-1.5') ?>
                    <?= e(t('admin.documents.field.current_file')) ?>
                </p>
            <?php endif; ?>
            <label for="field-file" class="font-label-lg text-label-lg text-ink">
                <?= e(t($isEdit ? 'admin.documents.field.file_replace' : 'admin.documents.field.file')) ?><?= $isEdit ? '' : ' <span class="text-danger">*</span>' ?>
            </label>
            <input type="file" id="field-file" name="file" accept=".pdf,.docx,.xlsx,.pptx,.odt"
                class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
            <?php if ($error('file') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('file')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.documents.field.file_help')) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/documents" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.documents.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.documents.submit.update' : 'admin.documents.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
