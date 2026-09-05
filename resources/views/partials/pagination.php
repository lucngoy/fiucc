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
?>
<nav class="flex items-center justify-center gap-1" aria-label="Pagination">
    <?php if ($currentPage > 1): ?>
        <a href="<?= e($pageUrl($currentPage - 1)) ?>"
           class="h-9 px-3 flex items-center justify-center rounded-control border border-border text-muted hover:text-ink hover:border-primary transition-colors">Previous</a>
    <?php endif; ?>

    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
        <?php if ($page === $currentPage): ?>
            <span class="h-9 w-9 flex items-center justify-center rounded-control bg-primary text-white font-label-lg" aria-current="page"><?= e((string) $page) ?></span>
        <?php else: ?>
            <a href="<?= e($pageUrl($page)) ?>"
               class="h-9 w-9 flex items-center justify-center rounded-control text-ink hover:bg-primary-light/20 transition-colors"><?= e((string) $page) ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= e($pageUrl($currentPage + 1)) ?>"
           class="h-9 px-3 flex items-center justify-center rounded-control border border-border text-muted hover:text-ink hover:border-primary transition-colors">Next</a>
    <?php endif; ?>
</nav>
