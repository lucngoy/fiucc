<?php
/**
 * Espace documents (FIUCC-57, section 30 du cahier des charges) —
 * règlements, formulaires, rapports, archives. Pas de maquette Stitch
 * dédiée pour cet écran ; mise en page simple cohérente avec les autres
 * listes du site (events/news), une carte par document plutôt qu'une
 * grille visuelle (pas d'image de couverture pour un PDF/docx).
 *
 * $documents ne contient que ce que le visiteur a le droit de voir —
 * DocumentController::index() filtre déjà par visibility selon son niveau
 * d'accès (Document::publicList()). Le lien "Télécharger" pointe vers
 * /documents/{id}/download, qui revérifie les droits indépendamment de
 * cette liste : la protection réelle est là-bas, pas ici.
 */
$documents ??= [];
$filters ??= ['search' => '', 'category' => ''];
$categories ??= [];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

$fileMeta = static function (string $mimeType): array {
    return match ($mimeType) {
        'application/pdf' => ['icon' => 'fa-file-pdf', 'label' => 'PDF'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['icon' => 'fa-file-word', 'label' => 'Word'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['icon' => 'fa-file-excel', 'label' => 'Excel'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['icon' => 'fa-file-powerpoint', 'label' => 'PowerPoint'],
        'application/vnd.oasis.opendocument.text' => ['icon' => 'fa-file-lines', 'label' => 'OpenDocument'],
        default => ['icon' => 'fa-file', 'label' => t('documents.file_type.other')],
    };
};

$humanSize = static function (?int $bytes): string {
    if ($bytes === null || $bytes <= 0) {
        return '';
    }

    $units = ['o', 'Ko', 'Mo', 'Go'];
    $value = (float) $bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return ($unitIndex === 0 ? (string) (int) $value : number_format($value, 1)) . ' ' . $units[$unitIndex];
};

$visibilityBadge = static function (string $visibility): string {
    if ($visibility === 'public') {
        return '';
    }

    $label = match ($visibility) {
        'admin'      => t('documents.visibility.admin'),
        'government' => t('documents.visibility.government'),
        default      => t('documents.visibility.members'),
    };

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md">'
        . '<i class="fa-solid fa-lock text-xs mr-1.5" aria-hidden="true"></i>' . e($label) . '</span>';
};

$paginationQuery = array_filter(['search' => $filters['search'], 'category' => $filters['category']], static fn ($v) => $v !== '');
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('documents.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('documents.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('documents.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <form method="GET" action="/documents" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-10">
        <label for="doc-search" class="sr-only"><?= e(t('documents.search_label')) ?></label>
        <input type="search" id="doc-search" name="search" value="<?= e($filters['search']) ?>"
            placeholder="<?= e(t('documents.search_placeholder')) ?>"
            class="h-10 min-w-[240px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        <?php if ($categories !== []): ?>
            <label for="doc-category" class="sr-only"><?= e(t('documents.filter.category_all')) ?></label>
            <select id="doc-category" name="category" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('documents.filter.category_all')) ?></option>
                <?php foreach ($categories as $categoryOption): ?>
                    <option value="<?= e($categoryOption) ?>" <?= $filters['category'] === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('documents.filter.apply')) ?></button>
        <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
            <a href="/documents" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('documents.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($documents === []): ?>
        <div class="max-w-xl mx-auto">
            <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('documents.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t('documents.empty.default')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="text-body-md text-muted mb-6"><?= e(t('documents.total', ['count' => $total])) ?></p>

        <div class="flex flex-col gap-3">
            <?php foreach ($documents as $document): ?>
                <?php $meta = $fileMeta((string) $document['mime_type']); ?>
                <div class="flex flex-col sm:flex-row sm:items-center gap-4 bg-white rounded-card border border-border p-5 hover:shadow-card transition-shadow">
                    <span class="h-12 w-12 rounded-control bg-primary/10 text-primary flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid <?= e($meta['icon']) ?>" aria-hidden="true"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($document['title']) ?></h2>
                            <?= $visibilityBadge($document['visibility']) ?>
                        </div>
                        <?php if (!empty($document['description'])): ?>
                            <p class="text-body-sm text-muted line-clamp-2 mb-1"><?= e($document['description']) ?></p>
                        <?php endif; ?>
                        <p class="text-label-caps font-label-caps text-muted uppercase">
                            <?= e($meta['label']) ?>
                            <?php if ($document['file_size'] !== null): ?>
                                &middot; <?= e($humanSize((int) $document['file_size'])) ?>
                            <?php endif; ?>
                            <?php if (!empty($document['category'])): ?>
                                &middot; <?= e($document['category']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="/documents/<?= e((string) $document['id']) ?>/download"
                       class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        <?= e(t('documents.download')) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => '/documents?' . http_build_query($paginationQuery) . '&page={page}',
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
