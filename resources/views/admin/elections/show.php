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
 * prolongation/réduction de end_at (seule écriture possible hors 'draft',
 * décision explicite FIUCC-59). Le contrôleur applique la même règle côté
 * serveur (App\Models\Election) : ce que cette vue cache n'est jamais la
 * seule protection.
 */
$election ??= [];
$positions ??= [];
$notice ??= '';

$isDraft = $election['status'] === 'draft';
$isCandidacies = $election['status'] === 'candidacies';
$isOpen = $election['status'] === 'open';
$isClosed = $election['status'] === 'closed';
$isPublished = $election['status'] === 'published';
$canDelete = $isDraft && $positions === [];
$canStartCandidacies = $isDraft && $positions !== [];

$statusBadge = static function (string $status): string {
    $variants = [
        'draft'       => 'bg-muted/[1%] text-muted',
        'candidacies' => 'bg-gold/20 text-ink',
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
    'cannot_publish'           => ['type' => 'error',   'message' => t('admin.elections.notice.cannot_publish')],
    'results_not_ready'        => ['type' => 'error',   'message' => t('admin.elections.notice.results_not_ready')],
    'reminder_too_soon'        => ['type' => 'error',   'message' => t('admin.elections.notice.reminder_too_soon')],
    'not_closed'               => ['type' => 'error',   'message' => t('admin.elections.notice.not_closed')],
    'reminder_sent'            => ['type' => 'success', 'message' => t('admin.elections.notice.reminder_sent', ['count' => (string) ($_GET['count'] ?? '0')])],
    'closure_notice_sent'      => ['type' => 'success', 'message' => t('admin.elections.notice.closure_notice_sent', ['count' => (string) ($_GET['count'] ?? '0')])],
];

$inputClasses = 'h-10 rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50';
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <?= e(t('admin.elections.title')) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
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
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    <?= e(t('admin.elections.action.manage_candidates')) ?>
                </a>
                <?php if ($isOpen): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/tokens"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.voting_tokens')) ?>
                    </a>
                    <button type="button" data-open-dialog="send-reminder-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <i class="fa-solid fa-bell" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.send_reminder')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed): ?>
                    <button type="button" data-open-dialog="send-closure-notice-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.send_closure_notice')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($canStartCandidacies): ?>
                    <button type="button" data-open-dialog="start-candidacies-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.start_candidacies')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isCandidacies): ?>
                    <button type="button" data-open-dialog="open-election-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.open')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isOpen): ?>
                    <button type="button" data-open-dialog="close-election-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-danger text-label-md font-label-md hover:bg-danger/15 transition-colors">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.close')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed): ?>
                    <button type="button" data-open-dialog="publish-results-dialog"
                        class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                        <i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.publish')) ?>
                    </button>
                <?php endif; ?>
                <?php if ($isClosed || $isPublished): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/results"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                        <?= e(t('elections.detail.view_results')) ?>
                    </a>
                <?php endif; ?>
                <?php if ($isDraft): ?>
                    <a href="/admin/elections/<?= e((string) $election['id']) ?>/edit"
                       class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        <?= e(t('admin.elections.action.edit')) ?>
                    </a>
                    <?php if ($canDelete): ?>
                        <button type="button" data-open-dialog="delete-election-dialog"
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-danger text-label-md font-label-md hover:bg-danger/15 transition-colors">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
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
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.start_at')) ?></p>
                <p><?= e(local_date($election['start_at'])) ?></p>
            </div>
            <div>
                <p class="text-label-caps font-label-caps text-muted uppercase mb-1"><?= e(t('admin.elections.field.end_at')) ?></p>
                <p><?= e(local_date($election['end_at'])) ?></p>
            </div>
        </div>

        <?php if (!$isDraft): ?>
            <div class="pt-2 border-t border-border">
                <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.locked_notice')]) ?>
            </div>
        <?php endif; ?>
    </div>

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
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
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

    <?php if ($isCandidacies): ?>
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
