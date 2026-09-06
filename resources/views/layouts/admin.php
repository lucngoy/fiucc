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
<div class="flex min-h-screen">
    <aside class="w-64 shrink-0 bg-primary text-white flex flex-col">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10 shrink-0">
            <span class="h-9 w-9 rounded-full bg-white/15 flex items-center justify-center font-headline-sm text-headline-sm" aria-hidden="true">F</span>
            <div class="leading-tight">
                <p class="font-label-lg text-label-lg"><?= e(t('admin.brand.name')) ?></p>
                <p class="text-label-caps font-label-caps uppercase text-white/60"><?= e(t('admin.brand.tagline')) ?></p>
            </div>
        </div>
        <nav class="flex-1 py-4 px-3 flex flex-col gap-1">
            <a href="/admin/members"
               class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg transition-colors <?= $activeNav === 'members' ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>"
               <?= $activeNav === 'members' ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid fa-users w-5 text-center" aria-hidden="true"></i>
                <?= e(t('admin.nav.members')) ?>
            </a>
        </nav>
        <div class="p-4 border-t border-white/10 shrink-0">
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
        <header class="h-16 bg-white border-b border-border flex items-center px-6 shrink-0">
            <h1 class="font-headline-sm text-headline-sm text-ink"><?= e($title ?? '') ?></h1>
        </header>
        <main class="flex-1 p-6 md:p-8 overflow-x-auto">
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
