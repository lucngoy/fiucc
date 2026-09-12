<?php
/**
 * Formulaire de création/édition d'une élection (FIUCC-59) — un seul
 * gabarit, même principe qu'admin/events/form.php. N'est jamais atteignable
 * pour une élection qui n'est plus 'draft' : Admin\ElectionController::
 * edit()/update() redirigent avant même de rendre cette vue (voir
 * App\Models\Election, verrouillage de configuration).
 */
$election ??= null;
$old ??= [];
$errors ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $election !== null;
$action = $isEdit ? '/admin/elections/' . (int) $election['id'] : '/admin/elections';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="<?= e($isEdit ? '/admin/elections/' . (int) $election['id'] : '/admin/elections') ?>"
       class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.elections.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'title', 'label' => t('admin.elections.field.title'), 'required' => true,
            'value' => $field('title'), 'error' => $error('title'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-description" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.field.description')) ?></label>
            <textarea id="field-description" name="description" rows="3"
                class="<?= e($inputClasses($error('description'))) ?> !h-auto py-2"><?= e($field('description')) ?></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <?= component('form_field', [
                'name' => 'start_at', 'label' => t('admin.elections.field.start_at'), 'type' => 'datetime-local', 'required' => true,
                'value' => $field('start_at'), 'error' => $error('start_at'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'end_at', 'label' => t('admin.elections.field.end_at'), 'type' => 'datetime-local', 'required' => true,
                'value' => $field('end_at'), 'error' => $error('end_at'), 'help' => t('admin.elections.field.end_at_help'),
            ]) ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="<?= e($isEdit ? '/admin/elections/' . (int) $election['id'] : '/admin/elections') ?>"
               class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.elections.submit.update' : 'admin.elections.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
