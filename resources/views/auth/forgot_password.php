<?php
/**
 * "Mot de passe oublié ?" — demande d'un lien (PasswordController). $sent :
 * écran de confirmation, identique que l'adresse existe ou non (voir
 * App\Services\Passwords::requestReset()).
 */
$sent ??= false;
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
            <h1 class="font-headline-lg text-headline-lg text-ink"><?= e(t('password.forgot.title')) ?></h1>
            <?php if (!$sent): ?>
                <p class="mt-2 text-sm text-muted"><?= e(t('password.forgot.subtitle')) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($sent): ?>
            <?= component('alert', ['type' => 'success', 'message' => t('password.forgot.sent')]) ?>
        <?php else: ?>
            <form method="POST" action="/password/forgot" class="flex flex-col gap-5">
                <?= csrf_field() ?>
                <?= component('form_field', [
                    'name' => 'email', 'label' => t('auth.email'), 'type' => 'email', 'required' => true,
                    'placeholder' => 'ex. lucius.shawn@final.edu.tr',
                ]) ?>
                <?= component('button', ['label' => t('password.forgot.submit'), 'variant' => 'primary', 'type' => 'submit']) ?>
            </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="/login" class="text-sm font-medium text-primary hover:underline"><?= e(t('password.back_to_login')) ?></a>
        </div>
    </div>
</div>
