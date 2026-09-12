<?php
/**
 * Formulaire de partenaire (FIUCC-90) — un seul gabarit pour create()/
 * edit()/update(), même principe qu'admin/documents/form.php. Le logo est
 * facultatif dans les deux cas (contrairement au fichier d'un document) :
 * une entrée texte seule reste utile sans image.
 */
$partner ??= null;
$old ??= [];
$errors ??= [];
$types ??= ['alumni', 'company', 'sponsor'];
$statuses ??= ['draft', 'published'];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $partner !== null;
$action = $isEdit ? '/admin/partners/' . (int) $partner['id'] : '/admin/partners';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="/admin/partners" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.partners.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'name', 'label' => t('admin.partners.field.name'), 'required' => true,
            'value' => $field('name'), 'error' => $error('name'),
        ]) ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="flex flex-col gap-1.5">
                <label for="field-type" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.partners.field.type')) ?></label>
                <select id="field-type" name="type" class="<?= e($inputClasses($error('type'))) ?> bg-white">
                    <?php foreach ($types as $typeOption): ?>
                        <option value="<?= e($typeOption) ?>" <?= $field('type') === $typeOption ? 'selected' : '' ?>>
                            <?= e(t('partners.type.' . $typeOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('type') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('type')) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="field-status" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.partners.field.status')) ?></label>
                <select id="field-status" name="status" class="<?= e($inputClasses($error('status'))) ?> bg-white">
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= e($statusOption) ?>" <?= $field('status') === $statusOption ? 'selected' : '' ?>>
                            <?= e(t('admin.partners.status.' . $statusOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('status') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('status')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.partners.field.status_help')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="field-description" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.partners.field.description')) ?></label>
            <textarea id="field-description" name="description" rows="3" maxlength="1000"
                class="<?= e($inputClasses($error('description'))) ?> !h-auto py-2"><?= e($field('description')) ?></textarea>
            <?php if ($error('description') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('description')) ?></p>
            <?php endif; ?>
        </div>

        <?= component('form_field', [
            'name' => 'website_url', 'label' => t('admin.partners.field.website_url'), 'type' => 'url',
            'value' => $field('website_url'), 'error' => $error('website_url'),
            'placeholder' => 'https://…',
        ]) ?>

        <?= component('form_field', [
            'name' => 'sort_order', 'label' => t('admin.partners.field.sort_order'), 'type' => 'number',
            'value' => $field('sort_order') ?: '0', 'error' => $error('sort_order'),
            'help' => t('admin.partners.field.sort_order_help'),
        ]) ?>

        <div class="flex flex-col gap-1.5 pt-2 border-t border-border">
            <?php if ($isEdit && !empty($partner['logo'])): ?>
                <img src="<?= e('/uploads/' . $partner['logo']) ?>" alt="" class="h-16 w-16 rounded-control object-contain border border-border bg-surface p-1 mb-1">
            <?php endif; ?>
            <label for="field-logo" class="font-label-lg text-label-lg text-ink">
                <?= e(t($isEdit ? 'admin.partners.field.logo_replace' : 'admin.partners.field.logo')) ?>
            </label>
            <input type="file" id="field-logo" name="logo" accept="image/jpeg,image/png,image/webp"
                class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
            <?php if ($error('logo') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('logo')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.partners.field.logo_help')) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/partners" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.partners.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.partners.submit.update' : 'admin.partners.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
