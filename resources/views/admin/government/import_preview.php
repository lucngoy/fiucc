<?php
/**
 * Import d'un comité — étape 2 : aperçu à valider. Chaque ligne montre le
 * profil proposé par App\Services\GovernmentImport et son degré de
 * certitude ; l'admin corrige, coche ou décoche, puis confirme. Le nom
 * d'origine (source_name) repart avec le formulaire : les propositions
 * sont recalculées côté serveur, jamais lues depuis le navigateur.
 */
$mandate ??= [];
$rows ??= [];
$errors ??= [];

$mandateId = (int) $mandate['id'];

$statusBadge = [
    'matched' => ['circle-check', 'bg-success/10 text-success'],
    'check'   => ['triangle-exclamation', 'bg-gold/20 text-ink'],
    'choose'  => ['circle-question', 'bg-primary/10 text-primary'],
    'none'    => ['circle-xmark', 'bg-surface text-muted'],
];

$inputClasses = 'h-[42px] w-full rounded-control border border-border px-3 text-body-md text-ink bg-white focus:outline-none focus:ring-2 focus:border-primary focus:ring-primary-light/50';
?>
<div class="flex flex-col gap-6">
    <a href="/admin/government/mandates/<?= e((string) $mandateId) ?>/import" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.government.import.back_to_form')) ?>
    </a>

    <?php if (isset($errors['form'])): ?>
        <?= component('alert', ['type' => 'error', 'message' => $errors['form']]) ?>
    <?php elseif ($errors !== []): ?>
        <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
    <?php endif; ?>

    <div class="flex flex-col gap-1">
        <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.government.import.preview_heading', ['mandate' => $mandate['mandate']])) ?></h1>
        <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.government.import.preview_intro', ['count' => (string) count($rows)])) ?></p>
    </div>

    <ul class="flex flex-wrap gap-x-5 gap-y-2 text-body-sm text-muted">
        <?php foreach ($statusBadge as $status => [$iconName, $classes]): ?>
            <li class="inline-flex items-center gap-2">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full <?= e($classes) ?>"><?= icon($iconName) ?></span>
                <?= e(t('admin.government.import.status.' . $status . '_help')) ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <form method="POST" action="/admin/government/mandates/<?= e((string) $mandateId) ?>/import" class="flex flex-col gap-3">
        <?= csrf_field() ?>

        <?php foreach ($rows as $index => $row): ?>
            <?php
            [$iconName, $badgeClasses] = $statusBadge[$row['status']];
            $prefix = 'rows[' . $index . ']';
            $rowId = 'import-row-' . $index;
            $rowError = $errors[$index] ?? null;
            ?>
            <div data-import-row class="bg-white rounded-card border <?= $rowError !== null ? 'border-danger' : 'border-border' ?> p-4 grid grid-cols-1 md:grid-cols-[auto_1fr_1fr_1fr] gap-4 md:items-start">
                <input type="hidden" name="<?= e($prefix . '[source_name]') ?>" value="<?= e($row['source_name']) ?>">

                <label class="inline-flex items-center gap-2 md:pt-9">
                    <input type="checkbox" name="<?= e($prefix . '[include]') ?>" value="1" <?= $row['include'] ? 'checked' : '' ?>
                        class="h-5 w-5 rounded border-border text-primary focus:ring-primary-light/50">
                    <span class="md:sr-only text-label-md font-label-md text-ink"><?= e(t('admin.government.import.field.include')) ?></span>
                </label>

                <div class="flex flex-col gap-1.5 min-w-0">
                    <label for="<?= e($rowId . '-member') ?>" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.import.field.member')) ?></label>
                    <select id="<?= e($rowId . '-member') ?>" name="<?= e($prefix . '[member_id]') ?>" data-import-member class="<?= e($inputClasses) ?>">
                        <?php if ($row['status'] === 'choose'): ?>
                            <option value="" <?= $row['member_id'] === null ? 'selected' : '' ?>><?= e(t('admin.government.import.field.member_choose')) ?></option>
                        <?php endif; ?>
                        <?php foreach ($row['candidates'] as $candidate): ?>
                            <option value="<?= e((string) $candidate['id']) ?>" data-name="<?= e($candidate['full_name']) ?>" <?= $row['member_id'] === (int) $candidate['id'] ? 'selected' : '' ?>>
                                <?= e($candidate['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="none" <?= $row['member_id'] === null && ($row['status'] !== 'choose' || !empty($row['none_chosen'])) ? 'selected' : '' ?>><?= e(t('admin.government.import.field.member_none')) ?></option>
                    </select>
                    <p class="inline-flex items-start gap-1.5 text-body-sm">
                        <span class="inline-flex items-center justify-center h-5 w-5 rounded-full shrink-0 <?= e($badgeClasses) ?>"><?= icon($iconName, 'text-[11px]') ?></span>
                        <span class="text-muted"><?= e(t('admin.government.import.status.' . $row['status'], ['name' => $row['source_name']])) ?></span>
                    </p>
                </div>

                <div class="flex flex-col gap-1.5 min-w-0">
                    <label for="<?= e($rowId . '-name') ?>" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.member.field.display_name')) ?></label>
                    <input type="text" id="<?= e($rowId . '-name') ?>" name="<?= e($prefix . '[display_name]') ?>" value="<?= e($row['display_name']) ?>" maxlength="150"
                        data-import-name data-source-name="<?= e($row['source_name']) ?>" class="<?= e($inputClasses) ?>">
                </div>

                <div class="flex flex-col gap-1.5 min-w-0">
                    <label for="<?= e($rowId . '-role') ?>" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.member.field.role')) ?></label>
                    <input type="text" id="<?= e($rowId . '-role') ?>" name="<?= e($prefix . '[role]') ?>" value="<?= e($row['role']) ?>" maxlength="100" class="<?= e($inputClasses) ?>">
                    <?php if ($row['duplicate_role']): ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.government.import.flag.duplicate_role')) ?></p>
                    <?php endif; ?>
                    <?php if ($row['already_in_mandate']): ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.government.import.flag.already_in_mandate')) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($rowError !== null): ?>
                    <p class="md:col-span-4 text-body-sm text-danger" role="alert"><?= e($rowError) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
            <p class="text-body-sm text-muted"><?= e(t('admin.government.import.confirm_help')) ?></p>
            <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
                <?= icon('circle-check') ?>
                <?= e(t('admin.government.import.confirm')) ?>
            </button>
        </div>
    </form>
</div>

<script src="/assets/js/admin-government-import.js" defer></script>
