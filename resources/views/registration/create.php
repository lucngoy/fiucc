<?php
/**
 * Recensement multi-étapes (FIUCC-31/32) — sections 13/14 du cahier des
 * charges + maquette fiucc_portal_member_registration_census.
 *
 * Un seul <form>, une seule soumission POST /register : les étapes sont une
 * navigation JS (public/assets/js/registration-wizard.js) sur des sections
 * cachées/affichées, pas des brouillons persistés côté serveur (décidé avec
 * l'utilisateur — pas de "save draft" façon maquette, non demandé par le
 * cahier des charges et incompatible avec les colonnes NOT NULL de members).
 *
 * $old / $errors : valeurs déjà saisies et erreurs de validation, pour
 * réafficher le formulaire rempli après un échec de FIUCC-32 — vides au
 * premier chargement (RegistrationController::create()).
 *
 * Carte d'information à gauche (lg+ uniquement) : reprise de la maquette
 * fiucc_portal_member_registration_census/code.html, mais seulement des
 * avantages réellement construits — annuaire (/directory), événements
 * (/events), élections (/elections), toutes des routes existantes
 * (routes/web.php) — pas de citation présidentielle ni d'email de
 * secrétariat inventés ; le renvoi d'aide pointe vers /contact, route
 * réelle. Le titre/sous-titre du haut, dupliqués dans la carte, ne
 * s'affichent que sous lg (la carte les remplace visuellement au-dessus).
 */
$old ??= [];
$errors ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;

$steps = [t('census.step.identity'), t('census.step.studies'), t('census.step.contact'), t('census.step.confirm')];
?>
<div class="max-w-layout mx-auto px-4 py-12 md:px-6 md:py-16">
    <div class="max-w-2xl mx-auto text-center mb-10 lg:hidden">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('census.title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('census.subtitle')) ?></p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
        <!-- Carte d'information — lg+ uniquement, remplace le titre du haut
             (masqué en lg via lg:hidden ci-dessus). -->
        <aside class="hidden lg:flex lg:col-span-5 lg:sticky lg:top-24 flex-col justify-between gap-8 bg-primary text-white rounded-card shadow-card p-8">
            <div class="flex flex-col gap-6">
                <span class="inline-flex items-center gap-2 self-start px-3 h-7 rounded-full bg-ink/25 text-label-caps font-label-caps uppercase tracking-wider">
                    <span class="h-2 w-2 rounded-full bg-gold" aria-hidden="true"></span>
                    <?= e(t('census.info.eyebrow')) ?>
                </span>
                <div class="flex flex-col gap-2">
                    <h1 class="font-headline-lg text-headline-lg"><?= e(t('census.info.title')) ?></h1>
                    <p class="text-body-md text-white/90"><?= e(t('census.info.subtitle')) ?></p>
                </div>
                <div class="flex flex-col gap-4 pt-2">
                    <?php foreach ([
                        ['icon' => 'fa-solid fa-calendar-days', 'title' => t('census.info.benefit1_title'), 'body' => t('census.info.benefit1_body')],
                        ['icon' => 'fa-solid fa-address-book', 'title' => t('census.info.benefit2_title'), 'body' => t('census.info.benefit2_body')],
                        ['icon' => 'fa-solid fa-check-to-slot', 'title' => t('census.info.benefit3_title'), 'body' => t('census.info.benefit3_body')],
                    ] as $benefit): ?>
                        <div class="flex items-start gap-3">
                            <span class="h-6 w-6 rounded-full bg-ink/25 flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                <i class="<?= e($benefit['icon']) ?> text-gold text-sm"></i>
                            </span>
                            <div class="flex flex-col">
                                <span class="font-label-lg text-label-lg"><?= e($benefit['title']) ?></span>
                                <span class="text-body-sm text-white"><?= e($benefit['body']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-control bg-white/10">
                    <?= icon('shield-halved', 'text-gold mt-0.5') ?>
                    <p class="text-label-caps font-label-caps text-white leading-relaxed"><?= e(t('census.info.privacy')) ?></p>
                </div>
            </div>

            <a href="/contact" class="flex items-center justify-between gap-3 p-4 rounded-control bg-white/10 hover:bg-ink/25 transition-colors">
                <span class="flex items-center gap-3">
                    <?= icon('circle-question', 'text-lg') ?>
                    <span class="flex flex-col">
                        <span class="font-label-md text-label-md"><?= e(t('census.info.help_title')) ?></span>
                        <span class="text-body-sm text-white"><?= e(t('census.info.help_body')) ?></span>
                    </span>
                </span>
                <?= icon('arrow-right', 'text-white/50') ?>
            </a>
        </aside>

        <div class="lg:col-span-7">
            <?php if ($errors !== []): ?>
                <div class="max-w-2xl mx-auto lg:max-w-none mb-8">
                    <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
                </div>
            <?php endif; ?>

            <!-- Indicateur d'étapes -->
            <div id="step-indicator" class="max-w-2xl mx-auto lg:max-w-none relative flex items-center justify-between mb-8" aria-hidden="true">
                <!-- Ligne reliant les cercles, centrée sur leur milieu (h-9 =
                     36px) et bornée à leurs centres sur 4 colonnes égales
                     (12.5% = 100%/4/2) — à ajuster si $steps change de
                     longueur. Placée avant les cercles dans le DOM et sous
                     "relative" comme eux : ceux-ci, étant positionnés,
                     peignent par-dessus dans l'ordre du DOM, donc la ligne
                     reste visible seulement entre eux, pas dessus. -->
                <div class="absolute left-[12.5%] right-[12.5%] top-[18px] h-0.5 bg-border">
                    <div id="step-progress" class="h-full bg-primary transition-all duration-300" style="width: 0%"></div>
                </div>
                <?php foreach ($steps as $index => $stepLabel): ?>
                    <div class="relative flex flex-col items-center gap-2 flex-1">
                        <span data-step-indicator class="h-9 w-9 flex items-center justify-center rounded-full font-label-lg text-label-lg bg-surface text-muted border border-border">
                            <?= e((string) ($index + 1)) ?>
                        </span>
                        <span class="text-label-md font-label-md text-muted"><?= e($stepLabel) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form id="registration-form" method="POST" action="/register" class="max-w-2xl mx-auto lg:max-w-none bg-white rounded-card border border-border shadow-card p-6 md:p-8 flex flex-col gap-8" novalidate>
                <?= csrf_field() ?>

        <!-- Étape 1 : Identité -->
        <div data-step="1" class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.identity')) ?></h2>

                <?= component('form_field', [
                    'name' => 'full_name', 'label' => t('census.field.full_name'), 'required' => true,
                    'value' => $field('full_name'), 'error' => $error('full_name'),
                    'placeholder' => t('census.field.full_name_placeholder'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'date_of_birth', 'label' => t('census.field.date_of_birth'), 'type' => 'date',
                    'value' => $field('date_of_birth'), 'error' => $error('date_of_birth'),
                ]) ?>

                <div class="flex flex-col gap-1.5">
                    <span class="font-label-lg text-label-lg text-ink"><?= e(t('census.field.gender')) ?></span>
                    <div class="flex gap-4">
                        <?php foreach (['male' => t('census.gender.male'), 'female' => t('census.gender.female'), 'other' => t('census.gender.other')] as $genderValue => $genderLabel): ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="gender" value="<?= e($genderValue) ?>" <?= $field('gender') === $genderValue ? 'checked' : '' ?> class="h-4 w-4 border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                                <span class="text-body-sm text-ink"><?= e($genderLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?= component('form_field', [
                    'name' => 'city_of_origin', 'label' => t('census.field.city_of_origin'),
                    'value' => $field('city_of_origin'), 'error' => $error('city_of_origin'),
                    'placeholder' => t('census.field.city_of_origin_placeholder'),
                ]) ?>
            </div>

            <div class="flex justify-end pt-6">
                <?= component('button', ['label' => t('census.next_studies'), 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 2 : Études -->
        <div data-step="2" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.studies')) ?></h2>

                <?= component('select_field', [
                    'name' => 'faculty', 'label' => t('census.field.faculty'), 'required' => true,
                    'options' => $faculties, 'value' => $field('faculty'), 'error' => $error('faculty'),
                    'placeholder' => t('census.field.faculty_placeholder'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'department', 'label' => t('census.field.department'), 'required' => true,
                    'value' => $field('department'), 'error' => $error('department'),
                    'placeholder' => t('census.field.department_placeholder'),
                ]) ?>

                <?= component('select_field', [
                    'name' => 'year', 'label' => t('census.field.year'), 'required' => true,
                    'options' => $studyLevels, 'value' => $field('year'), 'error' => $error('year'),
                    'placeholder' => t('census.field.year_placeholder'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'student_id', 'label' => t('census.field.student_id'), 'required' => true,
                    'value' => $field('student_id'), 'error' => $error('student_id'),
                    'placeholder' => t('census.field.student_id_placeholder'),
                ]) ?>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => t('census.back'), 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => t('census.next_contact'), 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 3 : Contact et compte -->
        <div data-step="3" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.contact')) ?></h2>

                <?= component('form_field', [
                    'name' => 'email', 'label' => t('census.field.email'), 'type' => 'email', 'required' => true,
                    'value' => $field('email'), 'error' => $error('email'),
                    'placeholder' => 'jonathan.mukendi@final.edu.tr',
                    'help' => t('census.field.email_help'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'password', 'label' => t('census.field.password'), 'type' => 'password', 'required' => true,
                    'error' => $error('password'),
                    'help' => t('census.field.password_help', ['min' => $passwordMinLength]),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'password_confirmation', 'label' => t('census.field.password_confirmation'), 'type' => 'password', 'required' => true,
                    'error' => $error('password_confirmation'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'phone', 'label' => t('census.field.phone'), 'type' => 'tel', 'required' => true,
                    'value' => $field('phone'), 'error' => $error('phone'),
                    'placeholder' => '+90 533 800 00 00',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'whatsapp', 'label' => t('census.field.whatsapp'), 'type' => 'tel',
                    'value' => $field('whatsapp'), 'error' => $error('whatsapp'),
                    'placeholder' => t('census.field.whatsapp_placeholder'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'instagram', 'label' => t('census.field.instagram'),
                    'value' => $field('instagram'), 'error' => $error('instagram'),
                    'placeholder' => t('census.field.instagram_placeholder'),
                ]) ?>

                <div class="flex flex-col gap-4 bg-surface rounded-control p-4 border border-border">
                    <h3 class="font-label-lg text-label-lg text-ink"><?= e(t('census.privacy.title')) ?></h3>
                    <?= component('checkbox_field', [
                        'name' => 'is_public', 'label' => t('census.privacy.is_public'),
                        'description' => t('census.privacy.is_public_description'),
                        'checked' => $old === [] ? true : ($field('is_public') === '1'),
                    ]) ?>
                    <?= component('checkbox_field', [
                        'name' => 'show_email', 'label' => t('census.privacy.show_email'),
                        'checked' => $field('show_email') === '1',
                    ]) ?>
                    <?= component('checkbox_field', [
                        'name' => 'show_phone', 'label' => t('census.privacy.show_phone'),
                        'checked' => $field('show_phone') === '1',
                    ]) ?>
                </div>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => t('census.back'), 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => t('census.next_confirm'), 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 4 : Confirmation -->
        <div data-step="4" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.confirm')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('census.confirm_notice')) ?></p>

                <?= component('checkbox_field', [
                    'name' => 'consent', 'label' => t('census.consent'),
                    'required' => true, 'checked' => $field('consent') === '1', 'error' => $error('consent'),
                ]) ?>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => t('census.back'), 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => t('census.submit'), 'variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/registration-wizard.js" defer></script>
