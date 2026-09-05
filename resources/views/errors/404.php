<div class="min-h-screen w-full flex flex-col items-center justify-center text-center px-4">
    <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-4">Error 404</p>
    <h1 class="font-headline-lg text-headline-lg text-ink mb-4"><?= e(t('error.404_title')) ?></h1>
    <p class="text-body-md text-muted mb-8"><?= e(t('error.404_body')) ?></p>
    <?= component('button', ['label' => t('error.back_home'), 'variant' => 'primary', 'href' => '/']) ?>
</div>
