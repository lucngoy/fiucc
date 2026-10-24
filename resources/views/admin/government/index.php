<?php
/**
 * Gouvernement — admin (lot 4bis). Un mandat sélectionné à la fois (le
 * courant par défaut), ses postes dans l'ordre d'affichage public. Les
 * actions courtes (mandat, suppression) passent par des <dialog>, comme
 * admin/partners/index.php ; un poste a son propre formulaire (photo).
 */
$mandates ??= [];
$selected ??= null;
$members ??= [];
$notice ??= '';

$noticeMap = [
    'mandate_created'       => ['type' => 'success', 'message' => t('admin.government.notice.mandate_created')],
    'mandate_updated'       => ['type' => 'success', 'message' => t('admin.government.notice.mandate_updated')],
    'mandate_deleted'       => ['type' => 'success', 'message' => t('admin.government.notice.mandate_deleted')],
    'made_current'          => ['type' => 'success', 'message' => t('admin.government.notice.made_current')],
    'member_added'          => ['type' => 'success', 'message' => t('admin.government.notice.member_added')],
    'member_updated'        => ['type' => 'success', 'message' => t('admin.government.notice.member_updated')],
    'member_removed'        => ['type' => 'success', 'message' => t('admin.government.notice.member_removed')],
    'members_imported'      => ['type' => 'success', 'message' => t('admin.government.notice.members_imported', ['count' => (string) ($importedCount ?? 0)])],
    'mandate_invalid'       => ['type' => 'error',   'message' => t('admin.government.notice.mandate_invalid')],
    'mandate_dates_invalid' => ['type' => 'error',   'message' => t('admin.government.notice.mandate_dates_invalid')],
    'mandate_duplicate'     => ['type' => 'error',   'message' => t('admin.government.notice.mandate_duplicate')],
    'mandate_not_deletable' => ['type' => 'error',   'message' => t('admin.government.notice.mandate_not_deletable')],
    'not_found'             => ['type' => 'error',   'message' => t('admin.government.notice.not_found')],
    'order_invalid'         => ['type' => 'error',   'message' => t('admin.government.notice.order_invalid')],
];

$actionTooltip = static function (string $label): string {
    return '<span role="tooltip" class="pointer-events-none absolute right-0 top-full mt-1.5 whitespace-nowrap rounded bg-ink text-white text-label-caps font-label-caps px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">'
        . e($label) . '</span>';
};

$inputClasses = 'h-[42px] w-full rounded-control border border-border px-3 text-body-md text-ink bg-white focus:outline-none focus:ring-2 focus:border-primary focus:ring-primary-light/50';

/** Formulaire de mandat (création ou modification) — mêmes champs dans les deux dialogues. */
$mandateFields = static function (?array $mandate, string $prefix) use ($inputClasses): string {
    $fields = [
        ['mandate', 'text', t('admin.government.mandate.field.label'), (string) ($mandate['mandate'] ?? ''), 'maxlength="20" placeholder="2026-2027"'],
        ['start_date', 'date', t('admin.government.mandate.field.start_date'), (string) ($mandate['start_date'] ?? ''), ''],
        ['end_date', 'date', t('admin.government.mandate.field.end_date'), (string) ($mandate['end_date'] ?? ''), ''],
    ];
    $html = '';

    foreach ($fields as [$name, $type, $label, $value, $extra]) {
        $id = $prefix . '-' . $name;
        $html .= '<div class="flex flex-col gap-1.5">'
            . '<label for="' . e($id) . '" class="font-label-lg text-label-lg text-ink">' . e($label) . '</label>'
            . '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" required ' . $extra
            . ' class="' . e($inputClasses) . '"></div>';
    }

    return $html;
};

$selectedId = $selected !== null ? (int) $selected['id'] : 0;
$isCurrent = $selected !== null && (int) $selected['is_current'] === 1;
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted max-w-2xl"><?= e(t('admin.government.intro')) ?></p>
        <button type="button" data-open-dialog="mandate-create-dialog"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
            <?= icon('plus') ?>
            <?= e(t('admin.government.mandate.create_button')) ?>
        </button>
    </div>

    <?php if ($mandates === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.government.empty_mandates')]) ?>
    <?php else: ?>
        <nav aria-label="<?= e(t('admin.government.mandate.switch_label')) ?>" class="flex flex-wrap gap-2">
            <?php foreach ($mandates as $mandate): ?>
                <?php $isSelected = (int) $mandate['id'] === $selectedId; ?>
                <a href="/admin/government?mandate=<?= e((string) $mandate['id']) ?>"
                   class="inline-flex items-center gap-2 h-9 px-3 rounded-full border text-label-md font-label-md transition-colors <?= $isSelected ? 'bg-primary border-primary text-white' : 'bg-white border-border text-ink hover:border-primary' ?>"
                   <?= $isSelected ? 'aria-current="page"' : '' ?>>
                    <?= e($mandate['mandate']) ?>
                    <?php if ((int) $mandate['is_current'] === 1): ?>
                        <span class="inline-flex items-center h-5 px-2 rounded-full text-label-caps font-label-caps <?= $isSelected ? 'bg-white/20 text-white' : 'bg-gold/20 text-ink' ?>"><?= e(t('admin.government.mandate.current_badge')) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($selected !== null): ?>
        <section class="bg-white rounded-card border border-border p-5 md:p-6 flex flex-col gap-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.government.mandate.heading', ['mandate' => $selected['mandate']])) ?></h2>
                        <?php if ($isCurrent): ?>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('admin.government.mandate.current_badge')) ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-surface text-muted text-label-md font-label-md"><?= e(t('admin.government.mandate.not_current_badge')) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-body-sm text-muted">
                        <?= e(t('admin.government.mandate.dates', [
                            'start' => local_date($selected['start_date']),
                            'end'   => local_date($selected['end_date']),
                        ])) ?>
                    </p>
                    <p class="text-body-sm text-muted"><?= e(t($isCurrent ? 'admin.government.mandate.current_help' : 'admin.government.mandate.not_current_help')) ?></p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <button type="button" data-open-dialog="mandate-edit-dialog"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-control border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
                        <?= icon('pen') ?>
                        <?= e(t('admin.government.mandate.edit_button')) ?>
                    </button>
                    <?php if (!$isCurrent): ?>
                        <button type="button" data-open-dialog="mandate-current-dialog"
                            class="inline-flex items-center gap-2 h-9 px-3 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors">
                            <?= icon('flag') ?>
                            <?= e(t('admin.government.mandate.make_current_button')) ?>
                        </button>
                        <?php if ($members === []): ?>
                            <button type="button" data-open-dialog="mandate-delete-dialog"
                                class="inline-flex items-center gap-2 h-9 px-3 rounded-control border border-danger/40 text-danger text-label-md font-label-md hover:bg-danger/[4%] transition-colors">
                                <?= icon('trash') ?>
                                <?= e(t('admin.government.mandate.delete_button')) ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.members.heading', ['count' => count($members)])) ?></h2>
                    <p class="text-body-sm text-muted"><?= e(t('admin.government.members.elected_help')) ?></p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <a href="/admin/government/mandates/<?= e((string) $selectedId) ?>/import"
                       class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control border border-border bg-white text-ink text-label-lg font-label-lg hover:bg-surface transition-colors">
                        <?= icon('file-lines') ?>
                        <?= e(t('admin.government.members.import_button')) ?>
                    </a>
                    <a href="/admin/government/mandates/<?= e((string) $selectedId) ?>/members/create"
                       class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                        <?= icon('user-plus') ?>
                        <?= e(t('admin.government.members.add_button')) ?>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-card border border-border overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="p-3 text-label-caps font-label-caps uppercase w-24"><?= e(t('admin.government.column.order')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.government.column.person')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.government.column.role')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase hidden lg:table-cell"><?= e(t('admin.government.column.faculty')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.government.column.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody data-government-sort>
                        <?php if ($members === []): ?>
                            <tr>
                                <td colspan="5" class="p-6">
                                    <?= component('alert', ['type' => 'info', 'message' => t('admin.government.members.empty')]) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $lastIndex = count($members) - 1; ?>
                            <?php foreach ($members as $index => $member): ?>
                                <?php $memberId = (int) $member['id']; ?>
                                <tr id="member-<?= e((string) $memberId) ?>" data-member-id="<?= e((string) $memberId) ?>" draggable="true" class="border-b border-border last:border-0 align-middle bg-white">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="hidden md:flex h-8 w-5 items-center justify-center text-muted cursor-grab active:cursor-grabbing" title="<?= e(t('admin.government.action.drag', ['name' => $member['display_name']])) ?>" aria-hidden="true">
                                                <?= icon('grip-vertical') ?>
                                            </span>
                                            <span data-position class="w-6 text-body-sm text-muted tabular-nums"><?= e((string) ($index + 1)) ?></span>
                                            <?php foreach (['up', 'down'] as $direction): ?>
                                                <?php $disabled = ($direction === 'up' && $index === 0) || ($direction === 'down' && $index === $lastIndex); ?>
                                                <form method="POST" action="/admin/government/members/<?= e((string) $memberId) ?>/move">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="direction" value="<?= e($direction) ?>">
                                                    <button type="submit" <?= $disabled ? 'disabled' : '' ?>
                                                        aria-label="<?= e(t('admin.government.action.move_' . $direction, ['name' => $member['display_name']])) ?>"
                                                        class="h-8 w-8 rounded-control bg-surface text-primary hover:bg-primary/15 disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center transition-colors">
                                                        <?= icon('chevron-down', $direction === 'up' ? 'rotate-180' : '') ?>
                                                    </button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-3 min-w-[180px]">
                                            <?php if (!empty($member['photo'])): ?>
                                                <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-10 w-10 rounded-full object-cover border border-border shrink-0">
                                            <?php else: ?>
                                                <span class="h-10 w-10 rounded-full bg-primary/10 text-primary text-label-md font-label-md flex items-center justify-center shrink-0"><?= e(initials((string) $member['display_name'])) ?></span>
                                            <?php endif; ?>
                                            <div class="min-w-0">
                                                <p class="font-label-lg text-label-lg text-ink"><?= e($member['display_name']) ?></p>
                                                <?php if ($member['member_id'] !== null): ?>
                                                    <p class="inline-flex items-center gap-1 text-body-sm text-success"><?= icon('circle-check') ?><?= e(t('admin.government.members.linked')) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-body-sm text-ink min-w-[160px]"><?= e($member['role']) ?></td>
                                    <td class="p-3 text-body-sm text-muted hidden lg:table-cell"><?= e($member['faculty'] ?? '—') ?></td>
                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <div class="relative group">
                                                <a href="/admin/government/members/<?= e((string) $memberId) ?>/edit" aria-label="<?= e(t('admin.government.action.edit')) ?>"
                                                    class="h-9 w-9 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center justify-center transition-colors">
                                                    <?= icon('pen') ?>
                                                </a>
                                                <?= $actionTooltip(t('admin.government.action.edit')) ?>
                                            </div>
                                            <div class="relative group">
                                                <button type="button" data-open-dialog="member-delete-<?= e((string) $memberId) ?>"
                                                    aria-label="<?= e(t('admin.government.action.remove')) ?>"
                                                    class="h-9 w-9 rounded-control bg-surface text-danger hover:bg-danger/15 flex items-center justify-center transition-colors">
                                                    <?= icon('trash') ?>
                                                </button>
                                                <?= $actionTooltip(t('admin.government.action.remove')) ?>
                                            </div>
                                        </div>
                                        <dialog id="member-delete-<?= e((string) $memberId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                            <form method="POST" action="/admin/government/members/<?= e((string) $memberId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                                                <?= csrf_field() ?>
                                                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.remove.confirm_title')) ?></h2>
                                                <p class="text-body-sm text-muted"><?= e(t('admin.government.remove.confirm_message', ['name' => $member['display_name'], 'role' => $member['role']])) ?></p>
                                                <div class="flex justify-end gap-2 mt-1">
                                                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></button>
                                                    <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.government.remove.confirm_button')) ?></button>
                                                </div>
                                            </form>
                                        </dialog>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php if (count($members) > 1): ?>
                <!-- Rempli et envoyé par admin-government-sort.js après un
                     glisser-déposer : l'ordre complet, ids[] du premier au
                     dernier. -->
                <form method="POST" action="/admin/government/mandates/<?= e((string) $selected['id']) ?>/reorder" data-government-sort-form hidden>
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>
        </section>

        <dialog id="mandate-edit-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-md backdrop:bg-ink/40">
            <form method="POST" action="/admin/government/mandates/<?= e((string) $selectedId) ?>" class="flex flex-col gap-4 p-5 text-left">
                <?= csrf_field() ?>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.mandate.edit_title')) ?></h2>
                <?= $mandateFields($selected, 'mandate-edit') ?>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></button>
                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.government.action.save')) ?></button>
                </div>
            </form>
        </dialog>

        <?php if (!$isCurrent): ?>
            <dialog id="mandate-current-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                <form method="POST" action="/admin/government/mandates/<?= e((string) $selectedId) ?>/current" class="flex flex-col gap-2 p-4 text-left">
                    <?= csrf_field() ?>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.mandate.make_current_title', ['mandate' => $selected['mandate']])) ?></h2>
                    <p class="text-body-sm text-muted"><?= e(t('admin.government.mandate.make_current_message')) ?></p>
                    <div class="flex justify-end gap-2 mt-1">
                        <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></button>
                        <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.government.mandate.make_current_button')) ?></button>
                    </div>
                </form>
            </dialog>

            <?php if ($members === []): ?>
                <dialog id="mandate-delete-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                    <form method="POST" action="/admin/government/mandates/<?= e((string) $selectedId) ?>/delete" class="flex flex-col gap-2 p-4 text-left">
                        <?= csrf_field() ?>
                        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.mandate.delete_title', ['mandate' => $selected['mandate']])) ?></h2>
                        <p class="text-body-sm text-muted"><?= e(t('admin.government.mandate.delete_message')) ?></p>
                        <div class="flex justify-end gap-2 mt-1">
                            <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></button>
                            <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.government.mandate.delete_button')) ?></button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <dialog id="mandate-create-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-md backdrop:bg-ink/40">
        <form method="POST" action="/admin/government/mandates" class="flex flex-col gap-4 p-5 text-left">
            <?= csrf_field() ?>
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.mandate.create_title')) ?></h2>
            <p class="text-body-sm text-muted"><?= e(t('admin.government.mandate.create_help')) ?></p>
            <?= $mandateFields(null, 'mandate-create') ?>
            <div class="flex justify-end gap-2">
                <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.government.action.cancel')) ?></button>
                <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.government.mandate.create_button')) ?></button>
            </div>
        </form>
    </dialog>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
<script src="/assets/js/admin-tooltip.js" defer></script>
<script src="/assets/js/admin-government-sort.js" defer></script>
