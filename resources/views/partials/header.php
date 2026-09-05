<?php
/**
 * Header sticky — navigation de la section 5 du cahier des charges
 * (Home / Community / Events / News / Gallery / Elections / About + CTA).
 * "About" pointe vers /community (mission/vision/valeurs) : aucune route
 * /about dédiée n'existe, on ne doit pas en inventer une hors ticket.
 *
 * Switch de langue : purement visuel pour l'instant, aucun système i18n
 * n'est encore branché (cf. CLAUDE.md §38, phase Polish) — FR ne change
 * rien tant que ce système n'existe pas.
 */
$brandName ??= 'FIUCC';
$locale ??= \App\Core\Config::get('app.locale', 'en');

$navLinks = [
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Community', 'href' => '/community'],
    ['label' => 'Events', 'href' => '/events'],
    ['label' => 'News', 'href' => '/news'],
    ['label' => 'Gallery', 'href' => '/gallery'],
    ['label' => 'Elections', 'href' => '/elections'],
    ['label' => 'About', 'href' => '/community'],
];

$enClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
?>
<header class="sticky top-0 z-50 w-full bg-white border-b border-border shadow-nav">
    <div class="max-w-layout mx-auto h-[72px] px-4 md:px-6 flex items-center justify-between gap-4">
        <a href="/" class="flex items-center gap-3 shrink-0">
            <img src="/assets/images/fiucc-seal.png" alt="FIUCC official seal" class="h-11 w-11 object-contain">
            <span class="font-headline-sm text-headline-sm text-ink"><?= e($brandName) ?></span>
        </a>

        <nav class="hidden lg:flex items-center gap-6" aria-label="Main navigation">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?= e($link['href']) ?>" class="font-label-lg text-label-lg text-muted hover:text-primary transition-colors"><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden lg:flex items-center gap-4 shrink-0">
            <div class="flex items-center bg-surface p-0.5 rounded-control border border-border text-label-md font-label-md">
                <a href="#" class="px-2 py-1 rounded <?= e($enClasses) ?>">EN</a>
                <a href="#" class="px-2 py-1 rounded <?= e($frClasses) ?>">FR</a>
            </div>
            <?= component('button', ['label' => 'Join Community', 'variant' => 'primary', 'href' => '/register']) ?>
        </div>

        <button type="button" id="nav-toggle" class="lg:hidden inline-flex items-center justify-center h-10 w-10 rounded-control border border-border text-ink" aria-expanded="false" aria-controls="mobile-nav">
            <span class="sr-only">Toggle navigation</span>
            <span aria-hidden="true">&#9776;</span>
        </button>
    </div>

    <nav id="mobile-nav" class="hidden lg:hidden flex-col gap-4 border-t border-border bg-white px-4 py-4" aria-label="Mobile navigation">
        <?php foreach ($navLinks as $link): ?>
            <a href="<?= e($link['href']) ?>" class="font-label-lg text-label-lg text-ink"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
        <div class="flex items-center gap-2 pt-3 border-t border-border">
            <a href="#" class="px-3 py-1.5 rounded-control border border-border <?= e($enClasses) ?>">EN</a>
            <a href="#" class="px-3 py-1.5 rounded-control border border-border <?= e($frClasses) ?>">FR</a>
        </div>
        <?= component('button', ['label' => 'Join Community', 'variant' => 'primary', 'href' => '/register']) ?>
    </nav>
</header>
