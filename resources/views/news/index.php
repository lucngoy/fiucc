<?php
/**
 * Liste des actualités (FIUCC-55, section 29 du cahier des charges) —
 * maquette fiucc_portal_news_announcements/code.html pour l'esprit
 * général (article vedette + grille), sur les tokens du projet. Éléments
 * fabriqués de la maquette absents ici : newsletter ("Subscribe to
 * Dispatches"), classement de popularité ("Trending Dispatches", aucun
 * compteur de vues dans le schéma) — même principe que events/index.php
 * (FIUCC-48), rien qui n'ait de donnée réelle derrière.
 *
 * Vedette ($featured) : calculée par NewsController::index(), pas ici —
 * même séparation controller/vue qu'events/index.php. N'apparaît que sur
 * la vue par défaut (page 1, sans recherche ni filtre).
 */
$articles ??= [];
$featured ??= null;
$filters ??= ['search' => '', 'category' => ''];
$categories ??= [];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

$categoryBadge = static function (string $category): string {
    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full bg-primary/10 text-primary text-label-md font-label-md uppercase tracking-wide">'
        . e(t('news.category.' . $category)) . '</span>';
};

$imagePlaceholder = static function (string $iconSize = 'text-3xl'): string {
    return '<div class="absolute inset-0 bg-gradient-to-br from-primary/15 to-primary/5 flex items-center justify-center">'
        . icon('newspaper', $iconSize . ' text-primary/30')
        . '</div>';
};

$paginationQuery = array_filter(['search' => $filters['search'], 'category' => $filters['category']], static fn ($v) => $v !== '');
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('news.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('news.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('news.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <form method="GET" action="/news" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-10">
        <label for="news-search" class="sr-only"><?= e(t('news.search_label')) ?></label>
        <input type="search" id="news-search" name="search" value="<?= e($filters['search']) ?>"
            placeholder="<?= e(t('news.search_placeholder')) ?>"
            class="h-10 min-w-[240px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        <?php if ($categories !== []): ?>
            <label for="news-category" class="sr-only"><?= e(t('news.filter.category_all')) ?></label>
            <select id="news-category" name="category" class="h-10 rounded-control border border-border px-3 text-body-sm text-ink bg-white">
                <option value=""><?= e(t('news.filter.category_all')) ?></option>
                <?php foreach ($categories as $categoryOption): ?>
                    <option value="<?= e($categoryOption) ?>" <?= $filters['category'] === $categoryOption ? 'selected' : '' ?>>
                        <?= e(t('news.category.' . $categoryOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('news.filter.apply')) ?></button>
        <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
            <a href="/news" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('news.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($featured === null && $articles === []): ?>
        <div class="max-w-xl mx-auto">
            <?php if ($filters['search'] !== '' || $filters['category'] !== ''): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('news.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t('news.empty.default')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($featured !== null): ?>
            <a href="<?= e('/news/' . $featured['slug']) ?>" class="group block bg-surface rounded-card border border-border overflow-hidden mb-10 grid grid-cols-1 lg:grid-cols-12 hover:shadow-card transition-shadow">
                <div class="relative min-h-[220px] lg:min-h-[320px] lg:col-span-5 overflow-hidden bg-white">
                    <?php if (!empty($featured['cover_image'])): ?>
                        <img src="<?= e('/uploads/' . $featured['cover_image']) ?>" alt=""
                             class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <?= $imagePlaceholder('text-6xl') ?>
                    <?php endif; ?>
                </div>
                <div class="lg:col-span-7 p-6 md:p-10 flex flex-col justify-center gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <?= $categoryBadge($featured['category']) ?>
                        <span class="text-body-sm text-muted"><?= e(local_date($featured['published_at'])) ?></span>
                    </div>
                    <h2 class="font-headline-lg text-headline-lg text-ink group-hover:text-primary transition-colors"><?= e($featured['title']) ?></h2>
                    <?php if (!empty($featured['excerpt'])): ?>
                        <p class="text-body-md text-muted line-clamp-3"><?= e($featured['excerpt']) ?></p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endif; ?>

        <?php if ($articles !== []): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($articles as $article): ?>
                    <a href="<?= e('/news/' . $article['slug']) ?>" class="group flex flex-col bg-white rounded-card border border-border hover:shadow-card transition-shadow overflow-hidden">
                        <div class="relative h-44 w-full overflow-hidden bg-surface">
                            <?php if (!empty($article['cover_image'])): ?>
                                <img src="<?= e('/uploads/' . $article['cover_image']) ?>" alt=""
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <?= $imagePlaceholder() ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 flex flex-col flex-grow gap-2">
                            <div><?= $categoryBadge($article['category']) ?></div>
                            <h3 class="font-headline-sm text-headline-sm text-ink group-hover:text-primary transition-colors line-clamp-2"><?= e($article['title']) ?></h3>
                            <?php if (!empty($article['excerpt'])): ?>
                                <p class="text-body-sm text-muted line-clamp-2 flex-grow"><?= e($article['excerpt']) ?></p>
                            <?php endif; ?>
                            <p class="text-label-caps font-label-caps text-muted uppercase mt-auto pt-2"><?= e(local_date($article['published_at'])) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => '/news?' . http_build_query($paginationQuery) . '&page={page}',
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
