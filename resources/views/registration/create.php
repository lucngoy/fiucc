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
$locale = \App\Core\Lang::locale();
$enLocaleClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frLocaleClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';

$steps = [t('census.step.identity'), t('census.step.studies'), t('census.step.contact'), t('census.step.confirm')];
?>
<style>
    .auth-register-shell {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    @media (min-width: 1024px) {
        .auth-register-shell {
            grid-template-columns: 1.15fr 0.85fr;
            height: 100vh;
        }

        .auth-register-shell > aside {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: hidden;
        }

        .auth-register-panel {
            height: 100vh;
            overflow-y: auto;
            scrollbar-gutter: stable;
        }
    }
</style>
<div class="auth-register-shell min-h-screen grid">
    <aside class="relative hidden lg:flex flex-col justify-center overflow-hidden bg-primary px-10 py-10 xl:px-16 xl:py-12">
        <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
            <g stroke="#3B82F6" stroke-width="1">
                <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
                <path d="M340 20 L420 220 L520 140" />
                <path d="M420 220 L460 320 L280 360 L340 20" />
                <path d="M280 360 L120 60" />
                <path d="M460 320 L620 380 L520 500 L280 360" />
                <path d="M520 500 L560 560 L400 620 L280 360" />
                <path d="M560 560 L700 620 L620 760 L400 620" />
                <path d="M620 760 L640 800 L480 860 L400 620" />
            </g>
            <g fill="#60A5FA">
                <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
                <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
                <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
                <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
                <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
                <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
                <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
            </g>
        </svg>
        <div class="absolute inset-0 bg-gradient-to-r from-[#0A1628] via-[#0A1628]/60 to-transparent"></div>

        <div class="relative z-10">
            <div class="mb-10 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 backdrop-blur">
                <img src="/assets/images/fiucc-seal.png" alt="FIUCC" class="h-6 w-6 rounded-md object-cover ring-1 ring-white/20" />
                <span class="text-xs font-semibold tracking-wide text-white/80"><?= e(t('app.university_name')) ?></span>
            </div>

            <div class="flex flex-col gap-3">
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-white/80">
                    <span class="h-2 w-2 rounded-full bg-[#ECC117]" aria-hidden="true"></span>
                    <?= e(t('census.info.eyebrow')) ?>
                </span>
                <h1 class="font-headline-lg text-headline-lg text-white"><?= e(t('census.info.title')) ?></h1>
                <p class="max-w-md text-base leading-relaxed text-white/80"><?= e(t('census.info.subtitle')) ?></p>
            </div>

            <div class="my-6 flex flex-col gap-4">
                <?php foreach ([
                    ['icon' => 'fa-solid fa-calendar-days', 'title' => t('census.info.benefit1_title'), 'body' => t('census.info.benefit1_body')],
                    ['icon' => 'fa-solid fa-address-book', 'title' => t('census.info.benefit2_title'), 'body' => t('census.info.benefit2_body')],
                    ['icon' => 'fa-solid fa-check-to-slot', 'title' => t('census.info.benefit3_title'), 'body' => t('census.info.benefit3_body')],
                ] as $benefit): ?>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/10" aria-hidden="true">
                            <i class="<?= e($benefit['icon']) ?> text-sm text-gold"></i>
                        </span>
                        <div>
                            <div class="font-label-lg text-label-lg text-white"><?= e($benefit['title']) ?></div>
                            <div class="text-sm leading-relaxed text-white/80"><?= e($benefit['body']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 flex items-start gap-3 rounded-control border border-white/10 bg-white/5 p-3">
                <span class="mt-0.5 text-gold"><?= icon('shield-halved', 'text-lg text-gold') ?></span>
                <p class="text-xs uppercase tracking-[0.12em] text-white/80"><?= e(t('census.info.privacy')) ?></p>
            </div>
        </div>

        <div class="relative z-10 mt-10">
            <a href="/contact" class="flex items-center justify-between gap-3 rounded-control border border-white/10 bg-white/5 p-4 text-left transition-colors hover:bg-white/10">
                <span class="flex items-center gap-3">
                    <?= icon('circle-question', 'text-lg text-white') ?>
                    <span>
                        <span class="block font-label-md text-label-md text-white"><?= e(t('census.info.help_title')) ?></span>
                        <span class="block text-sm text-white/80"><?= e(t('census.info.help_body')) ?></span>
                    </span>
                </span>
                <?= icon('arrow-right', 'text-white/60') ?>
            </a>
        </div>
    </aside>

    <div class="auth-register-panel flex items-center justify-center px-4 py-8 lg:py-12">
        <div class="w-full max-w-md p-6 md:p-8">
            <div class="mb-6 flex justify-end">
                <div class="inline-flex items-center gap-1 rounded-control border border-[#dbe3ee] bg-[#f8fafc] p-1">
                    <a href="/lang/en" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($enLocaleClasses) ?>">EN</a>
                    <a href="/lang/fr" class="px-2 py-1 rounded text-label-md font-label-md transition-colors <?= e($frLocaleClasses) ?>">FR</a>
                </div>
            </div>
            <div class="mb-6 text-center lg:hidden">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#e0f2fe] shadow-sm ring-1 ring-[#dbe3ee] overflow-hidden">
                    <img src="/assets/images/fiucc-seal.png" alt="FIUCC" class="h-full w-full object-cover" />
                </div>
                <h1 class="font-headline-lg text-headline-lg text-ink"><?= e(t('census.title')) ?></h1>
                <p class="mt-2 text-sm text-[#64748b]"><?= e(t('census.subtitle')) ?></p>
            </div>

            <?php if ($errors !== []): ?>
                <div class="mb-12">
                    <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
                </div>
            <?php endif; ?>

            <div id="step-indicator" class="relative mb-8 flex items-center justify-between" aria-hidden="true">
                <div class="absolute left-[12.5%] right-[12.5%] top-[18px] h-0.5 bg-[#e5e7eb]">
                    <div id="step-progress" class="h-full bg-primary transition-all duration-300" style="width: 0%"></div>
                </div>
                <?php foreach ($steps as $index => $stepLabel): ?>
                    <div class="relative flex flex-1 flex-col items-center gap-2">
                        <span data-step-indicator class="flex h-9 w-9 items-center justify-center rounded-full border border-[#dbe3ee] bg-[#f8fafc] text-label-lg font-label-lg text-[#64748b]">
                            <?= e((string) ($index + 1)) ?>
                        </span>
                        <span class="text-label-md font-label-md text-[#64748b]"><?= e($stepLabel) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form id="registration-form" method="POST" action="/register" class="" novalidate>
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

                <div class="flex flex-col gap-4 bg-white rounded-control p-4 border border-border">
                    <h3 class="font-label-lg text-label-lg text-ink"><?= e(t('census.privacy.title')) ?></h3>
                    <?= component('checkbox_field', [
                        'name' => 'is_public', 'label' => t('census.privacy.is_public'),
                        'description' => t('census.privacy.is_public_description'),
                        'checked' => $field('is_public') === '1',
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
