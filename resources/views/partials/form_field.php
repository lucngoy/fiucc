<?php
/**
 * @param string $name         Nom/id du champ
 * @param string $label        Libellé affiché
 * @param string $type         Type d'input HTML (défaut "text")
 * @param string $value        Valeur courante
 * @param string|null $error   Message d'erreur — bascule le champ en état d'erreur
 * @param string|null $help    Texte d'aide, affiché seulement en l'absence d'erreur
 * @param bool $required
 * @param string $placeholder
 */
$type ??= 'text';
$value ??= '';
$error ??= null;
$help ??= null;
$required ??= false;
$placeholder ??= '';

$hasError = $error !== null && $error !== '';
$fieldId = 'field-' . $name;

$inputClasses = 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink '
    . 'focus:outline-none focus:ring-2 '
    . ($hasError
        ? 'border-danger focus:border-danger focus:ring-danger/30'
        : 'border-border focus:border-primary focus:ring-primary-light/50');
?>
<div class="flex flex-col gap-1.5">
    <label for="<?= e($fieldId) ?>" class="font-label-lg text-label-lg text-ink">
        <?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
    </label>
    <input
        type="<?= e($type) ?>"
        id="<?= e($fieldId) ?>"
        name="<?= e($name) ?>"
        value="<?= e($value) ?>"
        placeholder="<?= e($placeholder) ?>"
        <?= $required ? 'required' : '' ?>
        class="<?= e($inputClasses) ?>"
    >
    <?php if ($hasError): ?>
        <p class="text-body-sm text-danger" role="alert"><?= e($error) ?></p>
    <?php elseif ($help !== null): ?>
        <p class="text-body-sm text-muted"><?= e($help) ?></p>
    <?php endif; ?>
</div>
