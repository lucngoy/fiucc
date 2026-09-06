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
$isPassword = $type === 'password';

$inputClasses = 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink '
    . ($isPassword ? 'pr-10 ' : '')
    . 'focus:outline-none focus:ring-2 '
    . ($hasError
        ? 'border-danger focus:border-danger focus:ring-danger/30'
        : 'border-border focus:border-primary focus:ring-primary-light/50');
?>
<div class="flex flex-col gap-1.5">
    <label for="<?= e($fieldId) ?>" class="font-label-lg text-label-lg text-ink">
        <?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
    </label>
    <div class="relative">
        <input
            type="<?= e($type) ?>"
            id="<?= e($fieldId) ?>"
            name="<?= e($name) ?>"
            value="<?= e($value) ?>"
            placeholder="<?= e($placeholder) ?>"
            <?= $required ? 'required' : '' ?>
            class="<?= e($inputClasses) ?>"
        >
        <?php if ($isPassword): ?>
            <button type="button" data-password-toggle
                class="absolute inset-y-0 right-0 flex items-center px-2.5 text-muted hover:text-primary"
                aria-label="<?= e(t('password.show')) ?>" aria-pressed="false"
                data-label-show="<?= e(t('password.show')) ?>" data-label-hide="<?= e(t('password.hide')) ?>">
                <svg data-icon-show class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg data-icon-hide class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        <?php endif; ?>
    </div>
    <?php if ($hasError): ?>
        <p class="text-body-sm text-danger" role="alert"><?= e($error) ?></p>
    <?php elseif ($help !== null): ?>
        <p class="text-body-sm text-muted"><?= e($help) ?></p>
    <?php endif; ?>
</div>
