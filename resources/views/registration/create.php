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

$steps = ['Identity', 'Studies', 'Contact', 'Confirm'];
?>
<div class="max-w-layout mx-auto px-4 py-12 md:py-16">
    <div class="max-w-2xl mx-auto text-center mb-10">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3">Join the Community</h1>
        <p class="text-body-md text-muted">Register as a member of the Congolese Community of Final International University.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="max-w-2xl mx-auto mb-8">
            <?= component('alert', ['type' => 'error', 'message' => 'Please correct the errors below before submitting again.']) ?>
        </div>
    <?php endif; ?>

    <!-- Indicateur d'étapes -->
    <div class="max-w-2xl mx-auto flex items-center justify-between mb-8" aria-hidden="true">
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
                <h2 class="font-headline-sm text-headline-sm text-ink">Personal Identity</h2>

                <?= component('form_field', [
                    'name' => 'full_name', 'label' => 'Full Name', 'required' => true,
                    'value' => $field('full_name'), 'error' => $error('full_name'),
                    'placeholder' => 'e.g., Jonathan Mukendi',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date',
                    'value' => $field('date_of_birth'), 'error' => $error('date_of_birth'),
                ]) ?>

                <div class="flex flex-col gap-1.5">
                    <span class="font-label-lg text-label-lg text-ink">Gender</span>
                    <div class="flex gap-4">
                        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Prefer not to say'] as $genderValue => $genderLabel): ?>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="gender" value="<?= e($genderValue) ?>" <?= $field('gender') === $genderValue ? 'checked' : '' ?> class="h-4 w-4 border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                                <span class="text-body-sm text-ink"><?= e($genderLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?= component('form_field', [
                    'name' => 'city_of_origin', 'label' => 'City / Province of Origin',
                    'value' => $field('city_of_origin'), 'error' => $error('city_of_origin'),
                    'placeholder' => 'e.g., Kinshasa',
                ]) ?>
            </div>

            <div class="flex justify-end pt-6">
                <?= component('button', ['label' => 'Next: Studies', 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 2 : Études -->
        <div data-step="2" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink">Academic Studies at Final</h2>

                <?= component('select_field', [
                    'name' => 'faculty', 'label' => 'University Faculty', 'required' => true,
                    'options' => $faculties, 'value' => $field('faculty'), 'error' => $error('faculty'),
                    'placeholder' => 'Select faculty',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'department', 'label' => 'Department / Degree Program', 'required' => true,
                    'value' => $field('department'), 'error' => $error('department'),
                    'placeholder' => 'e.g., Computer Engineering',
                ]) ?>

                <?= component('select_field', [
                    'name' => 'year', 'label' => 'Current Year of Study', 'required' => true,
                    'options' => $studyLevels, 'value' => $field('year'), 'error' => $error('year'),
                    'placeholder' => 'Select current academic level',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'student_id', 'label' => 'Student ID Number at FIU', 'required' => true,
                    'value' => $field('student_id'), 'error' => $error('student_id'),
                    'placeholder' => 'e.g., 20241890',
                ]) ?>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => 'Back', 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => 'Next: Contact', 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 3 : Contact et compte -->
        <div data-step="3" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink">Contact Details</h2>

                <?= component('form_field', [
                    'name' => 'email', 'label' => 'University Email', 'type' => 'email', 'required' => true,
                    'value' => $field('email'), 'error' => $error('email'),
                    'placeholder' => 'jonathan.mukendi@final.edu.tr',
                    'help' => 'A verification code will be sent to this address after you register.',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true,
                    'error' => $error('password'),
                    'help' => 'At least ' . $passwordMinLength . ' characters.',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'password_confirmation', 'label' => 'Confirm Password', 'type' => 'password', 'required' => true,
                    'error' => $error('password_confirmation'),
                ]) ?>

                <?= component('form_field', [
                    'name' => 'phone', 'label' => 'Mobile Phone Number', 'type' => 'tel', 'required' => true,
                    'value' => $field('phone'), 'error' => $error('phone'),
                    'placeholder' => '+90 533 800 00 00',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel',
                    'value' => $field('whatsapp'), 'error' => $error('whatsapp'),
                    'placeholder' => 'Optional, if different from phone',
                ]) ?>

                <?= component('form_field', [
                    'name' => 'instagram', 'label' => 'Instagram Handle',
                    'value' => $field('instagram'), 'error' => $error('instagram'),
                    'placeholder' => '@username (optional)',
                ]) ?>

                <div class="flex flex-col gap-4 bg-surface rounded-control p-4 border border-border">
                    <h3 class="font-label-lg text-label-lg text-ink">Directory & Privacy Settings</h3>
                    <?= component('checkbox_field', [
                        'name' => 'is_public', 'label' => 'Show my profile in the member directory',
                        'description' => 'Allows other Congolese members to find your faculty and year of study.',
                        'checked' => $old === [] ? true : ($field('is_public') === '1'),
                    ]) ?>
                    <?= component('checkbox_field', [
                        'name' => 'show_email', 'label' => 'Show my email to other registered members',
                        'checked' => $field('show_email') === '1',
                    ]) ?>
                    <?= component('checkbox_field', [
                        'name' => 'show_phone', 'label' => 'Show my phone number to other registered members',
                        'checked' => $field('show_phone') === '1',
                    ]) ?>
                </div>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => 'Back', 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => 'Next: Confirm', 'variant' => 'primary', 'attributes' => 'data-next']) ?>
            </div>
        </div>

        <!-- Étape 4 : Confirmation -->
        <div data-step="4" hidden class="scroll-mt-[88px]">
            <div class="flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink">Confirm your registration</h2>
                <p class="text-body-sm text-muted">Please review your information on the previous steps before submitting.</p>

                <?= component('checkbox_field', [
                    'name' => 'consent', 'label' => 'I certify that I am a current Congolese student at Final International University and agree to the Community Charter and Data Protection Policy.',
                    'required' => true, 'checked' => $field('consent') === '1',
                ]) ?>
            </div>

            <div class="flex justify-between pt-6">
                <?= component('button', ['label' => 'Back', 'variant' => 'secondary', 'attributes' => 'data-prev']) ?>
                <?= component('button', ['label' => 'Submit my registration', 'variant' => 'primary', 'type' => 'submit']) ?>
            </div>
        </div>
    </form>
</div>

<script src="/assets/js/registration-wizard.js" defer></script>
