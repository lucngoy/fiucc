<?php
/**
 * Création / modification d'un président (migration 030). Français
 * obligatoire, anglais facultatif. Portrait obligatoire à la création,
 * remplacé seulement si un nouveau fichier est envoyé.
 */
$president ??= null;
$values ??= [];
$errors ??= [];
$firstYear ??= 1960;
$bioMaxLength ??= 1500;

$value = static fn (string $name) => (string) ($values[$name] ?? '');
$error = static fn (string $name) => $errors[$name] ?? null;
$controlClasses = static function (?string $errorMessage, bool $textarea = false): string {
    return 'w-full rounded-control border px-3 text-body-md text-ink bg-white focus:outline-none focus:ring-2 '
        . ($textarea ? 'py-2 ' : 'h-[42px] ')
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
$isEdit = $president !== null;
$action = $isEdit ? '/admin/presidents/' . (int) $president['id'] : '/admin/presidents';
$photoUrl = $isEdit ? \App\Models\President::photoUrl($president) : null;
$thisYear = (int) date('Y');
?>
<div class="flex flex-col gap-6">
    <a href="/admin/presidents" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.presidents.back')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="flex flex-col gap-6">
        <?= csrf_field() ?>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.presidents.section.identity')) ?></h2>
            <?= component('form_field', [
                'name' => 'name', 'label' => t('admin.presidents.field.name'), 'required' => true,
                'value' => $value('name'), 'error' => $error('name'),
            ]) ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?php foreach (['from_year' => true, 'until_year' => false] as $yearField => $required): ?>
                    <div class="flex flex-col gap-1.5">
                        <label for="field-<?= e($yearField) ?>" class="font-label-lg text-label-lg text-ink">
                            <?= e(t('admin.presidents.field.' . $yearField)) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
                        </label>
                        <input type="number" id="field-<?= e($yearField) ?>" name="<?= e($yearField) ?>" inputmode="numeric"
                               min="<?= e((string) $firstYear) ?>" max="<?= e((string) $thisYear) ?>" <?= $required ? 'required' : '' ?>
                               value="<?= e($value($yearField)) ?>" class="<?= e($controlClasses($error($yearField))) ?>">
                        <?php if ($error($yearField) !== null): ?>
                            <p class="text-body-sm text-danger" role="alert"><?= e($error($yearField)) ?></p>
                        <?php elseif ($yearField === 'until_year'): ?>
                            <p class="text-body-sm text-muted"><?= e(t('admin.presidents.field.until_year_help')) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php foreach (['' => 'fr', '_en' => 'en'] as $suffix => $lang): ?>
            <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
                <div>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.presidents.section.' . $lang)) ?></h2>
                    <?php if ($lang === 'en'): ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.presidents.section.en_help')) ?></p>
                    <?php endif; ?>
                </div>
                <?= component('form_field', [
                    'name' => 'role' . $suffix, 'label' => t('admin.presidents.field.role'), 'required' => $lang === 'fr',
                    'value' => $value('role' . $suffix), 'error' => $error('role' . $suffix),
                    'placeholder' => $lang === 'fr' ? t('admin.presidents.field.role_placeholder') : '',
                ]) ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-bio<?= e($suffix) ?>" class="font-label-lg text-label-lg text-ink">
                        <?= e(t('admin.presidents.field.bio')) ?><?= $lang === 'fr' ? ' <span class="text-danger">*</span>' : '' ?>
                    </label>
                    <textarea id="field-bio<?= e($suffix) ?>" name="bio<?= e($suffix) ?>" rows="4" maxlength="<?= e((string) $bioMaxLength) ?>"
                        class="<?= e($controlClasses($error('bio' . $suffix), true)) ?>"><?= e($value('bio' . $suffix)) ?></textarea>
                    <?php if ($error('bio' . $suffix) !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('bio' . $suffix)) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <div>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.presidents.section.photo')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.presidents.section.photo_help')) ?></p>
            </div>
            <div class="flex flex-col gap-1.5">
                <?php if ($photoUrl !== null): ?>
                    <img src="<?= e($photoUrl) ?>" alt="" width="80" height="100" class="h-[100px] w-20 rounded-control object-cover object-top border border-border mb-1">
                <?php endif; ?>
                <label for="field-photo" class="font-label-lg text-label-lg text-ink">
                    <?= e(t($isEdit ? 'admin.presidents.field.photo_replace' : 'admin.presidents.field.photo')) ?><?= $isEdit ? '' : ' <span class="text-danger">*</span>' ?>
                </label>
                <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                    class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                <?php if ($error('photo') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('photo')) ?></p>
                <?php else: ?>
                    <p class="text-body-sm text-muted"><?= e(t('admin.presidents.field.photo_help')) ?></p>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?= component('form_field', [
                    'name' => 'credit_author', 'label' => t('admin.presidents.field.credit_author'), 'required' => true,
                    'value' => $value('credit_author'), 'error' => $error('credit_author'),
                ]) ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-credit_license" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.presidents.field.credit_license')) ?> <span class="text-danger">*</span></label>
                    <select id="field-credit_license" name="credit_license" class="<?= e($controlClasses($error('credit_license'))) ?>">
                        <?php foreach (\App\Models\President::LICENSES as $license): ?>
                            <option value="<?= e($license) ?>" <?= $value('credit_license') === $license ? 'selected' : '' ?>><?= e(t('presidents.license.' . $license)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($error('credit_license') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('credit_license')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?= component('form_field', [
                'name' => 'credit_source', 'label' => t('admin.presidents.field.credit_source'), 'type' => 'url',
                'value' => $value('credit_source'), 'error' => $error('credit_source'),
                'placeholder' => 'https://commons.wikimedia.org/wiki/File:…',
                'help' => t('admin.presidents.field.credit_source_help'),
            ]) ?>
        </section>

        <section class="bg-white rounded-card border border-border p-6">
            <?= component('checkbox_field', [
                'name' => 'is_published', 'label' => t('admin.presidents.field.published'),
                'description' => t('admin.presidents.field.published_help'),
                'checked' => (int) ($values['is_published'] ?? 0) === 1,
            ]) ?>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.presidents.save')) ?></button>
        </div>
    </form>
</div>
