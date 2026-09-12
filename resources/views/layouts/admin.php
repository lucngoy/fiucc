<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Jamais indexé (FIUCC-75) : back-office, aucune page ici n'a de
         contenu destiné à une recherche publique — robots.txt bloque déjà
         /admin/, cette balise le redit au niveau de chaque page (défense en
         profondeur, même principe que le reste du projet). -->
    <meta name="robots" content="noindex, nofollow">
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
 * Barre latérale volontairement minimale : seuls "Membres", "Événements",
 * "Galerie", "Actualités", "Documents" et "Élections" mènent à une page
 * réelle aujourd'hui. Pas de lien mort vers Dashboard/... — ces sections
 * n'ont ni contrôleur ni ticket construits (une phase à la fois,
 * CLAUDE.md) ; elles rejoindront la navigation quand leur ticket
 * existera, plutôt que de simuler un back office plus complet qu'il ne
 * l'est. "Élections" (FIUCC-59/60) : création/configuration seulement pour
 * l'instant — candidatures/vote/résultats arrivent avec les tickets
 * suivants (FIUCC-61+), pas de bouton pour des actions qui n'existent pas
 * encore.
 *
 * Sidebar fixe au scroll (md+) : logo officiel de la communauté (même
 * asset que le header public, /assets/images/fiucc-seal.png) + nav + pied
 * de compte (email, déconnexion) — dupliqué avec le menu profil de la
 * barre du haut par choix : les deux restent accessibles, l'un visible en
 * permanence en bas de la colonne latérale (desktop), l'autre présent
 * même sur mobile où la sidebar passe en rangée horizontale. Pas de nom/
 * rôle inventé (la maquette montre "Jonathan Mukendi / Executive
 * President" mais `users` n'a ni display_name ni titre — seul l'email
 * existe réellement, voir 001_initial_schema.sql) : le rôle affiché est
 * "Admin" (common.role.admin), pas un intitulé de poste fictif.
 *
 * Notification : cloche décorative, aucun système de notifications construit
 * (pas de ticket dédié) — pas de pastille "non lu" inventée, ça affirmerait
 * un état qu'aucune donnée ne soutient.
 */
$activeNav ??= null;
$adminInitial = $adminEmail ?? '' ? mb_strtoupper(mb_substr((string) $adminEmail, 0, 1)) : '?';
// Même mécanique que resources/views/partials/header.php (public) : GET
// /lang/{locale} (FIUCC-30) pose $_SESSION['locale'] et redirige vers la
// page d'origine — rien de spécifique à l'admin à construire ici.
$locale = \App\Core\Lang::locale();
$enLocaleClasses = $locale === 'en' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
$frLocaleClasses = $locale === 'fr' ? 'bg-primary text-white' : 'text-muted hover:text-ink';
?>
<div class="flex flex-col md:flex-row min-h-screen">
    <!-- Barre du haut sur mobile (< md), colonne latérale fixe à partir de
         md — un seul jeu de classes responsive plutôt que deux marquages
         séparés, pour ne pas désynchroniser mobile et desktop plus tard. -->
    <aside class="w-full md:w-64 md:shrink-0 md:fixed md:inset-y-0 md:left-0 md:z-30 bg-primary text-white flex flex-col md:h-screen">
        <div class="flex flex-col flex-1 md:overflow-y-auto">
            <div class="flex items-center gap-3 px-4 md:px-5 h-16 bg-primary-dark/40 border-b border-white/10 shrink-0">
                <img src="/assets/images/fiucc-seal.png" alt="<?= e(t('admin.brand.name')) ?>" class="h-9 w-9 object-contain shrink-0">
                <div class="leading-tight min-w-0">
                    <p class="font-label-lg text-label-lg truncate"><?= e(t('admin.brand.name')) ?></p>
                    <p class="hidden sm:block text-label-caps font-label-caps uppercase text-white/90"><?= e(t('admin.brand.tagline')) ?></p>
                </div>
            </div>
            <p class="hidden md:block px-3 pt-3 pb-1 font-label-caps text-label-caps uppercase text-white/90 tracking-wider"><?= e(t('admin.nav.section_label')) ?></p>
            <nav class="flex-1 py-2 md:py-1 px-3 flex flex-row md:flex-col gap-1 overflow-x-auto">
                <?php
                // Item actif : pastille claire + liseré or à gauche (charte —
                // l'or comme accent, jamais comme fond) sur md+ ; sur mobile
                // (nav horizontale), juste la pastille, le liseré vertical
                // n'aurait pas de sens dans une rangée.
                $activeClasses = 'relative bg-white text-primary font-bold shadow-card before:hidden md:before:block before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-1 before:h-5 before:bg-gold before:rounded-r-full';
                $inactiveClasses = 'text-white/90 hover:bg-white/10 hover:text-white';
                ?>
                <a href="/admin/members"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'members' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'members' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-users w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.members')) ?>
                </a>
                <a href="/admin/events"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'events' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'events' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-calendar-days w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.events')) ?>
                </a>
                <a href="/admin/gallery"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'gallery' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'gallery' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-images w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.gallery')) ?>
                </a>
                <a href="/admin/news"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'news' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'news' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-newspaper w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.news')) ?>
                </a>
                <a href="/admin/documents"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'documents' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'documents' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-folder-open w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.documents')) ?>
                </a>
                <a href="/admin/elections"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'elections' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'elections' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-check-to-slot w-5 text-center" aria-hidden="true"></i>
                    <?= e(t('admin.nav.elections')) ?>
                </a>
            </nav>
        </div>
        <div class="hidden md:flex items-center gap-3 p-4 bg-primary-dark/40 border-t border-white/10 shrink-0">
            <span class="h-10 w-10 rounded-full bg-white/15 flex items-center justify-center font-label-lg text-label-lg font-bold shrink-0" aria-hidden="true">
                <?= e($adminInitial) ?>
            </span>
            <div class="leading-tight min-w-0 flex-1">
                <p class="text-label-md font-label-md text-white truncate"><?= e($adminEmail ?? '') ?></p>
                <p class="text-label-caps font-label-caps text-white/90 uppercase truncate"><?= e(t('common.role.admin')) ?></p>
            </div>
            <form method="POST" action="/logout" class="shrink-0">
                <?= csrf_field() ?>
                <button type="submit" title="<?= e(t('admin.nav.logout')) ?>" aria-label="<?= e(t('admin.nav.logout')) ?>"
                    class="h-8 w-8 flex items-center justify-center rounded-control text-white/90 hover:bg-white/10 hover:text-white transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 md:ml-64">
        <header class="h-14 md:h-16 bg-white border-b border-border flex items-center justify-between px-4 md:px-6 shrink-0">
            <h1 class="font-headline-sm text-headline-sm text-ink"><?= e($title ?? '') ?></h1>
            <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center gap-1 mr-1 text-label-md font-label-md">
                    <a href="/lang/en" class="px-2 py-1 rounded-control transition-colors <?= e($enLocaleClasses) ?>">EN</a>
                    <a href="/lang/fr" class="px-2 py-1 rounded-control transition-colors <?= e($frLocaleClasses) ?>">FR</a>
                </div>
                <button type="button" title="<?= e(t('admin.header.notifications')) ?>" aria-label="<?= e(t('admin.header.notifications')) ?>"
                    class="h-10 w-10 rounded-control flex items-center justify-center text-muted hover:bg-surface hover:text-ink transition-colors">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                </button>
                <details class="relative">
                    <summary title="<?= e(t('admin.header.profile')) ?>" aria-label="<?= e(t('admin.header.profile')) ?>"
                        class="cursor-pointer list-none h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-label-lg text-label-lg font-bold hover:bg-primary/20 transition-colors">
                        <?= e($adminInitial) ?>
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-56 bg-white border border-border rounded-card shadow-card overflow-hidden text-left">
                        <div class="p-3 border-b border-border">
                            <p class="text-label-md font-label-md text-ink truncate"><?= e($adminEmail ?? '') ?></p>
                            <p class="text-label-caps font-label-caps text-muted uppercase"><?= e(t('common.role.admin')) ?></p>
                        </div>
                        <form method="POST" action="/logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2.5 text-body-sm text-ink hover:bg-surface transition-colors">
                                <i class="fa-solid fa-arrow-right-from-bracket text-muted" aria-hidden="true"></i>
                                <?= e(t('admin.nav.logout')) ?>
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
        <main class="flex-1 p-4 md:p-8 overflow-x-auto">
            <?= $content ?>
        </main>
    </div>
</div>
<script src="/assets/js/admin-nav.js" defer></script>
</body>
</html>
