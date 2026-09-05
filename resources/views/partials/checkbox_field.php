<?php
/**
 * @param string $name
 * @param string $label
 * @param string|null $description
 * @param bool $checked
 * @param bool $required
 * @param string|null $error   Message d'erreur — même convention que form_field/select_field
 */
$description ??= null;
$checked ??= false;
$required ??= false;
$error ??= null;
$fieldId = 'field-' . $name;
$hasError = $error !== null && $error !== '';
?>
<label for="<?= e($fieldId) ?>" class="flex items-start gap-3 cursor-pointer">
    <input
        type="checkbox"
        id="<?= e($fieldId) ?>"
        name="<?= e($name) ?>"
        value="1"
        <?= $checked ? 'checked' : '' ?>
        <?= $required ? 'required' : '' ?>
        class="mt-1 h-5 w-5 rounded border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50 shrink-0"
    >
    <span class="text-body-sm text-ink">
        <?= e($label) ?>
        <?php if ($description !== null): ?>
            <span class="block text-muted"><?= e($description) ?></span>
        <?php endif; ?>
        <?php if ($hasError): ?>
            <span class="block text-danger" role="alert"><?= e($error) ?></span>
        <?php endif; ?>
    </span>
</label>
