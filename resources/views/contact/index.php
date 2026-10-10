<?php
/**
 * Page contact (§39 du cahier des charges) — maquette
 * fiucc_portal_contact_secretariat_desk pour la disposition (formulaire à
 * gauche, coordonnées à droite, bandeau d'urgence). Seules les coordonnées
 * réellement renseignées dans le back office (/admin/contact) s'affichent :
 * aucune valeur d'exemple inventée.
 *
 * Saisies du bureau (adresse, horaires) : texte libre, échappé par e() puis
 * nl2br() pour garder les retours à la ligne.
 */
$contact ??= [];
$old ??= [];
$errors ??= [];
$sent ??= false;
$messageMaxLength ??= 3000;

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$has = static fn (string $key) => ($contact[$key] ?? '') !== '';

$directChannels = array_values(array_filter([
    $has('whatsapp_url') ? ['icon' => 'people-group', 'label' => t('contact.channel.whatsapp'), 'value' => t('contact.channel.whatsapp_hint'), 'href' => $contact['whatsapp_url'], 'external' => true] : null,
    $has('email') ? ['icon' => 'envelope', 'label' => t('contact.channel.email'), 'value' => $contact['email'], 'href' => 'mailto:' . $contact['email'], 'external' => false] : null,
    $has('phone') ? ['icon' => 'bell', 'label' => t('contact.channel.phone'), 'value' => $contact['phone'], 'href' => \App\Services\ContactSettings::telHref($contact['phone']), 'external' => false] : null,
    $has('instagram_url') ? ['icon' => 'instagram', 'label' => t('contact.channel.instagram'), 'value' => t('contact.channel.instagram_hint'), 'href' => $contact['instagram_url'], 'external' => true] : null,
]));

$textareaClasses = 'w-full rounded-control border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 '
    . ($error('message') !== null
        ? 'border-danger focus:border-danger focus:ring-danger/30'
        : 'border-border focus:border-primary focus:ring-primary-light/50');
?>
<section class="relative w-full overflow-hidden bg-primary text-white">
    <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <style>
            /* simple SVG animations: floating lines and pulsing dots */
            @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            @keyframes pulse { 0% { r: 2; opacity: 0.9 } 50% { r: 4; opacity: 0.5 } 100% { r: 2; opacity: 0.9 } }
            .float { transform-origin: 400px 450px; animation: floatY 6s ease-in-out infinite; }
            .pulse circle { animation: pulse 2.5s ease-in-out infinite; }
        </style>
        <g class="float" stroke="#3B82F6" stroke-width="1">
            <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
            <path d="M340 20 L420 220 L520 140" />
            <path d="M420 220 L460 320 L280 360 L340 20" />
            <path d="M280 360 L120 60" />
            <path d="M460 320 L620 380 L520 500 L280 360" />
            <path d="M520 500 L560 560 L400 620 L280 360" />
            <path d="M560 560 L700 620 L620 760 L400 620" />
            <path d="M620 760 L640 800 L480 860 L400 620" />
        </g>
        <g class="pulse" fill="#60A5FA">
            <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
            <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
            <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
            <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
            <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
            <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
            <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
        </g>
    </svg>
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
        <p class="inline-flex items-center gap-2 font-label-caps text-label-caps uppercase tracking-wider text-white/80 mb-3">
            <span class="h-2 w-2 rounded-full bg-gold" aria-hidden="true"></span>
            <?= e(t('contact.eyebrow')) ?>
        </p>
        <h1 class="font-headline-lg text-headline-lg mb-3"><?= e(t('contact.title')) ?></h1>
        <p class="max-w-2xl text-body-md text-white/80"><?= e(t('contact.subtitle')) ?></p>
    </div>
</section>

<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-14 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <section class="lg:col-span-2 bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <div>
            <h2 class="font-headline-sm text-headline-sm text-ink mb-1"><?= e(t('contact.form.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('contact.form.subtitle')) ?></p>
        </div>

        <?php if ($sent): ?>
            <?= component('alert', ['type' => 'success', 'message' => t('contact.sent')]) ?>
        <?php elseif ($error('form') !== null): ?>
            <?= component('alert', ['type' => 'error', 'message' => $error('form')]) ?>
        <?php elseif ($errors !== []): ?>
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        <?php endif; ?>

        <form method="POST" action="/contact" class="flex flex-col gap-5" novalidate>
            <?= csrf_field() ?>

            <!-- Piège anti-robot (ContactController::send()) : hors écran et hors
                 tabulation, jamais rempli par un humain. -->
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="field-website">Website</label>
                <input type="text" id="field-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <?= component('form_field', [
                    'name' => 'name', 'label' => t('contact.field.name'), 'required' => true,
                    'value' => $field('name'), 'error' => $error('name'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'email', 'label' => t('contact.field.email'), 'type' => 'email', 'required' => true,
                    'value' => $field('email'), 'error' => $error('email'),
                ]) ?>
            </div>

            <?= component('form_field', [
                'name' => 'subject', 'label' => t('contact.field.subject'), 'required' => true,
                'value' => $field('subject'), 'error' => $error('subject'),
                'placeholder' => t('contact.field.subject_placeholder'),
            ]) ?>

            <div class="flex flex-col gap-1.5">
                <div class="flex items-baseline justify-between gap-3">
                    <label for="field-message" class="font-label-lg text-label-lg text-ink">
                        <?= e(t('contact.field.message')) ?> <span class="text-danger">*</span>
                    </label>
                    <span class="text-body-sm text-muted"><?= e(t('contact.field.message_max', ['max' => $messageMaxLength])) ?></span>
                </div>
                <textarea id="field-message" name="message" rows="7" required maxlength="<?= e((string) $messageMaxLength) ?>"
                    placeholder="<?= e(t('contact.field.message_placeholder')) ?>"
                    class="<?= e($textareaClasses) ?>"><?= e($field('message')) ?></textarea>
                <?php if ($error('message') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('message')) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="flex items-start gap-2 text-body-sm text-muted">
                    <?= icon('shield-halved', 'mt-0.5 shrink-0') ?>
                    <?= e(t('contact.form.privacy')) ?>
                </p>
                <?= component('button', ['label' => t('contact.form.submit'), 'variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </form>
    </section>

    <aside class="flex flex-col gap-6">
        <?php if ($directChannels !== []): ?>
            <section class="bg-white rounded-card border border-border p-5">
                <h2 class="font-label-lg text-label-lg text-ink mb-3"><?= e(t('contact.direct.title')) ?></h2>
                <ul class="flex flex-col gap-2">
                    <?php foreach ($directChannels as $channel): ?>
                        <li>
                            <a href="<?= e($channel['href']) ?>" <?= $channel['external'] ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                               class="flex items-center gap-3 rounded-control border border-border p-3 hover:border-primary transition-colors">
                                <span class="h-9 w-9 shrink-0 rounded-control bg-primary/[4%] text-primary flex items-center justify-center"><?= icon($channel['icon']) ?></span>
                                <span class="min-w-0">
                                    <span class="block text-label-md font-label-md text-ink"><?= e($channel['label']) ?></span>
                                    <span class="block text-body-sm text-muted truncate"><?= e($channel['value']) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($has('office_hours')): ?>
            <section class="bg-white rounded-card border border-border p-5">
                <h2 class="flex items-center gap-2 font-label-lg text-label-lg text-ink mb-3">
                    <?= icon('clock', 'text-primary') ?>
                    <?= e(t('contact.hours.title')) ?>
                </h2>
                <p class="text-body-sm text-ink leading-relaxed"><?= nl2br(e($contact['office_hours'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if ($has('address')): ?>
            <section class="bg-white rounded-card border border-border p-5">
                <h2 class="flex items-center gap-2 font-label-lg text-label-lg text-ink mb-3">
                    <?= icon('location-dot', 'text-primary') ?>
                    <?= e(t('contact.address.title')) ?>
                </h2>
                <p class="text-body-sm text-ink leading-relaxed"><?= nl2br(e($contact['address'])) ?></p>
                <?php if ($has('map_url')): ?>
                    <a href="<?= e($contact['map_url']) ?>" target="_blank" rel="noopener noreferrer"
                       class="mt-3 inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                        <?= icon('map-location-dot') ?>
                        <?= e(t('contact.address.map')) ?>
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <a href="/community/government" class="flex items-center justify-between gap-3 bg-white rounded-card border border-border p-5 hover:border-primary transition-colors">
            <span class="flex items-center gap-3">
                <span class="h-9 w-9 shrink-0 rounded-control bg-primary/[4%] text-primary flex items-center justify-center"><?= icon('sitemap') ?></span>
                <span>
                    <span class="block text-label-md font-label-md text-ink"><?= e(t('contact.government.title')) ?></span>
                    <span class="block text-body-sm text-muted"><?= e(t('contact.government.body')) ?></span>
                </span>
            </span>
            <?= icon('arrow-right', 'text-muted shrink-0') ?>
        </a>
    </aside>

    <?php if ($has('emergency_phone')): ?>
        <!-- Urgence : rouge réservé aux alertes (charte graphique). -->
        <section class="lg:col-span-3 rounded-card border border-danger/30 bg-danger/5 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="h-10 w-10 shrink-0 rounded-full bg-danger text-white flex items-center justify-center"><?= icon('triangle-exclamation') ?></span>
                <div>
                    <p class="font-label-caps text-label-caps uppercase text-danger"><?= e(t('contact.emergency.eyebrow')) ?></p>
                    <p class="text-body-md text-ink"><?= e(t('contact.emergency.body')) ?></p>
                </div>
            </div>
            <a href="<?= e(\App\Services\ContactSettings::telHref($contact['emergency_phone'])) ?>"
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-control bg-danger text-white text-label-lg font-label-lg hover:bg-danger/90 transition-colors whitespace-nowrap">
                <?= icon('bell') ?>
                <?= e($contact['emergency_phone']) ?>
            </a>
        </section>
    <?php endif; ?>
</div>
