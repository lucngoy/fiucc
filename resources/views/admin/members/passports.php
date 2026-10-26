<?php
/**
 * Passeports expirés ou proches de l'expiration (Member::passportsExpiringBefore),
 * à transmettre au bureau ou à l'ambassade avant une mission biométrique.
 * Rouge : déjà expiré (alerte). Or : expire dans les 3 mois.
 */
$rows ??= [];
$months ??= 6;
$horizons ??= [3, 6, 12];

$today = strtotime(date('Y-m-d'));
$soon = strtotime('+3 months', $today);
?>
<div class="flex flex-col gap-6">
    <a href="/admin/members" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.passports.back')) ?>
    </a>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.passports.subtitle')) ?></p>
        <?php if ($rows !== []): ?>
            <a href="<?= e('/admin/members/passports?months=' . $months . '&format=csv') ?>"
               class="shrink-0 inline-flex items-center gap-2 h-9 px-4 rounded-control bg-white border border-border text-ink text-label-md font-label-md hover:bg-surface transition-colors">
                <?= icon('download') ?>
                <?= e(t('admin.passports.export')) ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-label-md font-label-md text-muted"><?= e(t('admin.passports.horizon')) ?></span>
        <?php foreach ($horizons as $horizon): ?>
            <?php $isSelected = $horizon === $months; ?>
            <a href="<?= e('/admin/members/passports?months=' . $horizon) ?>"
               class="inline-flex items-center h-9 px-3 rounded-full border text-label-md font-label-md transition-colors <?= $isSelected ? 'bg-primary border-primary text-white' : 'bg-white border-border text-ink hover:border-primary' ?>"
               <?= $isSelected ? 'aria-current="page"' : '' ?>>
                <?= e(t('admin.passports.months', ['count' => $horizon])) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <?php if ($rows === []): ?>
            <div class="p-6">
                <?= component('alert', ['type' => 'success', 'message' => t('admin.passports.none', ['count' => $months])]) ?>
            </div>
        <?php else: ?>
            <p class="p-4 border-b border-border text-body-sm text-muted"><?= e(t('admin.passports.count', ['count' => count($rows)])) ?></p>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.passports.column.member')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.passports.column.expiry')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase hidden md:table-cell"><?= e(t('admin.passports.column.passport_number')) ?></th>
                            <th class="p-3 text-label-caps font-label-caps uppercase hidden lg:table-cell"><?= e(t('admin.passports.column.whatsapp')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $expiry = strtotime((string) $row['passport_expiry']);
                            $badge = $expiry < $today
                                ? ['bg-danger/10 text-danger', t('admin.passports.badge.expired')]
                                : ($expiry <= $soon ? ['bg-gold/20 text-ink', t('admin.passports.badge.soon')] : null);
                            ?>
                            <tr class="border-b border-border last:border-0 align-top">
                                <td class="p-3 min-w-[180px]">
                                    <a href="<?= e('/admin/members/' . (int) $row['id']) ?>" class="block font-label-md text-label-md text-ink hover:text-primary"><?= e($row['full_name']) ?></a>
                                    <span class="text-body-sm text-muted"><?= e((string) $row['membership_number']) ?></span>
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <span class="block text-body-sm text-ink"><?= e(local_date((string) $row['passport_expiry'])) ?></span>
                                    <?php if ($badge !== null): ?>
                                        <span class="mt-1 inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md <?= e($badge[0]) ?>"><?= e($badge[1]) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-body-sm text-ink tabular-nums hidden md:table-cell"><?= e((string) ($row['passport_number'] ?? '—')) ?></td>
                                <td class="p-3 text-body-sm text-ink whitespace-nowrap hidden lg:table-cell"><?= e((string) ($row['whatsapp'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
