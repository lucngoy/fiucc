<?php
/**
 * Création/édition d'une candidature (FIUCC-61) — même gabarit pour les
 * deux, comme admin/news/form.php. Le membre n'est proposé qu'à la
 * création ($members, rempli seulement quand $candidate === null) :
 * l'identité du candidat est fixée une fois pour toutes, seul son profil
 * (poste, numéro de bulletin, bio, manifeste, photo) reste modifiable
 * ensuite — voir Admin\CandidateController::update().
 */
$election ??= [];
$candidate ??= null;
$positions ??= [];
$members ??= [];
$old ??= [];
$errors ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $candidate !== null;
$electionId = (int) $election['id'];
$action = $isEdit
    ? '/admin/elections/' . $electionId . '/candidates/' . (int) $candidate['id']
    : '/admin/elections/' . $electionId . '/candidates';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.candidates.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?php if ($isEdit): ?>
            <div class="flex flex-col gap-1.5">
                <span class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.field.member')) ?></span>
                <p class="text-body-md text-ink"><?= e($candidate['full_name']) ?></p>
                <p class="text-body-sm text-muted"><?= e(t('admin.candidates.field.member_fixed_help')) ?></p>
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-1.5">
                <label for="field-member-id" class="font-label-lg text-label-lg text-ink">
                    <?= e(t('admin.candidates.field.member')) ?> <span class="text-danger">*</span>
                </label>
                <select id="field-member-id" name="member_id" class="<?= e($inputClasses($error('member_id'))) ?> bg-white">
                    <option value="">—</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= e((string) $member['id']) ?>" <?= $field('member_id') === (string) $member['id'] ? 'selected' : '' ?>>
                            <?= e($member['full_name']) ?><?= !empty($member['student_id']) ? ' (' . e($member['student_id']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('member_id') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('member_id')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="flex flex-col gap-1.5">
                <label for="field-position" class="font-label-lg text-label-lg text-ink">
                    <?= e(t('admin.candidates.field.position')) ?> <span class="text-danger">*</span>
                </label>
                <select id="field-position" name="election_position_id" class="<?= e($inputClasses($error('election_position_id'))) ?> bg-white">
                    <option value="">—</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= e((string) $position['id']) ?>" <?= $field('election_position_id') === (string) $position['id'] ? 'selected' : '' ?>>
                            <?= e($position['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('election_position_id') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('election_position_id')) ?></p>
                <?php endif; ?>
            </div>

            <?= component('form_field', [
                'name' => 'ballot_number', 'label' => t('admin.candidates.field.ballot_number'), 'type' => 'number',
                'value' => $field('ballot_number_raw'), 'error' => $error('ballot_number'),
                'help' => t('admin.candidates.field.ballot_number_help'),
            ]) ?>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="field-bio" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.field.bio')) ?></label>
            <textarea id="field-bio" name="bio" rows="3"
                class="<?= e($inputClasses($error('bio'))) ?> !h-auto py-2"><?= e($field('bio')) ?></textarea>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="field-manifesto" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.field.manifesto')) ?></label>
            <textarea id="field-manifesto" name="manifesto" rows="6"
                class="<?= e($inputClasses($error('manifesto'))) ?> !h-auto py-2"><?= e($field('manifesto')) ?></textarea>
        </div>

        <div class="flex flex-col gap-5 pt-2 border-t border-border">
            <div class="flex items-center gap-5">
                <?php if ($isEdit && !empty($candidate['photo'])): ?>
                    <img src="<?= e('/uploads/' . $candidate['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover border border-border">
                <?php else: ?>
                    <span class="h-16 w-16 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                        <?= icon('user') ?>
                    </span>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-photo" class="font-label-lg text-label-lg text-ink">
                        <?= e(t($isEdit ? 'admin.candidates.field.photo_new' : 'admin.candidates.field.photo')) ?>
                    </label>
                    <input type="file" id="field-photo" name="photo" accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <?php if ($error('photo') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('photo')) ?></p>
                    <?php else: ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.candidates.field.photo_help')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.candidates.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.candidates.submit.update' : 'admin.candidates.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
