<?php
/**
 * @param string $name          Nom/id du champ
 * @param string $label         Libellé affiché
 * @param array $options        ['valeur' => 'Libellé', ...]
 * @param string $value         Valeur sélectionnée
 * @param string $placeholder   Option factice si aucune valeur (ex. "Select...")
 * @param string|null $error    Message d'erreur — bascule le champ en état d'erreur
 * @param string|null $help     Texte d'aide, affiché seulement en l'absence d'erreur
 * @param bool $required
 */
$value ??= '';
$placeholder ??= '';
$error ??= null;
$help ??= null;
$required ??= false;

$hasError = $error !== null && $error !== '';
$fieldId = 'field-' . $name;

$selectClasses = 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink bg-white '
    . 'focus:outline-none focus:ring-2 '
    . ($hasError
        ? 'border-danger focus:border-danger focus:ring-danger/30'
        : 'border-border focus:border-primary focus:ring-primary-light/50');
?>
<div class="flex flex-col gap-1.5">
    <label for="<?= e($fieldId) ?>" class="font-label-lg text-label-lg text-ink">
        <?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
    </label>
    <select id="<?= e($fieldId) ?>" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?> class="<?= e($selectClasses) ?>">
        <?php if ($placeholder !== ''): ?>
            <option value="" <?= $value === '' ? 'selected' : '' ?> disabled><?= e($placeholder) ?></option>
        <?php endif; ?>
        <?php foreach ($options as $optionValue => $optionLabel): ?>
            <option value="<?= e((string) $optionValue) ?>" <?= (string) $optionValue === (string) $value ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($hasError): ?>
        <p class="text-body-sm text-danger" role="alert"><?= e($error) ?></p>
    <?php elseif ($help !== null): ?>
        <p class="text-body-sm text-muted"><?= e($help) ?></p>
    <?php endif; ?>
</div>
