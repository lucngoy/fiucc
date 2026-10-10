<?php
/**
 * Vérification email (section 14 du cahier des charges) — FIUCC-34.
 */
$notice ??= null;
$error ??= null;
$resendWaitSeconds ??= 0;
?>
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('verify.title')) ?></h1>
    <p class="text-body-md text-muted mb-8"><?= e(t('verify.subtitle')) ?></p>

    <?php if ($notice !== null): ?>
        <div class="mb-6 text-left"><?= component('alert', ['type' => 'success', 'message' => $notice]) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="mb-6 text-left"><?= component('alert', ['type' => 'error', 'message' => $error]) ?></div>
    <?php endif; ?>

    <form method="POST" action="/verify-email" id="verify-form" class="flex flex-col gap-6 text-left">
        <?= csrf_field() ?>
        <?= component('form_field', [
            'name' => 'code', 'label' => t('verify.field.code'), 'required' => true,
            'placeholder' => '••••••',
        ]) ?>
    </form>

    <div class="mt-4 flex justify-center gap-3">
        <?= component('button', [
            'label' => t('verify.verify_button'), 'variant' => 'primary', 'type' => 'submit',
            'attributes' => 'form="verify-form"',
        ]) ?>

        <form method="POST" action="/verify-email/resend">
            <?= csrf_field() ?>
            <?= component('button', [
                'label' => t('verify.resend_button'), 'variant' => 'secondary', 'type' => 'submit',
                'attributes' => 'id="resend-button" data-wait="' . (int) $resendWaitSeconds . '"'
                    . ' data-label-resend="' . e(t('verify.resend_button')) . '"'
                    . ' data-label-countdown="' . e(t('verify.resend_button_countdown')) . '"'
                    . ($resendWaitSeconds > 0 ? ' disabled' : ''),
            ]) ?>
        </form>
    </div>

    <script src="/assets/js/verify-email.js" defer></script>
</div>
