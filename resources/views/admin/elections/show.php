<?php
/**
 * Détail/configuration d'une élection (FIUCC-59/60) — postes gérés ici
 * plutôt que sur une page séparée. Candidatures/ouverture/fermeture/
 * résultats : tickets séparés (FIUCC-61+), pas de bouton pour des actions
 * qui n'existent pas encore (même principe que le reste du back office :
 * ne jamais simuler une fonctionnalité qui n'est pas construite).
 *
 * Verrouillage : dès que $election['status'] !== 'draft', ni le formulaire
 * de configuration ni la gestion des postes ne sont proposés — seulement
 * une liste en lecture seule, et pour 'open' uniquement, le formulaire de
 * prolongation/réduction de end_at (décision explicite FIUCC-59). Le
 * contrôleur applique la même règle côté serveur (App\Models\Election) :
 * ce que cette vue cache n'est jamais la seule protection.
 *
 * Dates (décisions du bureau 2026-10-21, section #dates) : avant
 * l'ouverture du vote, début, fin et passation restent modifiables ; vote
 * ouvert ou clos, seulement la passation ; après publication, plus rien.
 * Le dépôt clos peut être rouvert avec une nouvelle date limite (jamais
 * pour un second tour), et la date limite d'un dépôt ouvert ne peut être
 * que repoussée.
 */
$election ??= [];
$positions ??= [];
$notice ??= '';

$isDraft = $election['status'] === 'draft';
$isCandidacies = $election['status'] === 'candidacies';
$isCandidaciesClosed = $election['status'] === 'candidacies_closed';
$commission ??= [];
$eligibleMembers ??= [];
// Clôture du dépôt seulement une fois la date limite annoncée passée
// (Election::closeCandidacies(), garde SQL identique).
$deadlinePassed = !empty($election['candidacy_deadline']) && strtotime((string) $election['candidacy_deadline']) <= time();
$commissionEditable = in_array($election['status'], ['draft', 'candidacies', 'candidacies_closed'], true);
$isOpen = $election['status'] === 'open';
$isClosed = $election['status'] === 'closed';
$isPublished = $election['status'] === 'published';
$datesBeforeVote = $isCandidacies || $isCandidaciesClosed;
$datesEditable = $datesBeforeVote || $isOpen || $isClosed;
$canReopenCandidacies = $isCandidaciesClosed && $election['first_round_id'] === null;
$descriptionEditable = $datesEditable; // candidacies, candidacies_closed, open, closed
$toLocal = static fn (?string $value): string => $value !== null && $value !== '' ? date('Y-m-d\TH:i', strtotime($value)) : '';
$canDelete = $isDraft && $positions === [];
$canStartCandidacies = $isDraft && $positions !== [];

$statusBadge = static function (string $status): string {
    $variants = [
        'draft'       => 'bg-muted/[1%] text-muted',
        'candidacies' => 'bg-gold/20 text-ink',
        'candidacies_closed' => 'bg-gold/20 text-ink',
        'open'        => 'bg-success/[4%] text-success',
        'closed'      => 'bg-primary/10 text-primary',
        'published'   => 'bg-primary/10 text-primary',
    ];
    $classes = $variants[$status] ?? $variants['draft'];

    return '<span class="inline-flex items-center h-7 px-3 rounded-full ' . $classes . ' text-label-lg font-label-lg">'
        . e(t('admin.elections.status.' . $status)) . '</span>';
};

$noticeMap = [
    'updated'          => ['type' => 'success', 'message' => t('admin.elections.notice.updated')],
    'locked'           => ['type' => 'error',   'message' => t('admin.elections.notice.locked')],
    'cannot_delete'    => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_delete')],
    'extended'         => ['type' => 'success', 'message' => t('admin.elections.notice.extended')],
    'not_open'         => ['type' => 'error',   'message' => t('admin.elections.notice.not_open')],
    'invalid_end_at'   => ['type' => 'error',   'message' => t('admin.elections.notice.invalid_end_at')],
    'position_created' => ['type' => 'success', 'message' => t('admin.elections.notice.position_created')],
    'position_updated' => ['type' => 'success', 'message' => t('admin.elections.notice.position_updated')],
    'position_deleted' => ['type' => 'success', 'message' => t('admin.elections.notice.position_deleted')],
    'position_invalid' => ['type' => 'error',   'message' => t('admin.elections.notice.position_invalid')],
    'candidacies_started'      => ['type' => 'success', 'message' => t('admin.elections.notice.candidacies_started')],
    'cannot_start_candidacies' => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_start_candidacies')],
    'opened'                   => ['type' => 'success', 'message' => t('admin.elections.notice.opened')],
    'cannot_open'              => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_open')],
    'closed'                   => ['type' => 'success', 'message' => t('admin.elections.notice.closed')],
    'cannot_close'             => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_close')],
    'published'                => ['type' => 'success', 'message' => t('admin.elections.notice.published')],
    'candidacies_closed'          => ['type' => 'success', 'message' => t('admin.elections.notice.candidacies_closed')],
    'cannot_close_candidacies'    => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_close_candidacies')],
    'candidacy_deadline_extended' => ['type' => 'success', 'message' => t('admin.elections.notice.candidacy_deadline_extended')],
    'invalid_candidacy_deadline'  => ['type' => 'error',   'message' => t('admin.elections.notice.invalid_candidacy_deadline')],
    'cannot_extend_candidacies'   => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_extend_candidacies')],
    'commission_added'     => ['type' => 'success', 'message' => t('admin.elections.notice.commission_added')],
    'commission_removed'   => ['type' => 'success', 'message' => t('admin.elections.notice.commission_removed')],
    'commission_invalid'   => ['type' => 'error',   'message' => t('admin.elections.notice.commission_invalid')],
    'commission_candidate' => ['type' => 'error',   'message' => t('admin.elections.notice.commission_candidate')],
    'commission_duplicate' => ['type' => 'error',   'message' => t('admin.elections.notice.commission_duplicate')],
    'commission_locked'    => ['type' => 'error',   'message' => t('admin.elections.notice.commission_locked')],
    'cannot_publish'           => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_publish')],
    'results_not_ready'        => ['type' => 'error',   'message' => t('admin.elections.notice.results_not_ready')],
    'reminder_too_soon'        => ['type' => 'error',   'message' => t('admin.elections.notice.reminder_too_soon')],
    'not_closed'               => ['type' => 'error',   'message' => t('admin.elections.notice.not_closed')],
    'reminder_sent'            => ['type' => 'success', 'message' => t('admin.elections.notice.reminder_sent', ['count' => (string) ($_GET['count'] ?? '0')])],
    'closure_notice_sent'      => ['type' => 'success', 'message' => t('admin.elections.notice.closure_notice_sent', ['count' => (string) ($_GET['count'] ?? '0')])],
    'second_round_created'     => ['type' => 'success', 'message' => t('admin.elections.second_round.notice.created')],
    'candidacy_deadline_not_later' => ['type' => 'error',   'message' => t('admin.elections.notice.candidacy_deadline_not_later')],
    'candidacies_reopened'         => ['type' => 'success', 'message' => t('admin.elections.notice.candidacies_reopened')],
    'cannot_reopen_candidacies'    => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_reopen_candidacies')],
    'dates_updated'                => ['type' => 'success', 'message' => t('admin.elections.dates.notice.updated')],
    'dates_unchanged'              => ['type' => 'info',    'message' => t('admin.elections.dates.notice.unchanged')],
    'dates_invalid'                => ['type' => 'error',   'message' => t('admin.elections.dates.notice.invalid')],
    'dates_handover_invalid'       => ['type' => 'error',   'message' => t('admin.elections.dates.notice.handover_invalid')],
    'dates_start_past'             => ['type' => 'error',   'message' => t('admin.elections.dates.notice.start_past')],
    'dates_start_before_deadline'  => ['type' => 'error',   'message' => t('admin.elections.dates.notice.start_before_deadline')],
    'dates_too_short'              => ['type' => 'error',   'message' => t('admin.elections.dates.notice.too_short')],
    'dates_end_after_handover'     => ['type' => 'error',   'message' => t('admin.elections.dates.notice.end_after_handover')],
    'dates_locked'                 => ['type' => 'error',   'message' => t('admin.elections.dates.notice.locked')],
    'dates_list_before_deadline'   => ['type' => 'error',   'message' => t('admin.elections.dates.notice.list_before_deadline')],
    'dates_list_after_start'       => ['type' => 'error',   'message' => t('admin.elections.dates.notice.list_after_start')],
    'candidacy_deadline_after_list' => ['type' => 'error',  'message' => t('admin.elections.notice.candidacy_deadline_after_list')],
    'description_updated'          => ['type' => 'success', 'message' => t('admin.elections.description.notice.updated')],
    'description_unchanged'        => ['type' => 'info',    'message' => t('admin.elections.description.notice.unchanged')],
    'description_too_long'         => ['type' => 'error',   'message' => t('admin.elections.description.notice.too_long')],
    'description_locked'           => ['type' => 'error',   'message' => t('admin.elections.description.notice.locked')],
];
$rounds ??= ['first' => null, 'second' => null, 'tied' => 0, 'pending_decisions' => 0];
$resultsUrl = '/admin/elections/' . (int) $election['id'] . '/results';

$inputClasses = 'h-10 rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50';
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.elections.title')) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <?php if ($rounds['first'] !== null): ?>
        <p class="flex flex-wrap items-center gap-2 rounded-control bg-primary-light/20 p-4 text-body-md text-primary">
            <?= icon('scale-balanced') ?>
            <?= e(t('admin.elections.second_round.of_first_round')) ?>
            <a href="/admin/elections/<?= e((string) $rounds['first']['id']) ?>" class="font-label-lg text-label-lg underline"><?= e($rounds['first']['title']) ?></a>
        </p>
    <?php endif; ?>
    <?php if ($rounds['second'] !== null): ?>
        <p class="flex flex-wrap items-center gap-2 rounded-control bg-primary-light/20 p-4 text-body-md text-primary">
            <?= icon('scale-balanced') ?>
            <?= e(t('admin.elections.second_round.exists', ['title' => $rounds['second']['title']])) ?>
            <a href="/admin/elections/<?= e((string) $rounds['second']['id']) ?>" class="font-label-lg text-label-lg underline"><?= e(t('admin.elections.second_round.open_link')) ?></a>
        </p>
    <?php endif; ?>
    <?php if ($rounds['tied'] > 0 || $rounds['pending_decisions'] > 0): ?>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-control border border-gold/60 bg-white p-4">
            <p class="flex-1 text-body-md text-ink">
                <?= e($rounds['tied'] > 0
                    ? t('admin.elections.second_round.needed', ['count' => (string) $rounds['tied']])
                    : t('admin.elections.decision.needed', ['count' => (string) $rounds['pending_decisions']])) ?>
            </p>
            <a href="<?= e($resultsUrl . ($rounds['tied'] > 0 ? '#second-round' : '')) ?>"
               class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
                <?= e(t($rounds['tied'] > 0 ? 'admin.elections.second_round.go' : 'admin.elections.decision.go')) ?>
                <?= icon('arrow-right') ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-headline-md text-headline-md text-ink"><?= e($election['title']) ?></h1>
                <?= $statusBadge($election['status']) ?>
            </div>
            <div class="flex items-center gap-2">
                <a href="/admin/elections/<?= e((string) $election['id']) ?>/candidates"
                   class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                    <?= icon('users') ?>
                    <?= e(t('admin.elections.action.manage_candidates')) ?>
                </a>
                <?php if ($isOpen): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/tokens"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <?= icon('key') ?>
                        <?= e(t('admin.elections.action.voting_tokens')) ?>
                    </a>
                    <button type="button" data-open-dialog="send-reminder-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <?= icon('bell') ?>
                        <?= e(t('admin.elections.action.send_reminder')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed): ?>
                    <button type="button" data-open-dialog="send-closure-notice-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <?= icon('envelope') ?>
                        <?= e(t('admin.elections.action.send_closure_notice')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($canStartCandidacies): ?>
                    <button type="button" data-open-dialog="start-candidacies-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <?= icon('arrow-right') ?>
                        <?= e(t('admin.elections.action.start_candidacies')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isCandidacies): ?>
                    <?php if ($deadlinePassed): ?>
                        <button type="button" data-open-dialog="close-candidacies-dialog"
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                            <?= icon('lock') ?>
                            <?= e(t('admin.elections.action.close_candidacies')) ?>
                        </button>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-muted text-label-md font-label-md" title="<?= e(t('admin.elections.close_candidacies.not_yet')) ?>">
                            <?= icon('clock') ?>
                            <?= e(t('admin.elections.close_candidacies.not_yet')) ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($isCandidaciesClosed): ?>
                    <button type="button" data-open-dialog="open-election-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <?= icon('lock-open') ?>
                        <?= e(t('admin.elections.action.open')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isOpen): ?>
                    <button type="button" data-open-dialog="close-election-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-danger text-label-md font-label-md hover:bg-danger/15 transition-colors">
                        <?= icon('lock') ?>
                        <?= e(t('admin.elections.action.close')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed): ?>
                    <button type="button" data-open-dialog="publish-results-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <?= icon('square-poll-vertical') ?>
                        <?= e(t('admin.elections.action.publish')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed || $isPublished): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/results"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <?= icon('chart-simple') ?>
                        <?= e(t('elections.detail.view_results')) ?>
                    </a>
                <?php endif; ?>
                <?php if ($isDraft): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/edit"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <?= icon('pen') ?>
                        <?= e(t('admin.elections.action.edit')) ?>
                    </a>
                    <?php if ($canDelete): ?>
                        <button type="button" data-open-dialog="delete-election-dialog"
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-danger text-label-md font-label-md hover:bg-danger/15 transition-colors">
                            <?= icon('trash') ?>
                            <?= e(t('admin.elections.action.delete')) ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($election['description'])): ?>
            <p class="text-body-md text-muted whitespace-pre-line"><?= e($election['description']) ?></p>
        <?php endif; ?>

        <div class="flex flex-wrap gap-6 text-body-sm text-ink pt-2 border-t border-border">
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.candidacy_deadline')) ?></p>
                <p><?= !empty($election['candidacy_deadline']) ? e(local_date($election['candidacy_deadline'])) . ' · ' . e(date('H:i', strtotime((string) $election['candidacy_deadline']))) : '<span class="text-muted">—</span>' ?></p>
            </div>
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.candidate_list_at')) ?></p>
                <p><?= !empty($election['candidate_list_at']) ? e(local_date($election['candidate_list_at'])) . ' · ' . e(date('H:i', strtotime((string) $election['candidate_list_at']))) : '<span class="text-muted">' . e(t('admin.elections.candidate_list_immediate')) . '</span>' ?></p>
            </div>
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.start_at')) ?></p>
                <p><?= e(local_date($election['start_at'])) ?> · <?= e(date('H:i', strtotime((string) $election['start_at']))) ?></p>
            </div>
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.end_at')) ?></p>
                <p><?= e(local_date($election['end_at'])) ?> · <?= e(date('H:i', strtotime((string) $election['end_at']))) ?></p>
            </div>
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.handover_date')) ?></p>
                <p>
                    <?php if (!empty($election['handover_date'])): ?>
                        <?php $mandate = \App\Models\Government::mandateFor($election); ?>
                        <?= e(local_date($election['handover_date'])) ?>
                        <span class="text-muted">· <?= e(t('admin.elections.mandate_until', ['date' => local_date($mandate['end_date'])])) ?></span>
                    <?php else: ?>
                        <span class="text-muted"><?= e(t('admin.elections.handover_missing')) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (!$isDraft): ?>
            <div class="pt-2 border-t border-border">
                <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.locked_notice')]) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($descriptionEditable): ?>
        <!-- Description et calendrier électoral (art. 8) — décision du
             bureau 2026-10-21, Election::updateDescription(). -->
        <div id="description" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.description.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.elections.description.help')) ?></p>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/description" class="flex flex-col gap-3">
                <?= csrf_field() ?>
                <label for="field-description-edit" class="sr-only"><?= e(t('admin.elections.description.title')) ?></label>
                <textarea id="field-description-edit" name="description" rows="12" maxlength="5000"
                    class="w-full rounded-control border border-border px-3 py-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"><?= e((string) ($election['description'] ?? '')) ?></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                        <?= e(t('admin.elections.description.submit')) ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5">
        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.positions.title')) ?></h2>

        <?php if ($positions === []): ?>
            <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.positions.empty')]) ?>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($positions as $position): ?>
                    <?php $positionId = (int) $position['id']; ?>
                    <?php if ($isDraft): ?>
                        <div class="flex flex-col sm:flex-row sm:items-end gap-3 p-4 rounded-control border border-border">
                            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/positions/<?= e((string) $positionId) ?>"
                                  class="flex-1 flex flex-col sm:flex-row sm:items-end gap-3">
                                <?= csrf_field() ?>
                                <div class="flex-1">
                                    <label for="pos-name-<?= e((string) $positionId) ?>" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.name')) ?></label>
                                    <input type="text" id="pos-name-<?= e((string) $positionId) ?>" name="name" value="<?= e($position['name']) ?>" required class="<?= e($inputClasses) ?> w-full">
                                </div>
                                <div class="flex-1">
                                    <label for="pos-desc-<?= e((string) $positionId) ?>" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.description')) ?></label>
                                    <input type="text" id="pos-desc-<?= e((string) $positionId) ?>" name="description" value="<?= e((string) ($position['description'] ?? '')) ?>" class="<?= e($inputClasses) ?> w-full">
                                </div>
                                <div class="w-full sm:w-24">
                                    <label for="pos-max-<?= e((string) $positionId) ?>" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.max_votes')) ?></label>
                                    <input type="number" id="pos-max-<?= e((string) $positionId) ?>" name="max_votes" min="1" value="<?= e((string) $position['max_votes']) ?>" required class="<?= e($inputClasses) ?> w-full">
                                </div>
                                <div class="w-full sm:w-20">
                                    <label for="pos-order-<?= e((string) $positionId) ?>" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.sort_order')) ?></label>
                                    <input type="number" id="pos-order-<?= e((string) $positionId) ?>" name="sort_order" min="0" value="<?= e((string) $position['sort_order']) ?>" class="<?= e($inputClasses) ?> w-full">
                                </div>
                                <button type="submit" class="h-10 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors shrink-0">
                                    <?= e(t('admin.elections.positions.action.save')) ?>
                                </button>
                            </form>
                            <button type="button" data-open-dialog="delete-position-<?= e((string) $positionId) ?>"
                                class="h-10 w-10 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors shrink-0">
                                <?= icon('trash') ?>
                            </button>
                            <dialog id="delete-position-<?= e((string) $positionId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/positions/<?= e((string) $positionId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                    <?= csrf_field() ?>
                                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.positions.delete.confirm_title')) ?></h2>
                                    <p class="text-body-sm text-muted"><?= e(t('admin.elections.positions.delete.confirm_message', ['name' => $position['name']])) ?></p>
                                    <div class="flex justify-end gap-2 mt-1">
                                        <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                                        <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.elections.positions.delete.confirm_button')) ?></button>
                                    </div>
                                </form>
                            </dialog>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 p-4 rounded-control border border-border">
                            <p class="font-label-lg text-label-lg text-ink flex-1"><?= e($position['name']) ?></p>
                            <?php if (!empty($position['description'])): ?>
                                <p class="text-body-sm text-muted flex-1"><?= e($position['description']) ?></p>
                            <?php endif; ?>
                            <p class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.max_votes')) ?>: <?= e((string) $position['max_votes']) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($isDraft): ?>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/positions"
                  class="flex flex-col sm:flex-row sm:items-end gap-3 pt-4 border-t border-border">
                <?= csrf_field() ?>
                <div class="flex-1">
                    <label for="new-pos-name" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.name')) ?></label>
                    <input type="text" id="new-pos-name" name="name" required placeholder="<?= e(t('admin.elections.positions.field.name_placeholder')) ?>" class="<?= e($inputClasses) ?> w-full">
                </div>
                <div class="flex-1">
                    <label for="new-pos-desc" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.description')) ?></label>
                    <input type="text" id="new-pos-desc" name="description" class="<?= e($inputClasses) ?> w-full">
                </div>
                <div class="w-full sm:w-24">
                    <label for="new-pos-max" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.max_votes')) ?></label>
                    <input type="number" id="new-pos-max" name="max_votes" min="1" value="1" required class="<?= e($inputClasses) ?> w-full">
                </div>
                <div class="w-full sm:w-20">
                    <label for="new-pos-order" class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('admin.elections.positions.field.sort_order')) ?></label>
                    <input type="number" id="new-pos-order" name="sort_order" min="0" value="<?= e((string) count($positions)) ?>" class="<?= e($inputClasses) ?> w-full">
                </div>
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
                    <?= e(t('admin.elections.positions.action.add')) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Commission électorale (art. 5-7) : ses membres recensés ne peuvent
         pas être candidats (art. 6, App\Models\ElectionCommission). -->
    <div id="commission" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-5 scroll-mt-24">
        <div>
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.commission.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.elections.commission.help')) ?></p>
        </div>

        <?php if ($commission === []): ?>
            <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.commission.empty')]) ?>
        <?php else: ?>
            <ul class="flex flex-col divide-y divide-border rounded-control border border-border">
                <?php foreach ($commission as $commissioner): ?>
                    <li class="flex items-center justify-between gap-3 p-3">
                        <div class="min-w-0">
                            <p class="text-label-md font-label-md text-ink truncate"><?= e($commissioner['display_name']) ?></p>
                            <p class="text-body-sm text-muted">
                                <?= e(t('admin.elections.commission.role.' . $commissioner['role'])) ?>
                                · <?= e($commissioner['member_id'] !== null ? t('admin.elections.commission.linked', ['number' => (string) $commissioner['membership_number']]) : t('admin.elections.commission.not_linked')) ?>
                            </p>
                        </div>
                        <?php if ($commissionEditable): ?>
                            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/commission/<?= e((string) $commissioner['id']) ?>/delete">
                                <?= csrf_field() ?>
                                <button type="submit" class="h-9 px-3 rounded-control text-danger text-label-md font-label-md hover:bg-danger/10 transition-colors"><?= e(t('admin.elections.commission.remove')) ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($commissionEditable): ?>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/commission" class="grid grid-cols-1 md:grid-cols-[2fr_2fr_1fr_auto] gap-3 items-end">
                <?= csrf_field() ?>
                <div class="flex flex-col gap-1.5">
                    <label for="commission-member" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.commission.field.member')) ?></label>
                    <select id="commission-member" name="member_id" class="<?= e($inputClasses) ?> bg-white w-full">
                        <option value=""><?= e(t('admin.elections.commission.field.member_none')) ?></option>
                        <?php foreach ($eligibleMembers as $eligible): ?>
                            <option value="<?= e((string) $eligible['id']) ?>"><?= e($eligible['full_name']) ?> (<?= e($eligible['student_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="commission-name" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.commission.field.name')) ?></label>
                    <input type="text" id="commission-name" name="display_name" maxlength="150" placeholder="<?= e(t('admin.elections.commission.field.name_placeholder')) ?>" class="<?= e($inputClasses) ?> w-full">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="commission-role" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.commission.field.role')) ?></label>
                    <select id="commission-role" name="role" class="<?= e($inputClasses) ?> bg-white w-full">
                        <?php foreach (\App\Models\ElectionCommission::ROLES as $role): ?>
                            <option value="<?= e($role) ?>"><?= e(t('admin.elections.commission.role.' . $role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.commission.add')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($isCandidacies): ?>
        <!-- Prolongation de la date limite de dépôt (migration 023) — vers une
             date future, avant le début du vote (Election::extendCandidacyDeadline()). -->
        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.extend_candidacies.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.elections.extend_candidacies.help')) ?></p>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/extend-candidacy-deadline" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <?= csrf_field() ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-new-candidacy-deadline" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.extend_candidacies.new_deadline')) ?></label>
                    <input type="datetime-local" id="field-new-candidacy-deadline" name="candidacy_deadline" required class="<?= e($inputClasses) ?>">
                </div>
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= e(t('admin.elections.extend_candidacies.submit')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($canReopenCandidacies): ?>
        <!-- Réouverture du dépôt (décision du bureau 2026-10-21,
             Election::reopenCandidacies()). -->
        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.reopen_candidacies.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.elections.reopen_candidacies.help')) ?></p>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/reopen-candidacies" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <?= csrf_field() ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-reopen-deadline" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.extend_candidacies.new_deadline')) ?></label>
                    <input type="datetime-local" id="field-reopen-deadline" name="candidacy_deadline" required class="<?= e($inputClasses) ?>">
                </div>
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= e(t('admin.elections.reopen_candidacies.submit')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($datesEditable): ?>
        <!-- Dates du vote et de la passation (décisions du bureau 2026-10-21,
             Election::rescheduleVote()/updateHandoverDate()). -->
        <div id="dates" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.dates.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t($datesBeforeVote ? 'admin.elections.dates.help_before_vote' : 'admin.elections.dates.help_after_opening')) ?></p>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/dates" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <?= csrf_field() ?>
                <?php if ($datesBeforeVote && $election['first_round_id'] === null): ?>
                    <div class="flex flex-col gap-1.5">
                        <label for="field-dates-list" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.field.candidate_list_at')) ?></label>
                        <input type="datetime-local" id="field-dates-list" name="candidate_list_at" required value="<?= e($toLocal($election['candidate_list_at'])) ?>" class="<?= e($inputClasses) ?> w-full">
                    </div>
                <?php endif; ?>
                <?php if ($datesBeforeVote): ?>
                    <div class="flex flex-col gap-1.5">
                        <label for="field-dates-start" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.field.start_at')) ?></label>
                        <input type="datetime-local" id="field-dates-start" name="start_at" required value="<?= e($toLocal($election['start_at'])) ?>" class="<?= e($inputClasses) ?> w-full">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="field-dates-end" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.field.end_at')) ?></label>
                        <input type="datetime-local" id="field-dates-end" name="end_at" required value="<?= e($toLocal($election['end_at'])) ?>" class="<?= e($inputClasses) ?> w-full">
                    </div>
                <?php endif; ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-dates-handover" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.field.handover_date')) ?></label>
                    <input type="date" id="field-dates-handover" name="handover_date" required value="<?= e((string) ($election['handover_date'] ?? '')) ?>" class="<?= e($inputClasses) ?> w-full">
                </div>
                <button type="submit" class="md:col-span-2 justify-self-end h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= e(t('admin.elections.dates.submit')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($isOpen): ?>
        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.elections.extend.title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.elections.extend.help')) ?></p>
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/extend-deadline" class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <?= csrf_field() ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-new-end-at" class="text-label-md font-label-md text-muted"><?= e(t('admin.elections.extend.new_end_at')) ?></label>
                    <input type="datetime-local" id="field-new-end-at" name="end_at" required class="<?= e($inputClasses) ?>">
                </div>
                <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= e(t('admin.elections.extend.submit')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
        <dialog id="delete-election-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.delete.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.delete.confirm_message', ['title' => $election['title']])) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.elections.delete.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($canStartCandidacies): ?>
        <dialog id="start-candidacies-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/start-candidacies" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.start_candidacies.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.start_candidacies.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.start_candidacies.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isCandidacies && $deadlinePassed): ?>
        <dialog id="close-candidacies-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/close-candidacies" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.close_candidacies.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.close_candidacies.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.action.close_candidacies')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isCandidaciesClosed): ?>
        <dialog id="open-election-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/open" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.open.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.open.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.open.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isOpen): ?>
        <dialog id="close-election-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/close" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.close.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.close.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.elections.close.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isClosed): ?>
        <dialog id="publish-results-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/publish" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.publish.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.publish.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.publish.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isOpen): ?>
        <dialog id="send-reminder-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/reminder" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.reminder.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.reminder.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.reminder.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <?php if ($isClosed): ?>
        <dialog id="send-closure-notice-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
            <form method="POST" action="/admin/elections/<?= e((string) $election['id']) ?>/closure-notice" class="flex flex-col gap-2 p-4 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.closure_notice.confirm_title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.closure_notice.confirm_message')) ?></p>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.elections.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.elections.closure_notice.confirm_button')) ?></button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
