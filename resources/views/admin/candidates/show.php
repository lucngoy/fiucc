<?php
/**
 * Dossier d'un candidat (lot 3 ; règlement électoral art. 14-15) — pièces
 * privées (servies par Admin\CandidateController::document(), jamais par
 * une URL statique), parcours et plan d'action, et décision (lot 4) :
 * approuver, demander une correction (motif + délai) ou rejeter (motif).
 */
$election ??= [];
$candidate ??= [];
$documents ??= [];
$canReview ??= false;

$electionId = (int) $election['id'];
$candidateId = (int) $candidate['id'];
$base = '/admin/elections/' . $electionId . '/candidates/' . $candidateId;

$statusBadge = [
    'pending'   => 'bg-gold/20 text-ink',
    'correction' => 'bg-gold/20 text-ink',
    'approved'  => 'bg-success/[4%] text-success',
    'rejected'  => 'bg-danger/10 text-danger',
    'withdrawn' => 'bg-surface text-muted border border-border',
][$candidate['status']] ?? 'bg-gold/20 text-ink';

$byField = [];
foreach ($documents as $document) {
    $byField[$document['type'] . '_' . $document['slot']] = $document;
}
$missing = \App\Services\CandidacyDossier::missing($byField, (bool) $election['candidacy_fee_required']);
$notice ??= '';
$noticeMap = [
    'approved'                    => ['type' => 'success', 'message' => t('admin.candidates.notice.approved')],
    'rejected'                    => ['type' => 'success', 'message' => t('admin.candidates.notice.rejected')],
    'correction_requested'        => ['type' => 'success', 'message' => t('admin.candidates.notice.correction_requested')],
    'decision_reason_required'    => ['type' => 'error',   'message' => t('admin.candidates.notice.decision_reason_required')],
    'invalid_correction_deadline' => ['type' => 'error',   'message' => t('admin.candidates.notice.invalid_correction_deadline')],
    'locked'                      => ['type' => 'error',   'message' => t('admin.candidates.notice.locked')],
];
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col gap-2">
        <a href="<?= e('/admin/elections/' . $electionId . '/candidates') ?>" class="inline-flex w-fit items-center gap-2 text-label-md font-label-md text-muted hover:text-ink transition-colors">
            <?= icon('arrow-left') ?>
            <?= e(t('admin.candidates.title')) ?>
        </a>
        <div class="flex flex-wrap items-center gap-2 text-body-sm text-muted">
            <span class="font-label-md text-ink"><?= e($candidate['position_name']) ?></span>
            <span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md <?= e($statusBadge) ?>"><?= e(t('admin.candidates.status.' . $candidate['status'])) ?></span>
            <?php if (!empty($candidate['submitted_at'])): ?>
                <span>· <?= e(t('admin.candidates.submitted_by_member', ['date' => local_date($candidate['submitted_at'])])) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($missing !== []): ?>
        <?= component('alert', ['type' => 'warning', 'message' => t('admin.candidates.dossier.incomplete', ['count' => count($missing)])]) ?>
    <?php endif; ?>

    <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-4">
        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.dossier.documents')) ?></h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach (\App\Services\CandidacyDossier::fields() as $name => $definition): ?>
                <?php
                if ($definition['type'] === 'payment_proof' && !(bool) $election['candidacy_fee_required'] && !isset($byField[$name])) {
                    continue;
                }
                $document = $byField[$name] ?? null;
                $url = $document !== null ? $base . '/documents/' . (int) $document['id'] : null;
                ?>
                <div class="flex flex-col gap-2 rounded-control border border-border p-3">
                    <p class="text-label-md font-label-md text-ink"><?= e(t('candidacy.piece.' . $definition['type'], ['n' => $definition['slot']])) ?></p>
                    <?php if ($document === null): ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.candidates.dossier.missing')) ?></p>
                    <?php elseif (str_starts_with((string) $document['mime_type'], 'image/')): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="block rounded-control border border-border bg-surface overflow-hidden">
                            <img src="<?= e($url) ?>" alt="" class="w-full h-32 object-cover">
                        </a>
                    <?php else: ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                            <?= icon('file-pdf') ?>
                            <?= e(t('admin.candidates.dossier.open_pdf')) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-3">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('candidacy.field.bio')) ?></h2>
            <p class="text-body-sm text-ink whitespace-pre-line break-words"><?= e((string) ($candidate['bio'] ?? '—')) ?></p>
        </section>
        <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-3">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('candidacy.field.manifesto')) ?></h2>
            <p class="text-body-sm text-ink whitespace-pre-line break-words"><?= e((string) ($candidate['manifesto'] ?? '—')) ?></p>
        </section>
    </div>

    <?php if (in_array($candidate['status'], ['correction', 'rejected'], true) && !empty($candidate['decision_reason'])): ?>
        <?= component('alert', ['type' => $candidate['status'] === 'rejected' ? 'error' : 'warning', 'message' => t('admin.candidates.decision.current_' . $candidate['status'], [
            'reason'   => (string) $candidate['decision_reason'],
            'deadline' => !empty($candidate['correction_deadline']) ? local_date($candidate['correction_deadline']) . ' ' . date('H:i', strtotime((string) $candidate['correction_deadline'])) : '',
        ])]) ?>
    <?php endif; ?>

    <?php if ($canReview && in_array($candidate['status'], ['pending', 'correction'], true)): ?>
        <!-- Décision (lot 4 ; règlement électoral art. 15) : chaque action envoie
             un mail au candidat et revient sur ce dossier (return=dossier). -->
        <section id="decision" class="bg-white rounded-card border border-border p-6 flex flex-col gap-6 scroll-mt-24">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.decision.title')) ?></h2>

            <form method="POST" action="<?= e($base . '/approve') ?>" class="flex flex-col sm:flex-row sm:items-center gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="dossier">
                <button type="submit" class="h-10 px-5 rounded-control bg-success text-white text-label-lg font-label-lg hover:bg-success/90 transition-colors"><?= e(t('admin.candidates.action.approve')) ?></button>
                <p class="text-body-sm text-muted"><?= e(t('admin.candidates.decision.approve_help')) ?></p>
            </form>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php if ($candidate['status'] === 'pending' && in_array($election['status'], ['candidacies', 'candidacies_closed'], true)): ?>
                    <form method="POST" action="<?= e($base . '/request-correction') ?>" class="flex flex-col gap-2 rounded-control border border-border p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="dossier">
                        <h3 class="font-label-md text-label-md text-ink"><?= e(t('admin.candidates.decision.correction_title')) ?></h3>
                        <label for="correction-reason" class="text-body-sm text-muted"><?= e(t('admin.candidates.decision.reason_label')) ?></label>
                        <textarea id="correction-reason" name="reason" rows="4" required maxlength="2000" placeholder="<?= e(t('admin.candidates.decision.correction_placeholder')) ?>"
                            class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                        <label for="correction-deadline" class="text-body-sm text-muted"><?= e(t('admin.candidates.decision.deadline_label')) ?></label>
                        <input type="datetime-local" id="correction-deadline" name="correction_deadline" required
                            value="<?= e(date('Y-m-d\TH:i', strtotime('+48 hours'))) ?>"
                            class="h-10 rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                        <button type="submit" class="mt-1 h-10 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.candidates.decision.correction_submit')) ?></button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="<?= e($base . '/reject') ?>" class="flex flex-col gap-2 rounded-control border border-danger/30 p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="dossier">
                    <h3 class="font-label-md text-label-md text-danger"><?= e(t('admin.candidates.decision.reject_title')) ?></h3>
                    <label for="reject-reason" class="text-body-sm text-muted"><?= e(t('admin.candidates.decision.reason_label')) ?></label>
                    <textarea id="reject-reason" name="reason" rows="4" required maxlength="2000" placeholder="<?= e(t('admin.candidates.decision.reject_placeholder')) ?>"
                        class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                    <p class="text-body-sm text-muted"><?= e(t('admin.candidates.decision.reject_help')) ?></p>
                    <button type="submit" class="mt-1 h-10 px-4 rounded-control border border-danger text-danger text-label-md font-label-md hover:bg-danger/10 transition-colors"><?= e(t('admin.candidates.action.reject')) ?></button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
