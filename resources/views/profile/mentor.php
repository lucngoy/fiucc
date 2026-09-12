<?php
/**
 * Déclaration de mentor (FIUCC-87, section "Mentorat & partenariats" du
 * cahier — "un alumni peut se déclarer mentor (domaine, disponibilité,
 * mode de contact)"). domaine/disponibilité s'éditent ici ; le "mode de
 * contact" (show_email/show_phone) reste la propriété d'écriture de
 * /profile/privacy (FIUCC-83, Member::updatePrivacySettings()) — cet
 * écran l'affiche en lecture seule avec un lien pour le changer, jamais
 * un second formulaire pour la même donnée.
 */
$settings ??= ['is_mentor' => 0, 'mentor_field' => '', 'mentor_availability' => '', 'mentor_bio' => '', 'show_email' => 0, 'show_phone' => 0];
$errors ??= [];
$notice ??= (string) ($_GET['notice'] ?? '');

$error = static fn (string $name) => $errors[$name] ?? null;

$contactChannels = array_filter([
    (int) $settings['show_email'] === 1 ? t('census.privacy.show_email') : null,
    (int) $settings['show_phone'] === 1 ? t('census.privacy.show_phone') : null,
]);
?>
<div class="max-w-2xl mx-auto px-4 py-12 md:py-16">
    <div class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('profile.mentor.title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('profile.mentor.subtitle')) ?></p>
    </div>

    <?php if ($notice === 'updated'): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'success', 'message' => t('profile.mentor.notice.updated')]) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/profile/mentor" class="bg-white rounded-card border border-border shadow-card p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('checkbox_field', [
            'name' => 'is_mentor', 'label' => t('profile.mentor.field.is_mentor'),
            'description' => t('profile.mentor.field.is_mentor_description'),
            'checked' => (int) $settings['is_mentor'] === 1,
        ]) ?>

        <?= component('form_field', [
            'name' => 'mentor_field', 'label' => t('profile.mentor.field.domain'),
            'value' => (string) $settings['mentor_field'], 'error' => $error('mentor_field'),
            'placeholder' => t('profile.mentor.field.domain_placeholder'),
        ]) ?>

        <?= component('form_field', [
            'name' => 'mentor_availability', 'label' => t('profile.mentor.field.availability'),
            'value' => (string) $settings['mentor_availability'], 'error' => $error('mentor_availability'),
            'placeholder' => t('profile.mentor.field.availability_placeholder'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-mentor_bio" class="font-label-lg text-label-lg text-ink"><?= e(t('profile.mentor.field.bio')) ?></label>
            <textarea id="field-mentor_bio" name="mentor_bio" rows="4"
                placeholder="<?= e(t('profile.mentor.field.bio_placeholder')) ?>"
                class="rounded-control border border-border p-3 text-body-md text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"><?= e((string) $settings['mentor_bio']) ?></textarea>
        </div>

        <div class="rounded-control bg-surface border border-border p-4 flex items-start gap-3">
            <?= icon('circle-info', 'text-primary shrink-0 mt-0.5') ?>
            <p class="text-body-sm text-muted">
                <?php if ($contactChannels !== []): ?>
                    <?= e(t('profile.mentor.contact_channels', ['channels' => implode(', ', $contactChannels)])) ?>
                <?php else: ?>
                    <?= e(t('profile.mentor.contact_channels_none')) ?>
                <?php endif; ?>
                <a href="/profile/privacy" class="text-primary underline"><?= e(t('profile.mentor.contact_channels_link')) ?></a>
            </p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <?= component('button', ['label' => t('profile.cancel'), 'variant' => 'secondary', 'href' => '/profile']) ?>
            <?= component('button', ['label' => t('profile.save'), 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
