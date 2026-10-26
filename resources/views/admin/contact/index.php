<?php
/**
 * Coordonnées de la communauté (site_settings, migration 021) et messages
 * reçus par le formulaire /contact. Tous les champs sont facultatifs : un
 * champ vide disparaît simplement de la page publique.
 */
$values ??= [];
$errors ??= [];
$saved ??= false;
$messages ??= [];
$recipient ??= '';

$value = static fn (string $name) => $values[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;

$textareaClasses = static function (?string $errorMessage): string {
    return 'w-full rounded-control border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div class="flex flex-col gap-6">
    <?php if ($saved): ?>
        <?= component('alert', ['type' => 'success', 'message' => t('admin.contact.saved')]) ?>
    <?php elseif ($errors !== []): ?>
        <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.contact.subtitle')) ?></p>
        <a href="/contact" target="_blank" rel="noopener"
           class="shrink-0 whitespace-nowrap inline-flex items-center gap-2 h-9 px-4 rounded-control bg-white border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
            <?= icon('arrow-up-right-from-square') ?>
            <?= e(t('admin.contact.view_page')) ?>
        </a>
    </div>

    <form method="POST" action="/admin/contact" class="flex flex-col gap-6">
        <?= csrf_field() ?>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.section.channels')) ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?= component('form_field', ['name' => 'email', 'label' => t('admin.contact.field.email'), 'type' => 'email', 'value' => $value('email'), 'error' => $error('email'), 'placeholder' => 'contact@exemple.org']) ?>
                <?= component('form_field', ['name' => 'phone', 'label' => t('admin.contact.field.phone'), 'type' => 'tel', 'value' => $value('phone'), 'error' => $error('phone'), 'placeholder' => '+90 533 000 00 00']) ?>
                <?= component('form_field', ['name' => 'whatsapp_url', 'label' => t('admin.contact.field.whatsapp_url'), 'type' => 'url', 'value' => $value('whatsapp_url'), 'error' => $error('whatsapp_url'), 'placeholder' => 'https://chat.whatsapp.com/…', 'help' => t('admin.contact.field.whatsapp_url_help')]) ?>
                <?= component('form_field', ['name' => 'instagram_url', 'label' => t('admin.contact.field.instagram_url'), 'type' => 'url', 'value' => $value('instagram_url'), 'error' => $error('instagram_url'), 'placeholder' => 'https://instagram.com/…', 'help' => t('admin.contact.field.instagram_url_help')]) ?>
            </div>
        </section>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.section.office')) ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="flex flex-col gap-1.5">
                    <label for="field-address" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.field.address')) ?></label>
                    <textarea id="field-address" name="address" rows="4" maxlength="300" class="<?= e($textareaClasses($error('address'))) ?>"><?= e($value('address')) ?></textarea>
                    <?php if ($error('address') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('address')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="field-office_hours" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.field.office_hours')) ?></label>
                    <textarea id="field-office_hours" name="office_hours" rows="4" maxlength="500" placeholder="<?= e(t('admin.contact.field.office_hours_placeholder')) ?>" class="<?= e($textareaClasses($error('office_hours'))) ?>"><?= e($value('office_hours')) ?></textarea>
                    <?php if ($error('office_hours') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('office_hours')) ?></p>
                    <?php endif; ?>
                </div>
                <?= component('form_field', ['name' => 'map_url', 'label' => t('admin.contact.field.map_url'), 'type' => 'url', 'value' => $value('map_url'), 'error' => $error('map_url'), 'placeholder' => 'https://maps.google.com/…']) ?>
                <?= component('form_field', ['name' => 'emergency_phone', 'label' => t('admin.contact.field.emergency_phone'), 'type' => 'tel', 'value' => $value('emergency_phone'), 'error' => $error('emergency_phone'), 'help' => t('admin.contact.field.emergency_phone_help')]) ?>
            </div>
        </section>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <div>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.section.embassy')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.contact.section.embassy_help')) ?></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="flex flex-col gap-1.5">
                    <label for="field-embassy_address" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.field.embassy_address')) ?></label>
                    <textarea id="field-embassy_address" name="embassy_address" rows="3" maxlength="300" class="<?= e($textareaClasses($error('embassy_address'))) ?>"><?= e($value('embassy_address')) ?></textarea>
                    <?php if ($error('embassy_address') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('embassy_address')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col gap-5">
                    <?= component('form_field', ['name' => 'embassy_phone', 'label' => t('admin.contact.field.embassy_phone'), 'type' => 'tel', 'value' => $value('embassy_phone'), 'error' => $error('embassy_phone')]) ?>
                    <?= component('form_field', ['name' => 'embassy_email', 'label' => t('admin.contact.field.embassy_email'), 'type' => 'email', 'value' => $value('embassy_email'), 'error' => $error('embassy_email')]) ?>
                </div>
                <?= component('form_field', ['name' => 'embassy_url', 'label' => t('admin.contact.field.embassy_url'), 'type' => 'url', 'value' => $value('embassy_url'), 'error' => $error('embassy_url'), 'placeholder' => 'https://…']) ?>
                <?= component('form_field', ['name' => 'mother_community_url', 'label' => t('admin.contact.field.mother_community_url'), 'type' => 'url', 'value' => $value('mother_community_url'), 'error' => $error('mother_community_url'), 'placeholder' => 'https://congolais-chypre.org/', 'help' => t('admin.contact.field.mother_community_url_help')]) ?>
            </div>
        </section>

        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.section.form')) ?></h2>
            <?= component('form_field', ['name' => 'recipient_email', 'label' => t('admin.contact.field.recipient_email'), 'type' => 'email', 'value' => $value('recipient_email'), 'error' => $error('recipient_email'), 'help' => t('admin.contact.field.recipient_email_help')]) ?>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.contact.save')) ?></button>
        </div>
    </form>

    <section class="bg-white rounded-card border border-border overflow-hidden">
        <div class="p-5 border-b border-border">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.contact.messages.title')) ?></h2>
            <p class="text-body-sm text-muted">
                <?= e($recipient !== ''
                    ? t('admin.contact.messages.recipient', ['email' => $recipient])
                    : t('admin.contact.messages.no_recipient')) ?>
            </p>
        </div>
        <?php if ($messages === []): ?>
            <p class="p-5 text-body-sm text-muted"><?= e(t('admin.contact.messages.none')) ?></p>
        <?php else: ?>
            <ul class="divide-y divide-border">
                <?php foreach ($messages as $message): ?>
                    <li>
                        <details class="group">
                            <summary class="cursor-pointer list-none p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 hover:bg-surface">
                                <span class="min-w-0">
                                    <span class="block font-label-md text-label-md text-ink truncate"><?= e($message['subject']) ?></span>
                                    <span class="block text-body-sm text-muted truncate"><?= e($message['name']) ?> · <?= e($message['email']) ?></span>
                                </span>
                                <span class="flex items-center gap-2 shrink-0 text-body-sm text-muted">
                                    <?php if ((int) $message['mail_sent'] !== 1): ?>
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('admin.contact.messages.not_mailed')) ?></span>
                                    <?php endif; ?>
                                    <?= e(local_date($message['created_at'])) ?>
                                </span>
                            </summary>
                            <div class="px-4 pb-4 flex flex-col gap-3">
                                <p class="text-body-sm text-ink whitespace-pre-line break-words"><?= e($message['message']) ?></p>
                                <a href="<?= e('mailto:' . $message['email'] . '?subject=' . rawurlencode('Re: ' . $message['subject'])) ?>"
                                   class="inline-flex w-fit items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                                    <?= icon('paper-plane') ?>
                                    <?= e(t('admin.contact.messages.reply')) ?>
                                </a>
                            </div>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
