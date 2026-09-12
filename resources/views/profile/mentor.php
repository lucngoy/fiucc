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
$requests ??= [];
$notice ??= (string) ($_GET['notice'] ?? '');

$error = static fn (string $name) => $errors[$name] ?? null;

$contactChannels = array_filter([
    (int) $settings['show_email'] === 1 ? t('census.privacy.show_email') : null,
    (int) $settings['show_phone'] === 1 ? t('census.privacy.show_phone') : null,
]);

$requestNoticeMap = [
    'responded'       => ['type' => 'success', 'message' => t('profile.mentor.requests.notice.responded')],
    'response_failed' => ['type' => 'error',   'message' => t('profile.mentor.requests.notice.response_failed')],
];
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
    <?php elseif (isset($requestNoticeMap[$notice])): ?>
        <div class="mb-8">
            <?= component('alert', $requestNoticeMap[$notice]) ?>
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

    <?php if ($requests !== []): ?>
        <div class="mt-10">
            <h2 class="font-headline-sm text-headline-sm text-ink mb-4"><?= e(t('profile.mentor.requests.title')) ?></h2>
            <div class="flex flex-col gap-3">
                <?php foreach ($requests as $request): ?>
                    <div class="bg-white rounded-card border border-border p-4 flex flex-col gap-2">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($request['student_photo'])): ?>
                                <img src="<?= e('/uploads/' . $request['student_photo']) ?>" alt="" class="h-10 w-10 rounded-full object-cover shrink-0">
                            <?php else: ?>
                                <span class="h-10 w-10 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-label-md text-label-md shrink-0">
                                    <?= e(initials($request['student_name'])) ?>
                                </span>
                            <?php endif; ?>
                            <p class="font-label-lg text-label-lg text-ink"><?= e($request['student_name']) ?></p>
                        </div>
                        <?php if (!empty($request['topic'])): ?>
                            <p class="text-label-caps font-label-caps text-muted"><?= e($request['topic']) ?></p>
                        <?php endif; ?>
                        <p class="text-body-sm text-ink"><?= e($request['message']) ?></p>

                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="flex gap-2 mt-2">
                                <form method="POST" action="<?= e('/profile/mentor/requests/' . $request['id'] . '/respond') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="response" value="accepted">
                                    <button type="submit" class="h-9 px-4 rounded-control bg-success text-white text-label-md font-label-md hover:bg-success/90 transition-colors"><?= e(t('profile.mentor.requests.accept')) ?></button>
                                </form>
                                <form method="POST" action="<?= e('/profile/mentor/requests/' . $request['id'] . '/respond') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="response" value="declined">
                                    <button type="submit" class="h-9 px-4 rounded-control bg-surface text-ink hover:bg-ink/10 transition-colors"><?= e(t('profile.mentor.requests.decline')) ?></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-body-sm text-muted"><?= e(t('profile.mentor.requests.status.' . $request['status'])) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
