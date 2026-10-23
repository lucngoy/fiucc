<?php
/**
 * "Ma candidature" (lot 3 ; règlement électoral art. 9-14) — dépôt,
 * modification et suivi d'une candidature par le membre lui-même. Voir
 * App\Controllers\CandidacyController pour les états ($state) et les
 * gardes. Parcours et plan d'action sont publics une fois la candidature
 * approuvée (page des candidats) ; les pièces du dossier ne le sont jamais.
 */
$election ??= [];
$candidacy ??= null;
$positions ??= [];
$state ??= 'closed';
$present ??= [];
$rules ??= null;
$old ??= [];
$errors ??= [];
$notice ??= '';
$limits ??= ['bio_min' => 30, 'manifesto_min' => 50];

$electionId = (int) $election['id'];
$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$canEdit = in_array($state, ['open', 'editable'], true);
$feeRequired = (bool) $election['candidacy_fee_required'];

$noticeMap = [
    'submitted' => ['type' => 'success', 'message' => t('candidacy.notice.submitted')],
    'updated'   => ['type' => 'success', 'message' => t('candidacy.notice.updated')],
    'cancelled' => ['type' => 'success', 'message' => t('candidacy.notice.cancelled')],
    'corrected' => ['type' => 'success', 'message' => t('candidacy.notice.corrected')],
    'locked'    => ['type' => 'error',   'message' => t('candidacy.notice.locked')],
];

$statusBadge = [
    'pending'   => 'bg-gold/20 text-ink',
    'correction' => 'bg-gold/20 text-ink',
    'approved'  => 'bg-success/[4%] text-success',
    'rejected'  => 'bg-danger/10 text-danger',
    'withdrawn' => 'bg-surface text-muted border border-border',
];

$accept = [
    'image'        => 'image/jpeg,image/png,image/webp',
    'pdf'          => 'application/pdf',
    'image_or_pdf' => 'image/jpeg,image/png,image/webp,application/pdf',
];

$textareaClasses = static function (?string $message): string {
    return 'w-full rounded-control border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($message !== null ? 'border-danger focus:border-danger focus:ring-danger/30' : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-14 flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="<?= e('/elections/' . $electionId) ?>" class="inline-flex w-fit items-center gap-2 text-label-md font-label-md text-muted hover:text-ink transition-colors">
            <?= icon('arrow-left') ?>
            <?= e($election['title']) ?>
        </a>
        <h1 class="font-headline-lg text-headline-lg text-ink"><?= e(t('candidacy.heading')) ?></h1>
        <?php if (!empty($election['candidacy_deadline'])): ?>
            <p class="text-body-md text-muted"><?= e(t('candidacy.deadline', [
                'date' => local_date($election['candidacy_deadline']),
                'time' => date('H:i', strtotime((string) $election['candidacy_deadline'])),
            ])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <?php if ($error('form') !== null): ?>
        <?= component('alert', ['type' => 'error', 'message' => $error('form')]) ?>
    <?php elseif ($errors !== []): ?>
        <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
    <?php endif; ?>

    <?php if ($candidacy !== null): ?>
        <!-- Suivi : le statut de SA candidature, rien de lié au vote. -->
        <section class="bg-white rounded-card border border-border p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-label-lg text-label-lg text-ink"><?= e($candidacy['position_name']) ?></span>
                    <span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md <?= e($statusBadge[$candidacy['status']] ?? $statusBadge['pending']) ?>">
                        <?= e(t('candidacy.status.' . $candidacy['status'])) ?>
                    </span>
                </div>
                <?php
                $helpKey = $candidacy['status'] === 'correction'
                    ? ($state === 'editable' ? 'correction' : 'correction_expired')
                    : ($state === 'editable' ? 'editable' : $candidacy['status']);
                ?>
                <p class="text-body-sm text-muted"><?= e(t('candidacy.status_help.' . $helpKey)) ?></p>
                <?php if (in_array($candidacy['status'], ['correction', 'rejected'], true) && !empty($candidacy['decision_reason'])): ?>
                    <!-- Motif de la Commission (lot 4) : seule information de décision montrée. -->
                    <p class="text-body-sm text-ink whitespace-pre-line"><span class="font-label-md"><?= e(t('candidacy.reason_label')) ?></span> <?= e($candidacy['decision_reason']) ?></p>
                <?php endif; ?>
                <?php if ($candidacy['status'] === 'correction' && !empty($candidacy['correction_deadline'])): ?>
                    <p class="text-body-sm font-label-md text-ink"><?= e(t('candidacy.correction_until', [
                        'date' => local_date($candidacy['correction_deadline']),
                        'time' => date('H:i', strtotime((string) $candidacy['correction_deadline'])),
                    ])) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($state === 'editable'): ?>
                <button type="button" data-open-dialog="cancel-candidacy-dialog"
                    class="h-10 px-4 rounded-control border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
                    <?= e(t('candidacy.cancel')) ?>
                </button>
                <dialog id="cancel-candidacy-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                    <form method="POST" action="<?= e('/elections/' . $electionId . '/candidacy/cancel') ?>" class="flex flex-col gap-2 p-4 text-left">
                        <?= csrf_field() ?>
                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('candidacy.cancel')) ?></h2>
                        <p class="text-body-sm text-muted"><?= e(t('candidacy.cancel_confirm')) ?></p>
                        <div class="flex justify-end gap-2 mt-1">
                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('candidacy.keep')) ?></button>
                            <button type="submit" class="h-9 px-4 rounded-control bg-ink text-white text-label-md font-label-md hover:bg-ink/90 transition-colors"><?= e(t('candidacy.cancel')) ?></button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
        </section>
    <?php elseif (!$canEdit): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('candidacy.unavailable.' . $state)]) ?>
    <?php endif; ?>

    <?php if ($canEdit): ?>
        <form method="POST" action="<?= e('/elections/' . $electionId . '/candidacy') ?>" enctype="multipart/form-data" class="flex flex-col gap-6" novalidate>
            <?= csrf_field() ?>

            <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('candidacy.section.project')) ?></h2>

                <?= component('select_field', [
                    'name' => 'position_id', 'label' => t('candidacy.field.position'), 'required' => true,
                    'options' => array_column($positions, 'name', 'id'), 'value' => $field('position_id'),
                    'error' => $error('position_id'), 'placeholder' => t('candidacy.field.position_placeholder'),
                ]) ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-bio" class="font-label-lg text-label-lg text-ink"><?= e(t('candidacy.field.bio')) ?> <span class="text-danger">*</span></label>
                    <textarea id="field-bio" name="bio" rows="6" required class="<?= e($textareaClasses($error('bio'))) ?>"
                        placeholder="<?= e(t('candidacy.field.bio_placeholder')) ?>"><?= e($field('bio')) ?></textarea>
                    <p class="text-body-sm <?= $error('bio') !== null ? 'text-danger' : 'text-muted' ?>" <?= $error('bio') !== null ? 'role="alert"' : '' ?>>
                        <?= e($error('bio') ?? t('candidacy.field.bio_help', ['min' => $limits['bio_min']])) ?>
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="field-manifesto" class="font-label-lg text-label-lg text-ink"><?= e(t('candidacy.field.manifesto')) ?> <span class="text-danger">*</span></label>
                    <textarea id="field-manifesto" name="manifesto" rows="10" required class="<?= e($textareaClasses($error('manifesto'))) ?>"
                        placeholder="<?= e(t('candidacy.field.manifesto_placeholder')) ?>"><?= e($field('manifesto')) ?></textarea>
                    <p class="text-body-sm <?= $error('manifesto') !== null ? 'text-danger' : 'text-muted' ?>" <?= $error('manifesto') !== null ? 'role="alert"' : '' ?>>
                        <?= e($error('manifesto') ?? t('candidacy.field.manifesto_help', ['min' => $limits['manifesto_min']])) ?>
                    </p>
                </div>
            </section>

            <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
                <div>
                    <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('candidacy.section.documents')) ?></h2>
                    <p class="text-body-sm text-muted"><?= e(t('candidacy.section.documents_help')) ?></p>
                </div>

                <?php if ($feeRequired): ?>
                    <?= component('alert', ['type' => 'warning', 'message' => t('candidacy.fee_notice', ['details' => (string) ($election['candidacy_fee_details'] ?? '')])]) ?>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach (\App\Services\CandidacyDossier::fields() as $name => $definition): ?>
                        <?php
                        if ($definition['type'] === 'payment_proof' && !$feeRequired) {
                            continue;
                        }
                        $required = $definition['required'] === 'fee' ? $feeRequired : (bool) $definition['required'];
                        $received = $present[$name] ?? null;
                        ?>
                        <div class="flex flex-col gap-1.5 rounded-control border <?= $error($name) !== null ? 'border-danger' : 'border-border' ?> p-4">
                            <label for="field-<?= e($name) ?>" class="font-label-md text-label-md text-ink">
                                <?= e(t('candidacy.piece.' . $definition['type'], ['n' => $definition['slot']])) ?>
                                <?= $required ? '<span class="text-danger">*</span>' : '' ?>
                            </label>
                            <?php if ($received !== null): ?>
                                <p class="flex items-center gap-1.5 text-body-sm text-success">
                                    <?= icon('circle-check') ?>
                                    <?= e(t('candidacy.piece_received')) ?>
                                </p>
                            <?php endif; ?>
                            <input type="file" id="field-<?= e($name) ?>" name="<?= e($name) ?>" accept="<?= e($accept[$definition['kind']]) ?>"
                                class="block w-full text-body-sm text-ink file:mr-3 file:h-9 file:px-3 file:rounded-control file:border-0 file:bg-primary/[4%] file:text-primary file:font-label-md hover:file:bg-primary/10">
                            <p class="text-body-sm <?= $error($name) !== null ? 'text-danger' : 'text-muted' ?>" <?= $error($name) !== null ? 'role="alert"' : '' ?>>
                                <?= e($error($name) ?? ($received !== null ? t('candidacy.piece_replace') : t('candidacy.piece_format.' . $definition['kind']))) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="accept_rules" value="1" <?= $field('accept_rules') === '1' ? 'checked' : '' ?> required
                        class="mt-1 h-4 w-4 rounded border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                    <span class="text-body-sm text-ink">
                        <?= e(t('candidacy.field.accept_rules')) ?>
                        <?php if ($rules !== null): ?>
                            <a href="<?= e('/documents/' . (int) $rules['id'] . '/open') ?>" target="_blank" rel="noopener" class="text-primary hover:underline"><?= e(t('candidacy.field.rules_link')) ?></a>
                        <?php endif; ?>
                    </span>
                </label>
                <?php if ($error('accept_rules') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('accept_rules')) ?></p>
                <?php endif; ?>

                <div class="flex justify-end">
                    <?= component('button', [
                        'label' => t($candidacy !== null && $candidacy['status'] === 'correction' ? 'candidacy.send_correction' : ($state === 'editable' ? 'candidacy.save' : 'candidacy.submit')),
                        'variant' => 'primary', 'type' => 'submit',
                    ]) ?>
                </div>
            </section>
        </form>
    <?php endif; ?>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
