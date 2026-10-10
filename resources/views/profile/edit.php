<?php
/**
 * Édition du profil et upload de la photo (FIUCC-38). Un seul formulaire,
 * pas d'assistant multi-étapes (contrairement au recensement) : moins de
 * champs, et rien ici ne justifie de le découper.
 */
$old ??= [];
$errors ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$canReplaceScan ??= false;
$passportScanMaxMb ??= 8;
$isRejected = $member['status'] === 'rejected';

// Fiche étendue (migration 018) — mêmes listes que le recensement.
$labelled = static fn (array $keys, string $prefix): array => array_combine($keys, array_map(static fn (string $key): string => t($prefix . $key), $keys));
$communityRoleOptions = $labelled($communityRoles ?? [], 'census.community_role.');
$districtOptions = $labelled($districts ?? [], 'census.district.');
$religionOptions = $labelled($religions ?? [], 'census.religion.');
?>
<div class="max-w-layout mx-auto px-4 py-12 md:py-16">
    <div class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('profile.edit_title')) ?></h1>
        <p class="text-body-md text-muted"><?= e(t('profile.edit_subtitle')) ?></p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <?php if ($isRejected): ?>
        <!-- Dossier refusé : motif rappelé ici, enregistrer renvoie le dossier
             aux admins (ProfileController::update(), Member::resubmit()). -->
        <div class="mb-8 flex flex-col gap-2">
            <?= component('alert', ['type' => 'error', 'message' => t('profile.resubmit.intro')]) ?>
            <?php if (!empty($member['rejection_reason'])): ?>
                <p class="pl-4 text-body-sm text-ink"><?= e(t('profile.status.reason', ['reason' => $member['rejection_reason']])) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/profile" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-8">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('profile.photo_section')) ?></h2>

            <div class="flex items-center gap-5">
                <?php if (!empty($member['photo'])): ?>
                    <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover border border-border">
                <?php else: ?>
                    <span class="h-16 w-16 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                        <?= e(mb_substr($member['full_name'], 0, 1)) ?>
                    </span>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-photo" class="font-label-lg text-label-lg text-ink"><?= e(t('profile.photo_new')) ?></label>
                    <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <p class="text-body-sm text-muted"><?= e(t('profile.photo_help')) ?></p>
                    <?php if ($error('photo') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('photo')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.identity')) ?></h2>

            <?php if (($member['last_name'] ?? null) === null): ?>
                <!-- Inscrit avant la migration 018 : full_name n'est pas
                     découpé automatiquement, le membre complète lui-même. -->
                <p class="text-body-sm text-muted"><?= e(t('profile.complete_census_notice', ['name' => $member['full_name']])) ?></p>
            <?php endif; ?>

            <?= component('form_field', [
                'name' => 'last_name', 'label' => t('census.field.last_name'), 'required' => true,
                'value' => $field('last_name'), 'error' => $error('last_name'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'middle_name', 'label' => t('census.field.middle_name'), 'required' => true,
                'value' => $field('middle_name'), 'error' => $error('middle_name'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'first_name', 'label' => t('census.field.first_name'), 'required' => true,
                'value' => $field('first_name'), 'error' => $error('first_name'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'date_of_birth', 'label' => t('census.field.date_of_birth'), 'type' => 'date', 'required' => true,
                'value' => $field('date_of_birth'), 'error' => $error('date_of_birth'),
            ]) ?>

            <div class="flex flex-col gap-1.5">
                <span class="font-label-lg text-label-lg text-ink"><?= e(t('census.field.gender')) ?> <span class="text-danger">*</span></span>
                <div class="flex gap-4">
                    <?php foreach (['male' => t('census.gender.male'), 'female' => t('census.gender.female')] as $genderValue => $genderLabel): ?>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="gender" value="<?= e($genderValue) ?>" <?= $field('gender') === $genderValue ? 'checked' : '' ?> required class="h-4 w-4 border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                            <span class="text-body-sm text-ink"><?= e($genderLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($error('gender') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('gender')) ?></p>
                <?php endif; ?>
            </div>

            <?= component('form_field', [
                'name' => 'city_of_origin', 'label' => t('census.field.city_of_origin'), 'required' => true,
                'value' => $field('city_of_origin'), 'error' => $error('city_of_origin'),
            ]) ?>

            <?= component('select_field', [
                'name' => 'community_role', 'label' => t('census.field.community_role'), 'required' => true,
                'options' => $communityRoleOptions, 'value' => $field('community_role'), 'error' => $error('community_role'),
                'placeholder' => t('census.field.community_role_placeholder'),
            ]) ?>

            <?= component('select_field', [
                'name' => 'religion', 'label' => t('census.field.religion'), 'required' => true,
                'options' => $religionOptions, 'placeholder' => t('census.field.religion_placeholder'),
                'value' => $field('religion'), 'error' => $error('religion'),
                'help' => t('census.field.religion_help'),
            ]) ?>
        </div>

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
            ]) ?>

            <?= component('select_field', [
                'name' => 'year', 'label' => t('census.field.year'), 'required' => true,
                'options' => $studyLevels, 'value' => $field('year'), 'error' => $error('year'),
                'placeholder' => t('census.field.year_placeholder'),
            ]) ?>

            <p class="text-body-sm text-muted">
                <?= e(t('profile.student_id_locked', ['id' => $member['student_id']])) ?>
            </p>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.contact')) ?></h2>

            <?= component('form_field', [
                'name' => 'phone', 'label' => t('census.field.phone'), 'required' => true, 'type' => 'tel',
                'value' => $field('phone'), 'error' => $error('phone'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'whatsapp', 'label' => t('census.field.whatsapp'), 'type' => 'tel', 'required' => true,
                'value' => $field('whatsapp'), 'error' => $error('whatsapp'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'instagram', 'label' => t('census.field.instagram'),
                'value' => $field('instagram'), 'error' => $error('instagram'),
                'placeholder' => t('census.field.instagram_placeholder'),
            ]) ?>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.residence')) ?></h2>

            <?= component('form_field', [
                'name' => 'address_street', 'label' => t('census.field.address_street'), 'required' => true,
                'value' => $field('address_street'), 'error' => $error('address_street'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'address_building', 'label' => t('census.field.address_building'), 'required' => true,
                'value' => $field('address_building'), 'error' => $error('address_building'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'address_apartment', 'label' => t('census.field.address_apartment'), 'required' => true,
                'value' => $field('address_apartment'), 'error' => $error('address_apartment'),
            ]) ?>

            <?= component('select_field', [
                'name' => 'district', 'label' => t('census.field.district'), 'required' => true,
                'options' => $districtOptions, 'value' => $field('district'), 'error' => $error('district'),
                'placeholder' => t('census.field.district_placeholder'),
            ]) ?>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('census.section.passport')) ?></h2>

            <?= component('form_field', [
                'name' => 'passport_number', 'label' => t('census.field.passport_number'), 'required' => true,
                'value' => $field('passport_number'), 'error' => $error('passport_number'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'passport_expiry', 'label' => t('census.field.passport_expiry'), 'type' => 'date', 'required' => true,
                'value' => $field('passport_expiry'), 'error' => $error('passport_expiry'),
            ]) ?>

            <?php if ($canReplaceScan): ?>
                <!-- Photo du passeport : remplaçable tant que le dossier n'est
                     pas validé ; obligatoire pour renvoyer un dossier refusé
                     qui n'en a plus (supprimée 90 jours après la décision). -->
                <div class="flex flex-col gap-1.5">
                    <label for="field-passport_scan" class="font-label-lg text-label-lg text-ink">
                        <?= e(t('census.field.passport_scan')) ?><?= $isRejected && empty($member['passport_scan']) ? ' <span class="text-danger">*</span>' : '' ?>
                    </label>
                    <input type="file" id="field-passport_scan" name="passport_scan" accept="image/jpeg,image/png,image/webp"
                        <?= $isRejected && empty($member['passport_scan']) ? 'required' : '' ?>
                        class="block w-full text-body-sm text-ink file:mr-3 file:h-10 file:px-4 file:rounded-control file:border-0 file:bg-primary/[4%] file:text-primary file:font-label-md hover:file:bg-primary/10">
                    <?php if ($error('passport_scan') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('passport_scan')) ?></p>
                    <?php else: ?>
                        <p class="text-body-sm text-muted">
                            <?= e(empty($member['passport_scan'])
                                ? t('profile.passport_scan.missing', ['max' => $passportScanMaxMb])
                                : t('profile.passport_scan.present', ['max' => $passportScanMaxMb])) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="text-body-sm text-muted"><?= e(t('census.section.passport_notice')) ?></p>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <?= component('button', ['label' => t('profile.cancel'), 'variant' => 'secondary', 'href' => '/profile']) ?>
            <?= component('button', ['label' => $isRejected ? t('profile.resubmit.button') : t('profile.save'), 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
