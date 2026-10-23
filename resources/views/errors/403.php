<?php
$title ??= t('error.403_title');
$body ??= t('error.403_body');
?>
<div class="min-h-screen w-full flex flex-col items-center justify-center text-center px-4">
    <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-4">Error <?= e((string) http_response_code()) ?></p>
    <h1 class="font-headline-lg text-headline-lg text-ink mb-4"><?= e($title) ?></h1>
    <p class="text-body-md text-muted mb-8"><?= e($body) ?></p>
    <?= component('button', ['label' => t('common.back_home'), 'variant' => 'primary', 'href' => '/']) ?>
</div>
