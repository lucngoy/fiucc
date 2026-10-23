<?php
/**
 * Import d'un comité dans un mandat (décision du bureau 2026-10-21) —
 * étape 1 : fichier ou liste collée. Rien n'est enregistré ici : l'étape
 * suivante est un aperçu à valider (import_preview.php).
 */
$mandate ??= [];
$list ??= '';
$error ??= null;

$mandateId = (int) $mandate['id'];
?>
<div class="flex flex-col gap-6">
    <a href="/admin/government?mandate=<?= e((string) $mandateId) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.government.mandate.heading', ['mandate' => $mandate['mandate']])) ?>
    </a>

    <?php if ($error !== null): ?>
        <?= component('alert', ['type' => 'error', 'message' => $error]) ?>
    <?php endif; ?>

    <div class="flex flex-col gap-1">
        <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.government.import.heading', ['mandate' => $mandate['mandate']])) ?></h1>
        <p class="text-body-md text-muted max-w-3xl"><?= e(t('admin.government.import.intro')) ?></p>
    </div>

    <form method="POST" action="/admin/government/mandates/<?= e((string) $mandateId) ?>/import/preview" enctype="multipart/form-data"
          class="bg-white rounded-card border border-border p-5 md:p-6 flex flex-col gap-6">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-1.5">
            <label for="import-file" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.import.field.file')) ?></label>
            <input type="file" id="import-file" name="file" accept=".xlsx,.csv,.txt,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                class="block w-full text-body-sm text-ink file:mr-3 file:h-10 file:px-4 file:rounded-control file:border-0 file:bg-surface file:text-primary file:font-label-md hover:file:bg-primary/15">
            <p class="text-body-sm text-muted"><?= e(t('admin.government.import.field.file_help')) ?></p>
        </div>

        <div class="flex items-center gap-3 text-label-caps font-label-caps uppercase text-muted">
            <span class="h-px flex-1 bg-border"></span><?= e(t('admin.government.import.or')) ?><span class="h-px flex-1 bg-border"></span>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="import-list" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.government.import.field.list')) ?></label>
            <textarea id="import-list" name="list" rows="10" placeholder="<?= e(t('admin.government.import.field.list_placeholder')) ?>"
                class="w-full rounded-control border border-border px-3 py-2 text-body-md text-ink bg-white focus:outline-none focus:ring-2 focus:border-primary focus:ring-primary-light/50"><?= e($list) ?></textarea>
            <p class="text-body-sm text-muted"><?= e(t('admin.government.import.field.list_help')) ?></p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t('admin.government.import.analyze')) ?>
                <?= icon('arrow-right') ?>
            </button>
        </div>
    </form>
</div>
