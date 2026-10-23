<?php
/**
 * Formulaire d'un poste du gouvernement (lot 4bis) — un seul gabarit pour
 * l'ajout et la modification, même principe qu'admin/partners/form.php.
 * Le compte lié est facultatif ; choisi, il complète côté serveur le nom,
 * la faculté et l'Instagram laissés vides.
 */
$mandate ??= [];
$member ??= null;
$old ??= [];
$errors ??= [];
$memberOptions ??= [];

$field = static fn (string $name) => (string) ($old[$name] ?? '');
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $member !== null;
$mandateId = (int) $mandate['id'];
$action = $isEdit ? '/admin/government/members/' . (int) $member['id'] : '/admin/government/mandates/' . $mandateId . '/members';
$backUrl = '/admin/government?mandate=' . $mandateId;

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink bg-white focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="<?= e($backUrl) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.government.mandate.heading', ['mandate' => $mandate['mandate']])) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'role', 'label' => t('admin.government.member.field.role'), 'required' => true,
            'value' => $field('role'), 'error' => $error('role'),
            'placeholder' => t('admin.government.member.field.role_placeholder'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-member_id" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.member.field.member')) ?></label>
            <select id="field-member_id" name="member_id" class="<?= e($inputClasses($error('member_id'))) ?>">
                <option value=""><?= e(t('admin.government.member.field.member_none')) ?></option>
                <?php foreach ($memberOptions as $option): ?>
                    <option value="<?= e((string) $option['id']) ?>" <?= $field('member_id') === (string) $option['id'] ? 'selected' : '' ?>>
                        <?= e($option['full_name']) ?><?= !empty($option['faculty']) ? e(' — ' . $option['faculty']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($error('member_id') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('member_id')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.government.member.field.member_help')) ?></p>
            <?php endif; ?>
        </div>

        <?= component('form_field', [
            'name' => 'display_name', 'label' => t('admin.government.member.field.display_name'),
            'value' => $field('display_name'), 'error' => $error('display_name'),
            'help' => t('admin.government.member.field.display_name_help'),
        ]) ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <?= component('form_field', [
                'name' => 'faculty', 'label' => t('admin.government.member.field.faculty'),
                'value' => $field('faculty'), 'error' => $error('faculty'),
            ]) ?>
            <?= component('form_field', [
                'name' => 'instagram', 'label' => t('admin.government.member.field.instagram'),
                'value' => $field('instagram'), 'error' => $error('instagram'),
                'placeholder' => '@pseudo',
            ]) ?>
        </div>

        <?= component('form_field', [
            'name' => 'sort_order', 'label' => t('admin.government.member.field.sort_order'), 'type' => 'number',
            'value' => $field('sort_order') !== '' ? $field('sort_order') : '0', 'error' => $error('sort_order'),
            'help' => t('admin.government.member.field.sort_order_help'),
        ]) ?>

        <div class="flex flex-col gap-1.5 pt-2 border-t border-border">
            <?php if ($isEdit && !empty($member['photo'])): ?>
                <div class="flex items-center gap-3 mb-1">
                    <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover border border-border">
                    <label class="inline-flex items-center gap-2 text-body-sm text-ink">
                        <input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 rounded border-border text-primary focus:ring-primary-light/50">
                        <?= e(t('admin.government.member.field.remove_photo')) ?>
                    </label>
                </div>
            <?php endif; ?>
            <label for="field-photo" class="font-label-lg text-label-lg text-ink">
                <?= e(t($isEdit && !empty($member['photo']) ? 'admin.government.member.field.photo_replace' : 'admin.government.member.field.photo')) ?>
            </label>
            <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
            <?php if ($error('photo') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('photo')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.government.member.field.photo_help')) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= e($backUrl) ?>" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.government.action.save' : 'admin.government.member.submit_create')) ?>
            </button>
        </div>
    </form>
</div>
