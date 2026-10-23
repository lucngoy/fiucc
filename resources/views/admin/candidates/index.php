<?php
/**
 * Liste des candidatures d'une élection (FIUCC-61) — même structure de
 * table qu'admin/news/index.php, sans pagination (une élection compte au
 * plus quelques dizaines de candidats, jamais assez pour le justifier).
 *
 * L'avertissement d'inéligibilité est affiché ici, pas seulement sur la
 * fiche de chaque candidat (décision explicite conversation FIUCC-61) :
 * personne n'ouvre les candidatures une par une la veille d'un scrutin.
 *
 * Le nombre de voix ($candidate['vote_count'], calculé uniquement pour
 * les candidats approuvés) n'est montré qu'à l'admin, dans la confirmation
 * de retrait — jamais publié tel quel avant la clôture officielle (§22 du
 * cahier des charges).
 */
$election ??= [];
$candidates ??= [];
$canManage ??= false;
$canAdd ??= false;
$canWithdraw ??= false;
$notice ??= '';

$electionId = (int) $election['id'];

$statusBadge = static function (string $status): string {
    $variants = [
        'pending'   => 'bg-gold/20 text-ink',
        'correction' => 'bg-gold/20 text-ink',
        'approved'  => 'bg-success/[4%] text-success',
        'rejected'  => 'bg-danger/10 text-danger',
        'withdrawn' => 'bg-muted/[1%] text-muted',
    ];
    $classes = $variants[$status] ?? $variants['pending'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('admin.candidates.status.' . $status)) . '</span>';
};

$noticeMap = [
    'created'          => ['type' => 'success', 'message' => t('admin.candidates.notice.created')],
    'updated'          => ['type' => 'success', 'message' => t('admin.candidates.notice.updated')],
    'approved'         => ['type' => 'success', 'message' => t('admin.candidates.notice.approved')],
    'rejected'         => ['type' => 'success', 'message' => t('admin.candidates.notice.rejected')],
    'withdrawn'        => ['type' => 'success', 'message' => t('admin.candidates.notice.withdrawn')],
    'locked'           => ['type' => 'error',   'message' => t('admin.candidates.notice.locked')],
    'not_found'        => ['type' => 'error',   'message' => t('admin.candidates.notice.not_found')],
    'member_ineligible'=> ['type' => 'error',   'message' => t('admin.candidates.notice.member_ineligible')],
    'reason_required'  => ['type' => 'error',   'message' => t('admin.candidates.notice.reason_required')],
    'decision_reason_required' => ['type' => 'error',   'message' => t('admin.candidates.notice.decision_reason_required')],
    'cannot_withdraw'  => ['type' => 'error',   'message' => t('admin.candidates.notice.cannot_withdraw')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections/<?= e((string) $electionId) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e($election['title']) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.candidates.title')) ?></h1>
        <?php if ($canAdd): ?>
            <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates/create"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= icon('plus') ?>
                <?= e(t('admin.candidates.action.create')) ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.candidates.column.candidate')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.candidates.column.position')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.candidates.column.ballot_number')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.candidates.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.candidates.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($candidates === []): ?>
                    <tr>
                        <td colspan="5" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.candidates.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($candidates as $candidate): ?>
                        <?php $candidateId = (int) $candidate['id']; ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($candidate['photo'])): ?>
                                        <img src="<?= e('/uploads/' . $candidate['photo']) ?>" alt="" class="h-10 w-10 rounded-full object-cover border border-border shrink-0">
                                    <?php else: ?>
                                        <span class="h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                            <?= icon('user') ?>
                                        </span>
                                    <?php endif; ?>
                                    <div>
                                        <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates/<?= e((string) $candidateId) ?>" class="block font-label-lg text-label-lg text-ink hover:text-primary hover:underline"><?= e($candidate['full_name']) ?></a>
                                        <?php if (!empty($candidate['submitted_at'])): ?>
                                            <p class="text-body-sm text-muted"><?= e(t('admin.candidates.submitted_by_member', ['date' => local_date($candidate['submitted_at'])])) ?></p>
                                        <?php endif; ?>
                                        <?php if (!$candidate['eligible']): ?>
                                            <p class="flex items-center gap-1.5 text-label-md font-label-md text-danger mt-0.5">
                                                <?= icon('triangle-exclamation') ?>
                                                <?= e(t('admin.candidates.ineligible_warning')) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink"><?= e($candidate['position_name']) ?></td>
                            <td class="p-3 text-body-sm text-ink"><?= $candidate['ballot_number'] !== null ? e((string) $candidate['ballot_number']) : '—' ?></td>
                            <td class="p-3"><?= $statusBadge($candidate['status']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if ($canManage): ?>
                                        <div class="relative group">
                                            <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates/<?= e((string) $candidateId) ?>/edit"
                                                aria-label="<?= e(t('admin.candidates.action.edit')) ?>"
                                                class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                                <?= icon('pen') ?>
                                            </a>
                                            <?= $actionTooltip(t('admin.candidates.action.edit')) ?>
                                        </div>
                                        <?php if (in_array($candidate['status'], ['pending', 'correction'], true)): ?>
                                            <div class="relative group">
                                                <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/candidates/<?= e((string) $candidateId) ?>/approve">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" aria-label="<?= e(t('admin.candidates.action.approve')) ?>"
                                                        class="h-9 w-9 rounded-control bg-surface text-success hover:bg-success/15 flex items-center justify-center transition-colors">
                                                        <?= icon('check') ?>
                                                    </button>
                                                </form>
                                                <?= $actionTooltip(t('admin.candidates.action.approve')) ?>
                                            </div>
                                            <!-- Rejet / correction : motif obligatoire (lot 4), donc
                                                 depuis le panneau de décision du dossier. -->
                                            <div class="relative group">
                                                <a href="/admin/elections/<?= e((string) $electionId) ?>/candidates/<?= e((string) $candidateId) ?>#decision"
                                                    aria-label="<?= e(t('admin.candidates.action.decide')) ?>"
                                                    class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                                    <?= icon('xmark') ?>
                                                </a>
                                                <?= $actionTooltip(t('admin.candidates.action.decide')) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($canWithdraw && $candidate['status'] === 'approved'): ?>
                                        <div class="relative group">
                                            <button type="button" data-open-dialog="withdraw-dialog-<?= e((string) $candidateId) ?>"
                                                aria-label="<?= e(t('admin.candidates.action.withdraw')) ?>"
                                                class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                                <?= icon('user-slash') ?>
                                            </button>
                                            <?= $actionTooltip(t('admin.candidates.action.withdraw')) ?>
                                        </div>
                                        <dialog id="withdraw-dialog-<?= e((string) $candidateId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                            <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/candidates/<?= e((string) $candidateId) ?>/withdraw" class="flex flex-col gap-2 p-4 text-left">
                                                <?= csrf_field() ?>
                                                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.candidates.withdraw.confirm_title')) ?></h2>
                                                <p class="text-body-sm text-muted"><?= e(t('admin.candidates.withdraw.confirm_message', ['name' => $candidate['full_name']])) ?></p>
                                                <p class="text-body-sm font-label-md text-ink"><?= e(t('admin.candidates.withdraw.vote_count', ['count' => (string) ($candidate['vote_count'] ?? 0)])) ?></p>
                                                <div class="flex flex-col gap-1.5 mt-1">
                                                    <label for="withdraw-reason-<?= e((string) $candidateId) ?>" class="text-label-md font-label-md text-muted"><?= e(t('admin.candidates.withdraw.reason_label')) ?></label>
                                                    <textarea id="withdraw-reason-<?= e((string) $candidateId) ?>" name="reason" required rows="3"
                                                        class="rounded-control border border-border px-3 py-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                                                </div>
                                                <div class="flex justify-end gap-2 mt-1">
                                                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.candidates.action.cancel')) ?></button>
                                                    <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.candidates.withdraw.confirm_button')) ?></button>
                                                </div>
                                            </form>
                                        </dialog>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
<script src="/assets/js/admin-tooltip.js" defer></script>
