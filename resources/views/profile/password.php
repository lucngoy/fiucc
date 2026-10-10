<?php
/**
 * Changement du mot de passe, espace membre (ProfileController::password()).
 * Même formulaire que l'admin (admin/account/password.php) — App\Services\
 * Passwords::change() applique les règles.
 */
$errors ??= [];
$notice ??= '';
?>
<div class="max-w-layout mx-auto px-4 py-12 md:py-16">
    <div class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('password.change.title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('password.change.subtitle')) ?></p>
    </div>

    <?php if ($notice === 'updated'): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'success', 'message' => t('password.change.notice_updated')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/profile/password" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
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
            <?= component('button', ['label' => t('profile.cancel'), 'variant' => 'secondary', 'href' => '/profile']) ?>
            <?= component('button', ['label' => t('profile.save'), 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
