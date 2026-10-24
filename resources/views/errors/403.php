<?php
$title ??= t('error.403_title');
$body ??= t('error.403_body');
?>
<div class="min-h-screen w-full flex flex-col items-center justify-center text-center px-4">
    <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-4">Error <?= e((string) http_response_code()) ?></p>
    <h1 class="font-headline-lg text-headline-lg text-ink mb-4"><?= e($title) ?></h1>
    <p class="text-body-md text-muted mb-8"><?= e($body) ?></p>
    <?php $backUrl = \App\Core\Intended::backUrl(); ?>
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <?php if ($backUrl !== null): ?>
            <?= component('button', ['label' => t('common.back'), 'variant' => 'primary', 'href' => $backUrl]) ?>
        <?php endif; ?>
        <?= component('button', ['label' => t('common.back_home'), 'variant' => $backUrl !== null ? 'secondary' : 'primary', 'href' => '/']) ?>
    </div>
</div>
