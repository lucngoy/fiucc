<?php
$email ??= '';
$error ??= null;
?>
<div class="max-w-md mx-auto px-4 py-16">
    <h1 class="font-headline-lg text-headline-lg text-ink mb-8 text-center">Sign in</h1>

    <?php if ($error !== null): ?>
        <div class="mb-6"><?= component('alert', ['type' => 'error', 'message' => $error]) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login" class="flex flex-col gap-6">
        <?= component('form_field', [
            'name' => 'email',
            'label' => 'University email',
            'type' => 'email',
            'value' => $email,
            'required' => true,
        ]) ?>
        <?= component('form_field', [
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'required' => true,
        ]) ?>
        <?= component('button', ['label' => 'Sign in', 'variant' => 'primary', 'type' => 'submit']) ?>
    </form>
</div>
