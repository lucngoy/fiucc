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
        . icon('lock', 'text-xs mr-1.5') . e($label) . '</span>';
};

$paginationQuery = array_filter(['search' => $filters['search'], 'category' => $filters['category']], static fn ($v) => $v !== '');
?>
<div class="relative w-full overflow-hidden bg-primary">
    <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <style>
            /* simple SVG animations: floating lines and pulsing dots */
            @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            @keyframes pulse { 0% { r: 2; opacity: 0.9 } 50% { r: 4; opacity: 0.5 } 100% { r: 2; opacity: 0.9 } }
            .float { transform-origin: 400px 450px; animation: floatY 6s ease-in-out infinite; }
            .pulse circle { animation: pulse 2.5s ease-in-out infinite; }
        </style>
        <g class="float" stroke="#3B82F6" stroke-width="1">
            <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
            <path d="M340 20 L420 220 L520 140" />
            <path d="M420 220 L460 320 L280 360 L340 20" />
            <path d="M280 360 L120 60" />
            <path d="M460 320 L620 380 L520 500 L280 360" />
            <path d="M520 500 L560 560 L400 620 L280 360" />
            <path d="M560 560 L700 620 L620 760 L400 620" />
            <path d="M620 760 L640 800 L480 860 L400 620" />
        </g>
        <g class="pulse" fill="#60A5FA">
            <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
            <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
            <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
            <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
            <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
            <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
            <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
        </g>
    </svg>
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
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
                        <?= icon($meta['icon']) ?>
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
                    <!-- « Ouvrir » : PDF seulement, seul format que le navigateur
                         affiche (DocumentController::open()). Les autres formats
                         restent en téléchargement, signalé pour ne pas surprendre. -->
                    <div class="flex flex-wrap items-center gap-2 shrink-0 self-start sm:self-center">
                        <?php if (\App\Controllers\DocumentController::isViewable((string) $document['mime_type'])): ?>
                            <a href="/documents/<?= e((string) $document['id']) ?>/open" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-white border border-border text-ink text-label-lg font-label-lg hover:border-primary hover:text-primary transition-colors">
                                <?= icon('arrow-up-right-from-square') ?>
                                <?= e(t('documents.open')) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-body-sm text-muted"><?= e(t('documents.download_only')) ?></span>
                        <?php endif; ?>
                        <a href="/documents/<?= e((string) $document['id']) ?>/download"
                           class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                            <?= icon('download') ?>
                            <?= e(t('documents.download')) ?>
                        </a>
                    </div>
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
