<?php
/**
 * Annuaire des membres (FIUCC-82, section Annuaire des membres du cahier
 * des charges). Chaque ligne vient déjà rédigée de Member::directoryList()
 * (is_public = 1 filtre l'inclusion elle-même) — cette vue n'a aucun
 * contrôle de confidentialité à faire, seulement à afficher ce qu'elle
 * reçoit, même discipline que candidates.php pour l'élection (FIUCC-63).
 *
 * Carte simplifiée : prénom, nom, faculté, année, photo, réseaux sociaux —
 * exactement les champs du cahier, rien de plus (pas d'email/téléphone
 * ici, voir Member::directoryList()).
 */
$members ??= [];
$filters ??= ['search' => '', 'faculty' => '', 'city' => '', 'arrival_year' => ''];
$options ??= ['faculties' => [], 'cities' => [], 'arrival_years' => []];
$studyLevels ??= [];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

// En dessous de ce seuil, la grille a l'air cassée plutôt que jeune — un
// mot d'explication au-dessus plutôt qu'une page qui semble juste vide de
// contenu (les membres choisissent d'y figurer, voir /profile/privacy).
$sparseThreshold = 5;

$activeFilterQuery = array_filter([
    'search'       => $filters['search'],
    'faculty'      => $filters['faculty'],
    'city'         => $filters['city'],
    'arrival_year' => $filters['arrival_year'],
], static fn ($value) => $value !== '');

$renderInstagram = static function (?string $instagram): string {
    if (empty($instagram)) {
        return '';
    }

    $handle = ltrim($instagram, '@');

    return '<a href="' . e('https://www.instagram.com/' . $handle . '/') . '" target="_blank" rel="noopener noreferrer" '
        . 'title="Instagram" aria-label="Instagram — @' . e($handle) . '" '
        . 'class="inline-flex h-7 w-7 rounded-full bg-primary/[4%] text-primary items-center justify-center hover:bg-primary/25 transition-colors">'
        . icon('instagram') . '</a>';
};

$paginationBase = '/directory?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';
?>
<div class="relative w-full overflow-hidden bg-primary">
    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('directory.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('directory.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('directory.subtitle')) ?></p>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <form method="GET" action="/directory" class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 mb-6">
        <label for="directory-search" class="sr-only"><?= e(t('directory.search_label')) ?></label>
        <input type="search" id="directory-search" name="search" value="<?= e($filters['search']) ?>"
            placeholder="<?= e(t('directory.search_placeholder')) ?>"
            class="h-10 min-w-[220px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">

        <label for="directory-faculty" class="sr-only"><?= e(t('directory.filter.faculty_all')) ?></label>
        <select id="directory-faculty" name="faculty" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
            <option value=""><?= e(t('directory.filter.faculty_all')) ?></option>
            <?php foreach ($options['faculties'] as $facultyOption): ?>
                <option value="<?= e($facultyOption) ?>" <?= $filters['faculty'] === $facultyOption ? 'selected' : '' ?>><?= e($facultyOption) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="directory-city" class="sr-only"><?= e(t('directory.filter.city_all')) ?></label>
        <select id="directory-city" name="city" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
            <option value=""><?= e(t('directory.filter.city_all')) ?></option>
            <?php foreach ($options['cities'] as $cityOption): ?>
                <option value="<?= e($cityOption) ?>" <?= $filters['city'] === $cityOption ? 'selected' : '' ?>><?= e($cityOption) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="directory-arrival-year" class="sr-only"><?= e(t('directory.filter.arrival_year_all')) ?></label>
        <select id="directory-arrival-year" name="arrival_year" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
            <option value=""><?= e(t('directory.filter.arrival_year_all')) ?></option>
            <?php foreach ($options['arrival_years'] as $yearOption): ?>
                <option value="<?= e((string) $yearOption) ?>" <?= $filters['arrival_year'] === (string) $yearOption ? 'selected' : '' ?>><?= e((string) $yearOption) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('directory.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/directory" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('directory.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <p class="text-body-md text-muted mb-6"><?= e(t('directory.total', ['count' => $total])) ?></p>

    <?php if ($members === []): ?>
        <div class="max-w-xl mx-auto">
            <?php if ($activeFilterQuery !== []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('directory.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t('directory.empty.default')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($total < $sparseThreshold): ?>
            <div class="mb-6">
                <?= component('alert', ['type' => 'info', 'message' => t('directory.sparse_notice')]) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($members as $member): ?>
                <div class="bg-white rounded-card border border-border overflow-hidden">
                    <?php if (!empty($member['photo'])): ?>
                        <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="w-full aspect-square object-cover">
                    <?php else: ?>
                        <span class="w-full aspect-square bg-primary/[4%] text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                            <?= e(initials($member['full_name'])) ?>
                        </span>
                    <?php endif; ?>
                    <div class="p-3">
                        <h3 class="font-label-lg text-label-lg text-ink mb-0.5 truncate">
                            <a href="<?= e('/directory/' . $member['id']) ?>" class="hover:text-primary hover:underline"><?= e($member['full_name']) ?></a>
                        </h3>
                        <?php if (!empty($member['faculty'])): ?>
                            <p class="text-label-caps font-label-caps text-muted mb-0.5 truncate"><?= e($member['faculty']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($member['year']) && isset($studyLevels[(int) $member['year']])): ?>
                            <p class="text-body-sm text-muted mb-2"><?= e($studyLevels[(int) $member['year']]) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($member['instagram']) || !empty($member['whatsapp'])): ?>
                            <div class="flex items-center gap-2 mt-1">
                                <?= $renderInstagram($member['instagram'] ?? null) ?>
                                <?php if (!empty($member['whatsapp'])): ?>
                                    <span class="text-body-sm text-muted truncate" title="<?= e(t('directory.card.whatsapp')) ?>"><?= e($member['whatsapp']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => $paginationBase,
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
