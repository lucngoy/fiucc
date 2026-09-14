<?php
/**
 * Confidentialité et annuaire (FIUCC-83) : trois cases, un seul
 * formulaire, écriture séparée d'update() (voir Member::
 * updatePrivacySettings()) — même copie que le recensement
 * (census.privacy.*) pour ne jamais réexpliquer différemment la même
 * chose à deux endroits.
 */
$settings ??= ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0];
$notice ??= (string) ($_GET['notice'] ?? '');
?>
<div class="max-w-layout mx-auto px-4 py-12 md:py-16">
    <div class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('profile.privacy.title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('profile.privacy.subtitle')) ?></p>
    </div>

    <?php if ($notice === 'updated'): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'success', 'message' => t('profile.privacy.notice.updated')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/profile/privacy" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-4">
            <?= component('checkbox_field', [
                'name' => 'is_public', 'label' => t('census.privacy.is_public'),
                'description' => t('census.privacy.is_public_description'),
                'checked' => (int) $settings['is_public'] === 1,
            ]) ?>
            <?= component('checkbox_field', [
                'name' => 'show_email', 'label' => t('census.privacy.show_email'),
                'checked' => (int) $settings['show_email'] === 1,
            ]) ?>
            <?= component('checkbox_field', [
                'name' => 'show_phone', 'label' => t('census.privacy.show_phone'),
                'checked' => (int) $settings['show_phone'] === 1,
            ]) ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <?= component('button', ['label' => t('profile.cancel'), 'variant' => 'secondary', 'href' => '/profile']) ?>
            <?= component('button', ['label' => t('profile.save'), 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
