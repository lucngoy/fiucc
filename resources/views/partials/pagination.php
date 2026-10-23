<?php
/**
 * @param int $currentPage
 * @param int $totalPages
 * @param string $baseUrl  URL contenant le jeton {page}, ex. '/news?page={page}'
 */
$currentPage = (int) $currentPage;
$totalPages = (int) $totalPages;

if ($totalPages <= 1) {
    return;
}

$pageUrl = static fn (int $page): string => str_replace('{page}', (string) $page, $baseUrl);

// Première, dernière, et la page courante entourée de ses voisines ; un
// trou de plus d'une page devient « … ». Sans ça, chaque page a son
// bouton et la barre déborde à 390px dès une dizaine de pages (annuaire,
// membres admin, jetons de vote d'une communauté qui grandit).
$pages = [];
foreach (range(1, $totalPages) as $page) {
    if ($page === 1 || $page === $totalPages || abs($page - $currentPage) <= 1) {
        if ($pages !== [] && $page - end($pages) === 2) {
            $pages[] = $page - 1;
        } elseif ($pages !== [] && $page - end($pages) > 2) {
            $pages[] = null;
        }
        $pages[] = $page;
    }
}
?>
<nav class="flex items-center justify-center gap-1" aria-label="Pagination">
    <?php if ($currentPage > 1): ?>
        <a href="<?= e($pageUrl($currentPage - 1)) ?>"
           class="h-9 px-3 flex items-center justify-center rounded-control border border-border text-muted hover:text-ink hover:border-primary transition-colors"><?= e(t('pagination.previous')) ?></a>
    <?php endif; ?>

    <?php foreach ($pages as $page): ?>
        <?php if ($page === null): ?>
            <span class="h-9 w-6 flex items-center justify-center text-muted" aria-hidden="true">…</span>
        <?php elseif ($page === $currentPage): ?>
            <span class="h-9 w-9 flex items-center justify-center rounded-control bg-primary text-white font-label-lg" aria-current="page"><?= e((string) $page) ?></span>
        <?php else: ?>
            <a href="<?= e($pageUrl($page)) ?>"
               class="h-9 w-9 flex items-center justify-center rounded-control text-ink hover:bg-primary-light/20 transition-colors"><?= e((string) $page) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= e($pageUrl($currentPage + 1)) ?>"
           class="h-9 px-3 flex items-center justify-center rounded-control border border-border text-muted hover:text-ink hover:border-primary transition-colors"><?= e(t('pagination.next')) ?></a>
    <?php endif; ?>
</nav>
