<?php
/**
 * Aide et urgences (/help) : numéros d'urgence de Chypre du Nord
 * (config/emergency.php, source officielle TRNC), ambassade de la RDC et
 * communauté mère (saisies dans /admin/contact, rien d'inventé), puis la
 * demande d'aide au gouvernement (HelpController, migration 028).
 *
 * Rouge réservé aux urgences (charte graphique) : seul le bloc des numéros
 * l'utilise.
 */
$emergency ??= [];
$emergencySource ??= '';
$contact ??= [];
$member ??= null;
$isLoggedIn ??= false;
$requests ??= [];
$old ??= [];
$errors ??= [];
$sent ??= false;
$messageMaxLength ??= 3000;

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$has = static fn (string $key) => ($contact[$key] ?? '') !== '';
$telHref = static fn (string $phone) => \App\Services\ContactSettings::telHref($phone);

$hasEmbassy = $has('embassy_address') || $has('embassy_phone') || $has('embassy_email') || $has('embassy_url');

$categoryOptions = [];
foreach (\App\Models\HelpRequest::CATEGORIES as $category) {
    $categoryOptions[$category] = t('help.category.' . $category);
}

$statusClasses = [
    'open'        => 'bg-gold/20 text-ink',
    'in_progress' => 'bg-primary/10 text-primary',
    'closed'      => 'bg-success/10 text-success',
];

$textareaClasses = 'w-full rounded-control border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 '
    . ($error('message') !== null
        ? 'border-danger focus:border-danger focus:ring-danger/30'
        : 'border-border focus:border-primary focus:ring-primary-light/50');
?>
<section class="relative w-full overflow-hidden bg-primary text-white">
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="relative max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
        <p class="inline-flex items-center gap-2 font-label-caps text-label-caps uppercase tracking-wider text-white/80 mb-3">
            <span class="h-2 w-2 rounded-full bg-gold" aria-hidden="true"></span>
            <?= e(t('help.eyebrow')) ?>
        </p>
        <h1 class="font-headline-lg text-headline-lg mb-3"><?= e(t('help.title')) ?></h1>
        <p class="max-w-2xl text-body-md text-white/80"><?= e(t('help.subtitle')) ?></p>
    </div>
</section>

<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-14 flex flex-col gap-10">
    <!-- Numéros d'urgence -->
    <section aria-labelledby="emergency-title" class="rounded-card border border-danger/30 bg-danger/5 p-5 md:p-6 flex flex-col gap-5">
        <div class="flex items-start gap-3">
            <span class="h-10 w-10 shrink-0 rounded-full bg-danger text-white flex items-center justify-center"><?= icon('triangle-exclamation') ?></span>
            <div>
                <h2 id="emergency-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('help.emergency.title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('help.emergency.subtitle')) ?></p>
            </div>
        </div>
        <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <?php foreach ($emergency as $key => $entry): ?>
                <li>
                    <a href="<?= e($telHref((string) $entry['number'])) ?>"
                       class="h-full flex flex-col items-start gap-2 rounded-card bg-white border border-border p-4 hover:border-danger transition-colors">
                        <span class="h-9 w-9 rounded-control bg-danger/10 text-danger flex items-center justify-center"><?= icon((string) $entry['icon']) ?></span>
                        <span class="text-label-md font-label-md text-muted"><?= e(t('help.emergency.' . $key)) ?></span>
                        <span class="font-headline-md text-headline-md text-ink tabular-nums"><?= e((string) $entry['number']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-body-sm text-muted">
            <?= e(t('help.emergency.note')) ?>
            <?php if ($emergencySource !== ''): ?>
                <a href="<?= e($emergencySource) ?>" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline"><?= e(t('help.emergency.source')) ?></a>
            <?php endif; ?>
        </p>
    </section>

    <!-- Contacts utiles -->
    <section aria-labelledby="contacts-title" class="flex flex-col gap-4">
        <h2 id="contacts-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('help.contacts.title')) ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <article class="bg-white rounded-card border border-border p-5 flex flex-col gap-3">
                <h3 class="flex items-center gap-2 font-label-lg text-label-lg text-ink">
                    <?= icon('building-columns', 'text-primary') ?>
                    <?= e(t('help.embassy.title')) ?>
                </h3>
                <p class="text-body-sm text-muted"><?= e(t('help.embassy.body')) ?></p>
                <?php if ($hasEmbassy): ?>
                    <ul class="flex flex-col gap-2 text-body-sm text-ink">
                        <?php if ($has('embassy_address')): ?>
                            <li class="flex items-start gap-2"><?= icon('location-dot', 'text-muted mt-0.5 shrink-0') ?><span><?= nl2br(e($contact['embassy_address'])) ?></span></li>
                        <?php endif; ?>
                        <?php if ($has('embassy_phone')): ?>
                            <li class="flex items-center gap-2"><?= icon('phone', 'text-muted shrink-0') ?><a href="<?= e($telHref($contact['embassy_phone'])) ?>" class="text-primary hover:underline"><?= e($contact['embassy_phone']) ?></a></li>
                        <?php endif; ?>
                        <?php if ($has('embassy_email')): ?>
                            <li class="flex items-center gap-2 min-w-0"><?= icon('envelope', 'text-muted shrink-0') ?><a href="<?= e('mailto:' . $contact['embassy_email']) ?>" class="text-primary hover:underline truncate"><?= e($contact['embassy_email']) ?></a></li>
                        <?php endif; ?>
                        <?php if ($has('embassy_url')): ?>
                            <li class="flex items-center gap-2"><?= icon('arrow-up-right-from-square', 'text-muted shrink-0') ?><a href="<?= e($contact['embassy_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline"><?= e(t('help.embassy.website')) ?></a></li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-body-sm text-muted italic"><?= e(t('help.embassy.ask_bureau')) ?></p>
                <?php endif; ?>
            </article>

            <article class="bg-white rounded-card border border-border p-5 flex flex-col gap-3">
                <h3 class="flex items-center gap-2 font-label-lg text-label-lg text-ink">
                    <?= icon('people-group', 'text-primary') ?>
                    <?= e(t('help.mother.title')) ?>
                </h3>
                <p class="text-body-sm text-muted"><?= e(t('help.mother.body')) ?></p>
                <?php if ($has('mother_community_url')): ?>
                    <a href="<?= e($contact['mother_community_url']) ?>" target="_blank" rel="noopener noreferrer"
                       class="mt-auto inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                        <?= icon('arrow-up-right-from-square') ?>
                        <?= e(t('help.mother.link')) ?>
                    </a>
                <?php endif; ?>
            </article>

            <article class="bg-white rounded-card border border-border p-5 flex flex-col gap-3">
                <h3 class="flex items-center gap-2 font-label-lg text-label-lg text-ink">
                    <?= icon('sitemap', 'text-primary') ?>
                    <?= e(t('help.bureau.title')) ?>
                </h3>
                <p class="text-body-sm text-muted"><?= e(t('help.bureau.body')) ?></p>
                <div class="mt-auto flex flex-col gap-2">
                    <?php if ($has('emergency_phone')): ?>
                        <a href="<?= e($telHref($contact['emergency_phone'])) ?>" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                            <?= icon('phone') ?>
                            <?= e(t('help.bureau.phone', ['phone' => $contact['emergency_phone']])) ?>
                        </a>
                    <?php endif; ?>
                    <a href="/community/government" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                        <?= icon('arrow-right') ?>
                        <?= e(t('help.bureau.government_link')) ?>
                    </a>
                </div>
            </article>
        </div>
    </section>

    <!-- Demande d'aide -->
    <section id="request" aria-labelledby="request-title" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="flex flex-col gap-4">
            <div>
                <h2 id="request-title" class="font-headline-sm text-headline-sm text-ink mb-1"><?= e(t('help.request.title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('help.request.intro')) ?></p>
            </div>
            <ol class="flex flex-col gap-3">
                <?php foreach ([1, 2, 3] as $step): ?>
                    <li class="flex items-start gap-3">
                        <span class="h-7 w-7 shrink-0 rounded-full bg-primary text-white text-label-md font-label-md flex items-center justify-center"><?= e((string) $step) ?></span>
                        <span class="text-body-sm text-ink"><?= e(t('help.request.step' . $step)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="rounded-card border border-border bg-white p-4 flex items-start gap-3">
                <?= icon('circle-info', 'text-primary mt-0.5 shrink-0') ?>
                <p class="text-body-sm text-muted"><?= e(t('help.request.limits')) ?></p>
            </div>
            <p class="flex items-start gap-2 text-body-sm text-muted">
                <?= icon('shield-halved', 'mt-0.5 shrink-0') ?>
                <?= e(t('help.request.privacy')) ?>
            </p>
        </div>

        <div class="lg:col-span-2 bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
            <?php if ($member === null): ?>
                <div class="flex flex-col items-start gap-4">
                    <p class="text-body-md text-ink"><?= e($isLoggedIn ? t('help.request.no_member') : t('help.request.login_required')) ?></p>
                    <?php if (!$isLoggedIn): ?>
                        <div class="flex flex-wrap gap-3">
                            <?= component('button', ['label' => t('help.request.login'), 'variant' => 'primary', 'href' => '/login']) ?>
                            <?= component('button', ['label' => t('help.request.register'), 'variant' => 'secondary', 'href' => '/register']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($sent): ?>
                    <?= component('alert', ['type' => 'success', 'message' => t('help.request.sent')]) ?>
                <?php elseif ($error('form') !== null): ?>
                    <?= component('alert', ['type' => 'error', 'message' => $error('form')]) ?>
                <?php elseif ($errors !== []): ?>
                    <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
                <?php endif; ?>

                <form method="POST" action="/help" class="flex flex-col gap-5" novalidate>
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <?= component('select_field', [
                            'name' => 'category', 'label' => t('help.field.category'), 'required' => true,
                            'options' => $categoryOptions, 'value' => $field('category'),
                            'placeholder' => t('help.field.category_placeholder'), 'error' => $error('category'),
                        ]) ?>
                        <?= component('form_field', [
                            'name' => 'subject', 'label' => t('help.field.subject'), 'required' => true,
                            'value' => $field('subject'), 'error' => $error('subject'),
                            'placeholder' => t('help.field.subject_placeholder'),
                        ]) ?>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <label for="field-message" class="font-label-lg text-label-lg text-ink">
                                <?= e(t('help.field.message')) ?> <span class="text-danger">*</span>
                            </label>
                            <span class="text-body-sm text-muted"><?= e(t('help.field.message_max', ['max' => $messageMaxLength])) ?></span>
                        </div>
                        <textarea id="field-message" name="message" rows="7" required maxlength="<?= e((string) $messageMaxLength) ?>"
                            placeholder="<?= e(t('help.field.message_placeholder')) ?>"
                            class="<?= e($textareaClasses) ?>"><?= e($field('message')) ?></textarea>
                        <?php if ($error('message') !== null): ?>
                            <p class="text-body-sm text-danger" role="alert"><?= e($error('message')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-end">
                        <?= component('button', ['label' => t('help.request.submit'), 'variant' => 'primary', 'type' => 'submit']) ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($member !== null && $requests !== []): ?>
        <section id="my-requests" aria-labelledby="my-requests-title" class="flex flex-col gap-4">
            <h2 id="my-requests-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('help.mine.title')) ?></h2>
            <ul class="flex flex-col gap-3">
                <?php foreach ($requests as $request): ?>
                    <li class="bg-white rounded-card border border-border p-5 flex flex-col gap-3">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-label-caps font-label-caps uppercase text-muted">
                                    <?= e(\App\Models\HelpRequest::reference($request)) ?> · <?= e(t('help.category.' . $request['category'])) ?>
                                </p>
                                <p class="font-label-lg text-label-lg text-ink"><?= e($request['subject']) ?></p>
                                <p class="text-body-sm text-muted"><?= e(t('help.mine.sent_on', ['date' => local_date($request['created_at'])])) ?></p>
                            </div>
                            <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md <?= e($statusClasses[$request['status']] ?? '') ?>">
                                <?= e(t('help.status.' . $request['status'])) ?>
                            </span>
                        </div>
                        <?php if (($request['response'] ?? '') !== ''): ?>
                            <div class="rounded-control bg-surface p-4">
                                <p class="text-label-md font-label-md text-ink mb-1"><?= e(t('help.mine.response')) ?></p>
                                <p class="text-body-sm text-ink leading-relaxed"><?= nl2br(e($request['response'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
