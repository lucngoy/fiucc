<?php
/**
 * Liste des membres — admin (FIUCC-43) et actions de validation/rejet/
 * suspension, unitaires et en masse (FIUCC-44). Section 24 du cahier des
 * charges ; style rapproché de la maquette fiucc_portal_admin_dashboard/
 * code.html (cartes blanches arrondies sur fond bg-surface).
 *
 * Structure des formulaires : un seul <form> $bulkFormId, vide, sert de
 * cible aux cases à cocher et aux boutons "en masse" via l'attribut HTML5
 * form="..." — elles ne sont physiquement pas dans ce <form>, ce qui évite
 * d'imbriquer un <form> dans un autre alors que chaque ligne a aussi son
 * propre petit formulaire pour les actions unitaires (validate/reject/
 * suspend), avec sa propre action et son propre CSRF.
 *
 * Sécurité de la validation groupée (consigne explicite du ticket) : le vrai
 * garde-fou est serveur (Admin\MemberController — ne touche que les membres
 * réellement dans le statut source attendu). admin-members.js n'ajoute que
 * la confirmation explicite avec le nombre d'éléments concernés, côté
 * client — une commodité, pas la protection elle-même.
 */
$bulkFormId = 'members-bulk-form';

$members ??= [];
$filters ??= ['search' => '', 'status' => '', 'faculty' => '', 'year' => ''];
$faculties ??= [];
$studyLevels ??= [];
$sort ??= 'submitted';
$dir ??= 'desc';
$currentPage ??= 1;
$totalPages ??= 1;
$total ??= 0;
$notice ??= '';
$requested ??= 0;
$applied ??= 0;

$currentUrl = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/members');

$activeFilterQuery = array_filter([
    'search'  => $filters['search'],
    'status'  => $filters['status'],
    'faculty' => $filters['faculty'],
    'year'    => $filters['year'],
], static fn ($value) => $value !== '');

$sortUrl = static function (string $column) use ($activeFilterQuery, $sort, $dir): string {
    $newDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    $query = $activeFilterQuery;
    $query['sort'] = $column;
    $query['dir'] = $newDir;

    return '/admin/members?' . http_build_query($query);
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '<i class="fa-solid fa-sort text-white/30" aria-hidden="true"></i>';
    }

    return $dir === 'asc'
        ? '<i class="fa-solid fa-sort-up" aria-hidden="true"></i>'
        : '<i class="fa-solid fa-sort-down" aria-hidden="true"></i>';
};

$statusBadge = static function (string $status): string {
    $variants = [
        'pending'   => 'bg-gold/20 text-ink',
        'validated' => 'bg-success/15 text-success',
        'rejected'  => 'bg-danger/10 text-danger',
        'suspended' => 'bg-muted/15 text-muted',
    ];
    $classes = $variants[$status] ?? $variants['pending'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md ' . $classes . '">'
        . e(t('admin.members.status.' . $status)) . '</span>';
};

$noticeMap = [
    'validated'          => ['type' => 'success', 'message' => t('admin.members.notice.validated')],
    'rejected'           => ['type' => 'success', 'message' => t('admin.members.notice.rejected')],
    'suspended'          => ['type' => 'success', 'message' => t('admin.members.notice.suspended')],
    'reason_required'    => ['type' => 'error',   'message' => t('admin.members.notice.reason_required')],
    'self_action_denied' => ['type' => 'error',   'message' => t('admin.members.notice.self_action_denied')],
    'bulk_none_selected' => ['type' => 'warning', 'message' => t('admin.members.notice.bulk_none_selected')],
    'bulk_validated'      => ['type' => 'success', 'message' => t('admin.members.notice.bulk_validated', ['applied' => $applied, 'requested' => $requested])],
    'bulk_rejected'       => ['type' => 'success', 'message' => t('admin.members.notice.bulk_rejected', ['applied' => $applied, 'requested' => $requested])],
    'bulk_suspended'      => ['type' => 'success', 'message' => t('admin.members.notice.bulk_suspended', ['applied' => $applied, 'requested' => $requested])],
];

$paginationBase = '/admin/members?' . ($activeFilterQuery !== [] || $sort !== 'submitted' || $dir !== 'desc'
    ? http_build_query(array_merge($activeFilterQuery, ['sort' => $sort, 'dir' => $dir])) . '&'
    : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted"><?= e(t('admin.members.subtitle')) ?></p>
        <p class="text-body-sm text-muted"><?= e(t('admin.members.total', ['count' => $total])) ?></p>
    </div>

    <form method="GET" action="/admin/members" class="bg-white rounded-card border border-border p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="member-search" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.members.search_label')) ?></label>
            <input type="search" id="member-search" name="search" value="<?= e($filters['search']) ?>"
                placeholder="<?= e(t('admin.members.search_placeholder')) ?>"
                class="h-10 w-full rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        </div>
        <div>
            <label for="member-status" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.members.filter.status')) ?></label>
            <select id="member-status" name="status" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.members.filter.status_all')) ?></option>
                <?php foreach (['pending', 'validated', 'rejected', 'suspended'] as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>>
                        <?= e(t('admin.members.status.' . $statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="member-faculty" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.members.filter.faculty')) ?></label>
            <select id="member-faculty" name="faculty" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white max-w-[220px]">
                <option value=""><?= e(t('admin.members.filter.faculty_all')) ?></option>
                <?php foreach ($faculties as $facultyOption): ?>
                    <option value="<?= e($facultyOption) ?>" <?= $filters['faculty'] === $facultyOption ? 'selected' : '' ?>><?= e($facultyOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="member-year" class="block text-label-md font-label-md text-muted mb-1"><?= e(t('admin.members.filter.year')) ?></label>
            <select id="member-year" name="year" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('admin.members.filter.year_all')) ?></option>
                <?php foreach ($studyLevels as $yearValue => $yearLabel): ?>
                    <option value="<?= e((string) $yearValue) ?>" <?= $filters['year'] === (string) $yearValue ? 'selected' : '' ?>><?= e($yearLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.members.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/admin/members" class="h-10 px-3 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('admin.members.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <form method="POST" id="<?= e($bulkFormId) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($currentUrl) ?>">
    </form>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 w-10">
                        <input type="checkbox" id="member-select-all" aria-label="<?= e(t('admin.members.bulk.select_all')) ?>"
                            class="h-4 w-4 rounded border-white/50">
                    </th>
                    <th class="p-3">
                        <a href="<?= e($sortUrl('name')) ?>" class="inline-flex items-center gap-1.5 text-label-caps font-label-caps uppercase hover:underline">
                            <?= e(t('admin.members.column.member')) ?> <?= $sortIcon('name') ?>
                        </a>
                    </th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.members.column.student_id')) ?></th>
                    <th class="p-3">
                        <a href="<?= e($sortUrl('faculty')) ?>" class="inline-flex items-center gap-1.5 text-label-caps font-label-caps uppercase hover:underline">
                            <?= e(t('admin.members.column.faculty_level')) ?> <?= $sortIcon('faculty') ?>
                        </a>
                    </th>
                    <th class="p-3">
                        <a href="<?= e($sortUrl('status')) ?>" class="inline-flex items-center gap-1.5 text-label-caps font-label-caps uppercase hover:underline">
                            <?= e(t('admin.members.column.status')) ?> <?= $sortIcon('status') ?>
                        </a>
                    </th>
                    <th class="p-3">
                        <a href="<?= e($sortUrl('submitted')) ?>" class="inline-flex items-center gap-1.5 text-label-caps font-label-caps uppercase hover:underline">
                            <?= e(t('admin.members.column.submitted')) ?> <?= $sortIcon('submitted') ?>
                        </a>
                    </th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.members.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members === []): ?>
                    <tr>
                        <td colspan="7" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.members.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($members as $member): ?>
                        <?php
                        $memberId = (int) $member['id'];
                        $rowActionUrl = static fn (string $action): string => '/admin/members/' . $memberId . '/' . $action;
                        ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3">
                                <input type="checkbox" form="<?= e($bulkFormId) ?>" name="ids[]" value="<?= e((string) $memberId) ?>"
                                    class="member-row-checkbox h-4 w-4 rounded border-border text-primary focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($member['photo'])): ?>
                                        <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-9 w-9 rounded-full object-cover border border-border shrink-0">
                                    <?php else: ?>
                                        <span class="h-9 w-9 rounded-full bg-primary/15 text-primary flex items-center justify-center font-label-md text-label-md shrink-0">
                                            <?= e(initials($member['full_name'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="font-label-lg text-label-lg text-ink truncate"><?= e($member['full_name']) ?></p>
                                        <p class="text-body-sm text-muted truncate"><?= e($member['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-body-sm text-ink font-mono"><?= e($member['student_id']) ?></td>
                            <td class="p-3">
                                <p class="text-body-sm text-ink"><?= e($member['faculty'] ?? '—') ?></p>
                                <?php if ($member['year'] !== null && isset($studyLevels[$member['year']])): ?>
                                    <p class="text-label-caps font-label-caps text-muted"><?= e($studyLevels[$member['year']]) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="p-3"><?= $statusBadge($member['status']) ?></td>
                            <td class="p-3 text-body-sm text-muted"><?= e(local_date($member['created_at'])) ?></td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if (in_array($member['status'], ['pending', 'rejected', 'suspended'], true)): ?>
                                        <form method="POST" action="<?= e($rowActionUrl('validate')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="return_to" value="<?= e($currentUrl) ?>">
                                            <button type="submit" title="<?= e(t('admin.members.action.validate')) ?>" aria-label="<?= e(t('admin.members.action.validate')) ?>"
                                                class="h-9 w-9 rounded-control bg-surface text-success hover:bg-success/15 flex items-center justify-center transition-colors">
                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($member['status'] === 'pending'): ?>
                                        <button type="button" data-open-dialog="reason-dialog-reject-<?= e((string) $memberId) ?>"
                                            title="<?= e(t('admin.members.action.reject')) ?>" aria-label="<?= e(t('admin.members.action.reject')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                        <dialog id="reason-dialog-reject-<?= e((string) $memberId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                            <form method="POST" action="<?= e($rowActionUrl('reject')) ?>" class="flex flex-col gap-2 p-4 text-left">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($currentUrl) ?>">
                                                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.members.reason.confirm_reject')) ?></h2>
                                                <label for="reject-reason-<?= e((string) $memberId) ?>" class="text-label-md font-label-md text-muted"><?= e(t('admin.members.reason.label')) ?></label>
                                                <textarea id="reject-reason-<?= e((string) $memberId) ?>" name="reason" required rows="3"
                                                    class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                                                <div class="flex justify-end gap-2 mt-1">
                                                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.members.action.cancel')) ?></button>
                                                    <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.members.reason.confirm_reject')) ?></button>
                                                </div>
                                            </form>
                                        </dialog>
                                    <?php endif; ?>

                                    <?php if ($member['status'] === 'validated'): ?>
                                        <button type="button" data-open-dialog="reason-dialog-suspend-<?= e((string) $memberId) ?>"
                                            title="<?= e(t('admin.members.action.suspend')) ?>" aria-label="<?= e(t('admin.members.action.suspend')) ?>"
                                            class="h-9 w-9 rounded-control bg-surface text-ink hover:bg-ink/10 flex items-center justify-center transition-colors">
                                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                        </button>
                                        <dialog id="reason-dialog-suspend-<?= e((string) $memberId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                            <form method="POST" action="<?= e($rowActionUrl('suspend')) ?>" class="flex flex-col gap-2 p-4 text-left">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="return_to" value="<?= e($currentUrl) ?>">
                                                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.members.reason.confirm_suspend')) ?></h2>
                                                <label for="suspend-reason-<?= e((string) $memberId) ?>" class="text-label-md font-label-md text-muted"><?= e(t('admin.members.reason.label')) ?></label>
                                                <textarea id="suspend-reason-<?= e((string) $memberId) ?>" name="reason" required rows="3"
                                                    class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                                                <div class="flex justify-end gap-2 mt-1">
                                                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.members.action.cancel')) ?></button>
                                                    <button type="submit" class="h-9 px-4 rounded-control bg-ink text-white text-label-md font-label-md hover:bg-ink/90 transition-colors"><?= e(t('admin.members.reason.confirm_suspend')) ?></button>
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

        <?php if ($members !== []): ?>
            <div class="flex flex-wrap items-center gap-3 p-4 bg-surface border-t border-border">
                <span id="bulk-selected-count" class="text-label-md font-label-md text-muted" data-template="<?= e(t('admin.members.bulk.selected', ['count' => '__COUNT__'])) ?>">
                    <?= e(t('admin.members.bulk.selected', ['count' => 0])) ?>
                </span>
                <button type="submit" form="<?= e($bulkFormId) ?>" formaction="/admin/members/bulk-validate"
                    data-bulk-action="validate"
                    data-confirm-template="<?= e(t('admin.members.bulk.confirm_validate', ['count' => '__COUNT__'])) ?>"
                    data-label-template="<?= e(t('admin.members.bulk.validate_count', ['count' => '__COUNT__'])) ?>"
                    disabled
                    class="h-9 px-4 rounded-control bg-success text-white text-label-md font-label-md hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <?= e(t('admin.members.bulk.validate_count', ['count' => 0])) ?>
                </button>
                <!-- Rejeter/suspendre en masse : pas de soumission directe — un
                     clic ouvre tout de suite le dialogue avec le champ Raison
                     (data-open-reason-dialog), plutôt que d'exiger de remplir
                     un champ discret dans cette barre au préalable et de
                     sanctionner l'oubli par un second dialogue d'erreur. -->
                <button type="button"
                    data-bulk-action="reject"
                    data-open-reason-dialog="1"
                    data-reason-dialog-title-template="<?= e(t('admin.members.bulk.reason_dialog_reject', ['count' => '__COUNT__'])) ?>"
                    data-reason-dialog-action="/admin/members/bulk-reject"
                    data-reason-dialog-confirm-label="<?= e(t('admin.members.reason.confirm_reject')) ?>"
                    data-reason-dialog-confirm-class="bg-danger hover:bg-danger/90"
                    data-label-template="<?= e(t('admin.members.bulk.reject_count', ['count' => '__COUNT__'])) ?>"
                    disabled
                    class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <?= e(t('admin.members.bulk.reject_count', ['count' => 0])) ?>
                </button>
                <button type="button"
                    data-bulk-action="suspend"
                    data-open-reason-dialog="1"
                    data-reason-dialog-title-template="<?= e(t('admin.members.bulk.reason_dialog_suspend', ['count' => '__COUNT__'])) ?>"
                    data-reason-dialog-action="/admin/members/bulk-suspend"
                    data-reason-dialog-confirm-label="<?= e(t('admin.members.reason.confirm_suspend')) ?>"
                    data-reason-dialog-confirm-class="bg-ink hover:bg-ink/90"
                    data-label-template="<?= e(t('admin.members.bulk.suspend_count', ['count' => '__COUNT__'])) ?>"
                    disabled
                    class="h-9 px-4 rounded-control bg-ink text-white text-label-md font-label-md hover:bg-ink/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <?= e(t('admin.members.bulk.suspend_count', ['count' => 0])) ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?= component('pagination', ['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => $paginationBase]) ?>
</div>

<!-- Modaux remplaçant window.confirm() pour les actions en masse (même
     mécanique data-open-dialog/data-close-dialog que les dialogues de
     raison par ligne ci-dessus) — un dialogue natif du navigateur ne peut
     pas reprendre la charte graphique et coupe le flux visuel de la page. -->
<dialog id="bulk-confirm-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
    <div class="flex flex-col gap-3 p-4 text-left">
        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.members.bulk.confirm_title')) ?></h2>
        <p id="bulk-confirm-message" class="text-body-sm text-muted"></p>
        <div class="flex justify-end gap-2 mt-1">
            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.members.action.cancel')) ?></button>
            <button type="button" id="bulk-confirm-submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.members.action.confirm')) ?></button>
        </div>
    </div>
</dialog>

<!-- Un seul dialogue de saisie, réutilisé pour rejeter et suspendre en
     masse : titre, action du formulaire et couleur du bouton de
     confirmation sont réécrits par admin-members.js selon le bouton
     cliqué. Le textarea est hors du <form> $bulkFormId mais lui reste
     associé via l'attribut form="..." (comme les cases à cocher des
     lignes) — sa valeur part avec la sélection à la soumission. -->
<dialog id="bulk-reason-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
    <div class="flex flex-col gap-2 p-4 text-left">
        <h2 id="bulk-reason-dialog-title" class="font-label-lg text-label-lg text-ink"></h2>
        <label for="bulk-reason-dialog-textarea" class="text-label-md font-label-md text-muted"><?= e(t('admin.members.bulk.reason_label')) ?></label>
        <textarea id="bulk-reason-dialog-textarea" form="<?= e($bulkFormId) ?>" name="reason" rows="3"
            placeholder="<?= e(t('admin.members.bulk.reason_placeholder')) ?>"
            class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
        <div class="flex justify-end gap-2 mt-1">
            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.members.action.cancel')) ?></button>
            <button type="submit" id="bulk-reason-dialog-confirm" form="<?= e($bulkFormId) ?>"
                class="h-9 px-4 rounded-control text-white text-label-md font-label-md transition-colors"></button>
        </div>
    </div>
</dialog>

<script src="/assets/js/admin-members.js" defer></script>
