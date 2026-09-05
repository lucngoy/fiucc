<?php
/**
 * Header sticky — navigation de la section 5 du cahier des charges
 * (Home / Community / Events / News / Gallery / Elections / About + CTA).
 * "About" pointe vers /community (mission/vision/valeurs) : aucune route
 * /about dédiée n'existe, on ne doit pas en inventer une hors ticket.
 *
 * Switch de langue : branché sur GET /lang/{locale} (FIUCC-30), qui pose
 * $_SESSION['locale'] et redirige vers la page d'origine.
 */
$brandName ??= 'FIUCC';
$locale = \App\Core\Lang::locale();

$navLinks = [
    ['label' => t('nav.home'), 'href' => '/'],
    ['label' => t('nav.community'), 'href' => '/community'],
    ['label' => t('nav.events'), 'href' => '/events'],
    ['label' => t('nav.news'), 'href' => '/news'],
    ['label' => t('nav.gallery'), 'href' => '/gallery'],
    ['label' => t('nav.elections'), 'href' => '/elections'],
    ['label' => t('nav.about'), 'href' => '/community'],
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
                <a href="/lang/en" class="px-2 py-1 rounded <?= e($enClasses) ?>">EN</a>
                <a href="/lang/fr" class="px-2 py-1 rounded <?= e($frClasses) ?>">FR</a>
            </div>
            <?= component('button', ['label' => t('nav.join'), 'variant' => 'primary', 'href' => '/register']) ?>
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
            <a href="/lang/en" class="px-3 py-1.5 rounded-control border border-border <?= e($enClasses) ?>">EN</a>
            <a href="/lang/fr" class="px-3 py-1.5 rounded-control border border-border <?= e($frClasses) ?>">FR</a>
        </div>
        <?= component('button', ['label' => t('nav.join'), 'variant' => 'primary', 'href' => '/register']) ?>
    </nav>
</header>
