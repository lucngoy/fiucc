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
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null;
$accountHref = ($role === 'admin') ? '/admin' : '/profile';
$accountDisplayName = 'User';

if ($isLoggedIn && $role === 'admin') {
    $accountDisplayName = 'Admin';
} elseif ($isLoggedIn && $userId !== null) {
    $member = \App\Models\Member::findByUserId((int) $userId);
    $accountDisplayName = trim((string) ($member['full_name'] ?? '')) !== '' ? (string) $member['full_name'] : 'User';
}
$accountLabel = $accountDisplayName;
$profileMenuLabel = t('profile.title');

$navLinks = [
    ['label' => t('nav.home'), 'href' => '/'],
    ['label' => t('nav.committee'), 'href' => '/community/government'],
    ['label' => t('nav.events'), 'href' => '/events'],
    ['label' => t('nav.news'), 'href' => '/news'],
    ['label' => t('nav.gallery'), 'href' => '/gallery'],
    ['label' => t('nav.elections'), 'href' => '/elections'],
    ['label' => t('nav.about'), 'href' => '/community'],
];

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($currentPath === '' || $currentPath === false) {
    $currentPath = '/';
}
// Normalize common index filenames that some hosts (or rewrites) expose.
$currentPath = preg_replace('#/+(index\.(php|html?))$#i', '/', $currentPath);
// Trim trailing slashes except keep root '/'.
$currentPath = rtrim($currentPath, '/');
if ($currentPath === '') {
    $currentPath = '/';
}

$navLinkIsActive = function (string $href) use ($currentPath): bool {
    $normHref = $href === '/' ? '/' : rtrim($href, '/');
    return $currentPath === $normHref || ($normHref !== '/' && $currentPath !== '/community/government' && str_starts_with($currentPath, $normHref . '/'));
};

$enClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
?>
<header class="sticky top-0 z-50 w-full bg-white border-b border-border shadow-box">
    <div class="max-w-layout mx-auto h-[72px] px-4 md:px-6 flex items-center justify-between gap-4">
        <a href="/" class="flex items-center gap-3 shrink-0">
            <img src="/assets/images/fiucc-seal.png" alt="FIUCC official seal" class="h-11 w-11 object-contain">
            <span class="font-headline-sm text-headline-sm text-ink"><?= e($brandName) ?></span>
        </a>

        <nav class="hidden lg:flex items-center gap-6" aria-label="Main navigation">
            <?php foreach ($navLinks as $link): ?>
                <?php $isActive = $navLinkIsActive($link['href']); ?>
                <a href="<?= e($link['href']) ?>" class="<?= $isActive ? 'font-label-lg text-label-lg text-primary hover:text-primary transition-colors' : 'font-label-lg text-label-lg text-ink hover:text-primary transition-colors' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden lg:flex items-center gap-4 shrink-0">
            <div class="flex items-center bg-surface p-0.5 rounded-control border border-border text-label-md font-label-md">
                <a href="/lang/en" class="px-2 py-1 rounded <?= e($enClasses) ?>">EN</a>
                <a href="/lang/fr" class="px-2 py-1 rounded <?= e($frClasses) ?>">FR</a>
            </div>
            <?php if ($isLoggedIn): ?>
                <details class="relative" data-header-dropdown>
                    <summary class="list-none cursor-pointer inline-flex items-center justify-center gap-2 h-11 px-4 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors">
                        <span><?= e($accountLabel) ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-52 rounded-control border border-border bg-white shadow-card overflow-hidden z-50">
                        <a href="<?= e($accountHref) ?>" class="block px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                            <?= e($profileMenuLabel) ?>
                        </a>
                        <form method="POST" action="/logout" class="border-t border-border">
                            <?= csrf_field() ?>
                            <button type="submit" style="text-wrap: nowrap;" class="w-full text-left px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                                <?= e(t('admin.nav.logout')) ?>
                            </button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <details class="relative" data-header-dropdown>
                    <summary class="list-none cursor-pointer inline-flex items-center justify-center h-11 px-4 rounded-control border bg-primary border-border text-ink text-label-lg font-label-lg font-semibold text-white hover:bg-surface hover:text-ink transition-colors">
                        <?= e(t('auth.sign_in')) ?>
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-56 rounded-control border border-border bg-white shadow-card overflow-hidden z-50">
                        <a href="/login" class="block px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                            <?= e(t('auth.sign_in')) ?>
                        </a>
                        <a href="/register" class="block border-t border-border px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                            <?= e(t('nav.join')) ?>
                        </a>
                        <!-- Onboarding (FIUCC-89) : visiteurs sans compte uniquement —
                             le public de cette page, avant de s'inscrire. -->
                        <a href="/new-at-final" class="block border-t border-border px-4 py-3 text-label-lg font-label-lg text-primary hover:bg-surface transition-colors">
                            <?= e(t('footer.new_at_final')) ?>
                        </a>
                    </div>
                </details>
            <?php endif; ?>
        </div>

        <button type="button" id="nav-toggle" class="lg:hidden inline-flex items-center justify-center h-10 w-10 rounded-control border border-border text-ink" aria-expanded="false" aria-controls="mobile-nav">
            <span class="sr-only">Toggle navigation</span>
            <span aria-hidden="true">&#9776;</span>
        </button>
    </div>

    <nav id="mobile-nav" class="hidden lg:hidden flex-col gap-4 border-t border-border bg-white px-4 py-4" aria-label="Mobile navigation">
        <?php foreach ($navLinks as $link): ?>
            <?php $isActive = $navLinkIsActive($link['href']); ?>
            <a href="<?= e($link['href']) ?>" class="<?= $isActive ? 'font-label-lg text-label-lg text-primary hover:text-primary transition-colors' : 'font-label-lg text-label-lg text-ink hover:text-primary transition-colors' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        <?php endforeach; ?>
        <div class="flex items-center gap-2 pt-3 border-t border-border">
            <a href="/lang/en" class="px-3 py-1.5 rounded-control border border-border <?= e($enClasses) ?>">EN</a>
            <a href="/lang/fr" class="px-3 py-1.5 rounded-control border border-border <?= e($frClasses) ?>">FR</a>
        </div>
        <?php if ($isLoggedIn): ?>
            <details class="group" data-header-dropdown>
                <summary class="list-none cursor-pointer inline-flex items-center justify-center gap-2 h-11 w-full rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors">
                    <span><?= e($accountLabel) ?></span>
                    <span aria-hidden="true" class="text-xs">▾</span>
                </summary>
                <div class="mt-2 flex flex-col rounded-control border border-border bg-white overflow-hidden">
                    <a href="<?= e($accountHref) ?>" class="px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                        <?= e($profileMenuLabel) ?>
                    </a>
                    <form method="POST" action="/logout" class="border-t border-border">
                        <?= csrf_field() ?>
                        <button type="submit" class="w-full text-left px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                            <?= e(t('admin.nav.logout')) ?>
                        </button>
                    </form>
                </div>
            </details>
        <?php else: ?>
            <details class="group" data-header-dropdown>
                <summary class="list-none cursor-pointer inline-flex items-center justify-center h-11 w-full rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors">
                    <?= e(t('auth.sign_in')) ?>
                </summary>
                <div class="mt-2 flex flex-col rounded-control border border-border bg-white overflow-hidden">
                    <a href="/login" class="px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                        <?= e(t('auth.sign_in')) ?>
                    </a>
                    <a href="/register" class="border-t border-border px-4 py-3 text-label-lg font-label-lg text-ink hover:bg-surface transition-colors">
                        <?= e(t('nav.join')) ?>
                    </a>
                    <a href="/new-at-final" class="border-t border-border px-4 py-3 text-label-lg font-label-lg text-primary hover:bg-surface transition-colors">
                        <?= e(t('footer.new_at_final')) ?>
                    </a>
                </div>
            </details>
        <?php endif; ?>
    </nav>
</header>
