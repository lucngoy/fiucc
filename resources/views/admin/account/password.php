<?php
/**
 * Changement du mot de passe de l'admin connecté (Admin\AccountController).
 * Même règles que l'espace membre — App\Services\Passwords::change().
 */
$errors ??= [];
$notice ??= '';
?>
<div class="max-w-xl flex flex-col gap-6">
    <?php if ($notice === 'updated'): ?>
        <?= component('alert', ['type' => 'success', 'message' => t('password.change.notice_updated')]) ?>
    <?php endif; ?>

    <p class="text-body-md text-muted"><?= e(t('password.change.subtitle')) ?></p>

    <form method="POST" action="/admin/account/password" class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
        <?= csrf_field() ?>
        <?= component('form_field', [
            'name' => 'current_password', 'label' => t('password.field.current'), 'type' => 'password', 'required' => true,
            'error' => $errors['current_password'] ?? null,
        ]) ?>
        <?= component('form_field', [
            'name' => 'password', 'label' => t('password.field.new'), 'type' => 'password', 'required' => true,
            'error' => $errors['password'] ?? null,
            'help' => t('census.field.password_help', ['min' => (int) \App\Core\Config::get('security.password_min_length', 8)]),
        ]) ?>
        <?= component('form_field', [
            'name' => 'password_confirmation', 'label' => t('password.field.confirmation'), 'type' => 'password', 'required' => true,
            'error' => $errors['password_confirmation'] ?? null,
        ]) ?>

        <div class="flex justify-end gap-3 pt-2">
            <?= component('button', ['label' => t('profile.cancel'), 'variant' => 'secondary', 'href' => '/admin']) ?>
            <?= component('button', ['label' => t('profile.save'), 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
