<?php
/**
 * Nouveau mot de passe depuis le lien reçu par email (PasswordController).
 * $usable=false : lien expiré, déjà utilisé ou inconnu — un seul message
 * pour les trois, avec un renvoi vers une nouvelle demande.
 */
$usable ??= false;
$token ??= '';
$errors ??= [];
$locale = \App\Core\Lang::locale();
$enLocaleClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frLocaleClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
?>
<div class="min-h-screen flex justify-center px-4 py-8">
    <div class="my-auto w-full max-w-md p-6 md:p-8">
        <div class="mb-6 flex justify-end">
            <div class="inline-flex items-center gap-1 rounded-control border border-[#dbe3ee] bg-[#f8fafc] p-1">
                <a href="/lang/en" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($enLocaleClasses) ?>">EN</a>
                <a href="/lang/fr" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($frLocaleClasses) ?>">FR</a>
            </div>
        </div>
        <div class="mb-8 text-center">
            <a href="/" class="mx-auto mb-4 flex h-14 w-14 items-center justify-center overflow-hidden">
                <img src="/assets/images/fiucc-seal.png" alt="FIUCC" class="h-full w-full object-cover" />
            </a>
            <h1 class="font-headline-lg text-headline-lg text-ink"><?= e(t('password.reset.title')) ?></h1>
        </div>

        <?php if (!$usable): ?>
            <?= component('alert', ['type' => 'error', 'message' => t('password.reset.invalid_link')]) ?>
            <div class="mt-6 text-center">
                <a href="/password/forgot" class="text-sm font-medium text-primary hover:underline"><?= e(t('password.reset.request_new')) ?></a>
            </div>
        <?php else: ?>
            <form method="POST" action="/password/reset" class="flex flex-col gap-5">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <?= component('form_field', [
                    'name' => 'password', 'label' => t('password.field.new'), 'type' => 'password', 'required' => true,
                    'error' => $errors['password'] ?? null,
                    'help' => t('census.field.password_help', ['min' => (int) \App\Core\Config::get('security.password_min_length', 8)]),
                ]) ?>
                <?= component('form_field', [
                    'name' => 'password_confirmation', 'label' => t('password.field.confirmation'), 'type' => 'password', 'required' => true,
                    'error' => $errors['password_confirmation'] ?? null,
                ]) ?>
                <?= component('button', ['label' => t('password.reset.submit'), 'variant' => 'primary', 'type' => 'submit']) ?>
            </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="/login" class="text-sm font-medium text-primary hover:underline"><?= e(t('password.back_to_login')) ?></a>
        </div>
    </div>
</div>
