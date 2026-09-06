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
?>
<div class="max-w-2xl mx-auto px-4 py-12 md:py-16">
    <div class="mb-8">
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3">Edit profile</h1>
        <p class="text-body-md text-muted">Update your contact details and academic information.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="mb-8">
            <?= component('alert', ['type' => 'error', 'message' => 'Please correct the errors below before submitting again.']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/profile" enctype="multipart/form-data" class="bg-white rounded-card border border-border shadow-card p-6 md:p-8 flex flex-col gap-8">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink">Photo</h2>

            <div class="flex items-center gap-5">
                <?php if (!empty($member['photo'])): ?>
                    <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover border border-border">
                <?php else: ?>
                    <span class="h-16 w-16 rounded-full bg-primary/15 text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                        <?= e(mb_substr($member['full_name'], 0, 1)) ?>
                    </span>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-photo" class="font-label-lg text-label-lg text-ink">New photo</label>
                    <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <p class="text-body-sm text-muted">JPG, PNG or WEBP. Leave empty to keep your current photo.</p>
                    <?php if ($error('photo') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('photo')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink">Personal Identity</h2>

            <?= component('form_field', [
                'name' => 'full_name', 'label' => 'Full Name', 'required' => true,
                'value' => $field('full_name'), 'error' => $error('full_name'),
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
            ]) ?>
        </div>

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
            ]) ?>

            <?= component('select_field', [
                'name' => 'year', 'label' => 'Current Year of Study', 'required' => true,
                'options' => $studyLevels, 'value' => $field('year'), 'error' => $error('year'),
                'placeholder' => 'Select current academic level',
            ]) ?>

            <p class="text-body-sm text-muted">
                Student ID: <span class="font-label-md text-ink"><?= e($member['student_id']) ?></span> — cannot be changed here.
            </p>
        </div>

        <div class="flex flex-col gap-5">
            <h2 class="font-headline-sm text-headline-sm text-ink">Contact Details</h2>

            <?= component('form_field', [
                'name' => 'phone', 'label' => 'Phone Number', 'required' => true, 'type' => 'tel',
                'value' => $field('phone'), 'error' => $error('phone'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel',
                'value' => $field('whatsapp'), 'error' => $error('whatsapp'),
            ]) ?>

            <?= component('form_field', [
                'name' => 'instagram', 'label' => 'Instagram Handle',
                'value' => $field('instagram'), 'error' => $error('instagram'),
                'placeholder' => 'e.g., jonathan.mukendi',
            ]) ?>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <?= component('button', ['label' => 'Cancel', 'variant' => 'secondary', 'href' => '/profile']) ?>
            <?= component('button', ['label' => 'Save changes', 'variant' => 'primary', 'type' => 'submit']) ?>
        </div>
    </form>
</div>
