<?php
/**
 * Mises à jour de la base — admin. Liste des migrations du dépôt, appliquées
 * ou en attente, et un bouton pour appliquer celles en attente (voir
 * Admin\MigrationController). Pensé pour un hébergement sans SSH.
 */
$migrations ??= [];
$notice ??= '';
$result ??= null;
$keysPlan ??= ['statements' => [], 'missing_tables' => []];
$repair ??= null;
$keysMissing = count($keysPlan['statements']);

$pending = array_values(array_filter($migrations, static fn (array $row): bool => $row['applied_at'] === null));

$noticeMap = [
    'applied' => ['type' => 'success', 'message' => t('admin.migrations.notice.applied', ['count' => (string) count($result['applied'] ?? [])])],
    'nothing' => ['type' => 'info',    'message' => t('admin.migrations.notice.nothing')],
    'failed'  => ['type' => 'error',   'message' => t('admin.migrations.notice.failed', ['name' => (string) ($result['failed'] ?? '')])],
    'keys_repaired'          => ['type' => 'success', 'message' => t('admin.migrations.keys.notice.repaired', ['count' => (string) count($repair['done'] ?? [])])],
    'keys_repair_incomplete' => ['type' => 'error',   'message' => t('admin.migrations.keys.notice.incomplete', ['count' => (string) count($repair['failed'] ?? [])])],
];
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
        <?php if ($notice === 'failed' && !empty($result['error'])): ?>
            <pre class="bg-white rounded-card border border-danger/40 p-4 text-body-sm text-danger whitespace-pre-wrap break-words"><?= e((string) $result['error']) ?></pre>
        <?php endif; ?>
        <?php if ($notice === 'keys_repair_incomplete'): ?>
            <?php foreach ($repair['failed'] ?? [] as $failure): ?>
                <pre class="bg-white rounded-card border border-danger/40 p-4 text-body-sm text-danger whitespace-pre-wrap break-words"><?= e($failure['sql'] . "\n→ " . $failure['error']) ?></pre>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Structure de la base (App\Services\SchemaKeys) — à vérifier AVANT
         les mises à jour : une table sans AUTO_INCREMENT fait échouer
         jusqu'à l'enregistrement d'une migration appliquée. -->
    <div id="structure" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.migrations.keys.heading')) ?></h2>
        <p class="text-body-sm text-muted"><?= e(t('admin.migrations.keys.help')) ?></p>

        <?php if ($keysPlan['missing_tables'] !== []): ?>
            <?= component('alert', ['type' => 'warning', 'message' => t('admin.migrations.keys.missing_tables', ['tables' => implode(', ', $keysPlan['missing_tables'])])]) ?>
        <?php endif; ?>

        <?php if ($keysMissing === 0): ?>
            <?= component('alert', ['type' => 'success', 'message' => t('admin.migrations.keys.ok')]) ?>
        <?php else: ?>
            <?= component('alert', ['type' => 'warning', 'message' => t('admin.migrations.keys.missing', ['count' => (string) $keysMissing])]) ?>
            <details class="rounded-control border border-border p-3">
                <summary class="cursor-pointer text-label-md font-label-md text-ink"><?= e(t('admin.migrations.keys.details')) ?></summary>
                <pre class="mt-3 text-body-sm text-ink whitespace-pre-wrap break-words"><?= e(implode(";\n\n", array_column($keysPlan['statements'], 'sql')) . ';') ?></pre>
            </details>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <a href="/admin/backups" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control border border-border text-ink text-label-lg font-label-lg hover:bg-surface transition-colors">
                    <?= icon('database') ?>
                    <?= e(t('admin.migrations.backup_first')) ?>
                </a>
                <button type="button" data-open-dialog="repair-keys-dialog"
                    class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= icon('key') ?>
                    <?= e(t('admin.migrations.keys.repair_button', ['count' => (string) $keysMissing])) ?>
                </button>
            </div>
            <dialog id="repair-keys-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-md backdrop:bg-ink/40">
                <form method="POST" action="/admin/migrations/repair-keys" class="flex flex-col gap-3 p-5 text-left">
                    <?= csrf_field() ?>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.migrations.keys.confirm_title')) ?></h2>
                    <p class="text-body-sm text-muted"><?= e(t('admin.migrations.keys.confirm_message')) ?></p>
                    <div class="flex justify-end gap-2 mt-1">
                        <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.migrations.cancel')) ?></button>
                        <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.migrations.keys.confirm_button')) ?></button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('admin.migrations.heading')) ?></h2>
        <p class="text-body-sm text-muted"><?= e(t('admin.migrations.help')) ?></p>

        <?php if ($pending === []): ?>
            <?= component('alert', ['type' => 'success', 'message' => t('admin.migrations.up_to_date')]) ?>
        <?php else: ?>
            <?= component('alert', ['type' => 'warning', 'message' => t('admin.migrations.pending_count', ['count' => (string) count($pending)])]) ?>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <a href="/admin/backups" class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control border border-border text-ink text-label-lg font-label-lg hover:bg-surface transition-colors">
                    <?= icon('database') ?>
                    <?= e(t('admin.migrations.backup_first')) ?>
                </a>
                <button type="button" data-open-dialog="apply-migrations-dialog"
                    class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= icon('rotate') ?>
                    <?= e(t('admin.migrations.apply_button', ['count' => (string) count($pending)])) ?>
                </button>
            </div>
            <dialog id="apply-migrations-dialog" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-md backdrop:bg-ink/40">
                <form method="POST" action="/admin/migrations" class="flex flex-col gap-3 p-5 text-left">
                    <?= csrf_field() ?>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.migrations.confirm_title')) ?></h2>
                    <p class="text-body-sm text-muted"><?= e(t('admin.migrations.confirm_message')) ?></p>
                    <ul class="text-body-sm text-ink list-disc pl-5">
                        <?php foreach ($pending as $row): ?>
                            <li class="break-all"><?= e($row['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="flex justify-end gap-2 mt-1">
                        <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.migrations.cancel')) ?></button>
                        <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('admin.migrations.confirm_button')) ?></button>
                    </div>
                </form>
            </dialog>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.migrations.column.file')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.migrations.column.status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($migrations) as $row): ?>
                    <tr class="border-b border-border last:border-0">
                        <td class="p-3 text-body-sm text-ink break-all"><?= e($row['name']) ?></td>
                        <td class="p-3 text-body-sm">
                            <?php if ($row['applied_at'] === null): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('admin.migrations.status.pending')) ?></span>
                            <?php else: ?>
                                <span class="text-muted"><?= e(t('admin.migrations.status.applied', ['date' => local_date((string) $row['applied_at'])])) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
