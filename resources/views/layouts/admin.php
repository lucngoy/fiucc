<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FIUCC Admin') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-surface">
<?php
/**
 * Coquille du back office (FIUCC-43) — maquette fiucc_portal_admin_dashboard/
 * code.html pour le style (sidebar bleu marine, cartes blanches arrondies).
 * Barre latérale volontairement minimale : seul "Membres" mène à une page
 * réelle aujourd'hui. Pas de lien mort vers Dashboard/Events/News/... —
 * ces sections n'ont ni contrôleur ni ticket construits (une phase à la
 * fois, CLAUDE.md) ; elles rejoindront la navigation quand leur ticket
 * existera, plutôt que de simuler un back office plus complet qu'il ne
 * l'est.
 */
$activeNav ??= null;
?>
<div class="flex flex-col md:flex-row min-h-screen">
    <!-- Barre du haut sur mobile (< md), colonne latérale à partir de md —
         un seul jeu de classes responsive plutôt que deux marquages
         séparés, pour ne pas désynchroniser mobile et desktop plus tard. -->
    <aside class="w-full md:w-64 md:shrink-0 bg-primary text-white flex flex-col md:min-h-screen">
        <div class="flex items-center justify-between gap-3 px-4 md:px-5 h-16 border-b border-white/10 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <span class="h-9 w-9 rounded-full bg-white/15 flex items-center justify-center font-headline-sm text-headline-sm shrink-0" aria-hidden="true">F</span>
                <div class="leading-tight min-w-0">
                    <p class="font-label-lg text-label-lg truncate"><?= e(t('admin.brand.name')) ?></p>
                    <p class="hidden sm:block text-label-caps font-label-caps uppercase text-white/60"><?= e(t('admin.brand.tagline')) ?></p>
                </div>
            </div>
            <form method="POST" action="/logout" class="md:hidden shrink-0">
                <?= csrf_field() ?>
                <button type="submit" aria-label="<?= e(t('admin.nav.logout')) ?>"
                    class="h-9 w-9 flex items-center justify-center rounded-control text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </button>
            </form>
        </div>
        <nav class="flex-1 py-2 md:py-4 px-3 flex flex-row md:flex-col gap-1 overflow-x-auto">
            <a href="/admin/members"
               class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'members' ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>"
               <?= $activeNav === 'members' ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-users w-5 text-center" aria-hidden="true"></i>
                <?= e(t('admin.nav.members')) ?>
            </a>
        </nav>
        <div class="hidden md:block p-4 border-t border-white/10 shrink-0">
            <p class="text-body-sm text-white/70 mb-3 truncate"><?= e($adminEmail ?? '') ?></p>
            <form method="POST" action="/logout">
                <?= csrf_field() ?>
                <button type="submit" class="w-full flex items-center gap-2 h-9 px-3 rounded-control text-label-md font-label-md text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                    <?= e(t('admin.nav.logout')) ?>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-14 md:h-16 bg-white border-b border-border flex items-center px-4 md:px-6 shrink-0">
            <h1 class="font-headline-sm text-headline-sm text-ink"><?= e($title ?? '') ?></h1>
        </header>
        <main class="flex-1 p-4 md:p-8 overflow-x-auto">
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
