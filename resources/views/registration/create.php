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
 */
$old ??= [];
$errors ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;

$steps = [t('census.step.identity'), t('census.step.studies'), t('census.step.contact'), t('census.step.confirm')];
?>
<div class="max-w-layout mx-auto px-4 py-12 md:py-16">
    <div class="max-w-2xl mx-auto text-center mb-10">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('census.title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('census.subtitle')) ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="max-w-2xl mx-auto mb-8">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <!-- Indicateur d'étapes -->
    <div id="step-indicator" class="max-w-2xl mx-auto flex items-center justify-between mb-8" aria-hidden="true">
        <?php foreach ($steps as $index => $stepLabel): ?>
            <div class="flex flex-col items-center gap-2 flex-1">
                <span data-step-indicator class="h-9 w-9 flex items-center justify-center rounded-full font-label-lg text-label-lg bg-surface text-muted border border-border">
                    <?= e((string) ($index + 1)) ?>
                </span>
                <span class="text-label-md font-label-md text-muted"><?= e($stepLabel) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <form id="registration-form" method="POST" action="/register" class="max-w-2xl mx-auto bg-white rounded-card border border-border shadow-card p-6 md:p-8 flex flex-col gap-8" novalidate>
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

<script src="/assets/js/registration-wizard.js" defer></script>
