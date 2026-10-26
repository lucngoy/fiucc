<?php
/**
 * Demandes d'aide reçues (migration 028) : ouvertes d'abord, filtre par
 * statut. Le détail (message, coordonnées) n'est que sur la fiche.
 */
$requests ??= [];
$total ??= 0;
$status ??= null;
$counts ??= [];
$currentPage ??= 1;
$totalPages ??= 1;

$statusClasses = [
    'open'        => 'bg-gold/20 text-ink',
    'in_progress' => 'bg-primary/10 text-primary',
    'closed'      => 'bg-success/10 text-success',
];

$filters = ['' => t('admin.help.filter.all', ['count' => array_sum($counts)])];
foreach (\App\Models\HelpRequest::STATUSES as $statusKey) {
    $filters[$statusKey] = t('help.status.' . $statusKey) . ' (' . (int) ($counts[$statusKey] ?? 0) . ')';
}

$paginationBase = '/admin/help?' . ($status !== null ? 'status=' . rawurlencode($status) . '&' : '') . 'page={page}';
?>
<div class="flex flex-col gap-6">
    <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.help.subtitle')) ?></p>

    <div class="flex flex-wrap gap-2">
        <?php foreach ($filters as $value => $label): ?>
            <?php $isSelected = ($status ?? '') === $value; ?>
            <a href="<?= e($value === '' ? '/admin/help' : '/admin/help?status=' . $value) ?>"
               class="inline-flex items-center h-9 px-3 rounded-full border text-label-md font-label-md transition-colors <?= $isSelected ? 'bg-primary border-primary text-white' : 'bg-white border-border text-ink hover:border-primary' ?>"
               <?= $isSelected ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <?php if ($requests === []): ?>
            <div class="p-6">
                <?= component('alert', ['type' => 'info', 'message' => t('admin.help.empty')]) ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.help.column.reference')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.help.column.member')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.help.column.subject')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.help.column.status')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase hidden md:table-cell"><?= e(t('admin.help.column.date')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php $url = '/admin/help/' . (int) $request['id']; ?>
                            <tr class="border-b border-border last:border-0 align-top hover:bg-surface">
                                <td class="p-3 whitespace-nowrap">
                                    <a href="<?= e($url) ?>" class="font-label-md text-label-md text-primary hover:underline"><?= e(\App\Models\HelpRequest::reference($request)) ?></a>
                                </td>
                                <td class="p-3 text-body-sm text-ink min-w-[140px]"><?= e($request['full_name']) ?></td>
                                <td class="p-3 min-w-[200px]">
                                    <a href="<?= e($url) ?>" class="block text-body-sm text-ink hover:text-primary"><?= e($request['subject']) ?></a>
                                    <span class="text-body-sm text-muted"><?= e(t('help.category.' . $request['category'])) ?></span>
                                </td>
                                <td class="p-3">
                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md whitespace-nowrap <?= e($statusClasses[$request['status']] ?? '') ?>">
                                        <?= e(t('help.status.' . $request['status'])) ?>
                                    </span>
                                </td>
                                <td class="p-3 text-body-sm text-muted whitespace-nowrap hidden md:table-cell"><?= e(local_date($request['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?= component('pagination', ['currentPage' => $currentPage, 'totalPages' => $totalPages, 'baseUrl' => $paginationBase]) ?>
</div>
