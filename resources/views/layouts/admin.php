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
    <!-- Police et icônes semiauto-hébergées (FIUCC-78) — voir layouts/app.php. -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="/assets/images/fiucc-seal.png">
</head>
<body class="bg-surface">
<?php
/**
 * Coquille du back office (FIUCC-43) — maquette fiucc_portal_admin_dashboard/
 * code.html pour le style (sidebar bleu marine, cartes blanches arrondies).
 * Barre latérale volontairement minimale : "Tableau de bord" (FIUCC-23,
 * ajouté en préparant FIUCC-81 — la route existait depuis le début dans
 * routes/admin.php mais Admin\DashboardController n'a été construit que là,
 * voir CLAUDE.md), "Membres", "Événements", "Galerie", "Actualités",
 * "Documents", "Partenaires", "Élections" et "Sauvegardes" mènent à une
 * page réelle aujourd'hui. Pas de lien mort vers Emails/Logs/Admins/
 * Paramètres — ces sections n'ont ni contrôleur ni ticket construits (une
 * phase à la fois, CLAUDE.md) ; elles rejoindront la navigation quand leur
 * ticket existera, plutôt que de simuler un back office plus complet qu'il
 * ne l'est. "Élections" (FIUCC-59/60) : création/configuration seulement
 * pour l'instant — candidatures/vote/résultats arrivent avec les tickets
 * suivants (FIUCC-61+), pas de bouton pour des actions qui n'existent pas
 * encore.
 *
 * Sidebar fixe au scroll (md+) : logo officiel de la communauté (même
 * asset que le header public, /assets/images/fiucc-seal.png) + nav + pied
 * de compte (email, déconnexion) — dupliqué avec le menu profil de la
 * barre du haut par choix : les deux restent accessibles, l'un visible en
 * permanence en bas de la colonne latérale (desktop), l'autre présent
 * même sur mobile où la sidebar devient une barre du haut, sa nav repliée
 * derrière un bouton hamburger (admin-nav.js). Pas de nom/
 * rôle inventé (la maquette montre "Jonathan Mukendi / Executive
 * President" mais `users` n'a ni display_name ni titre — seul l'email
 * existe réellement, voir 001_initial_schema.sql) : le rôle affiché est
 * "Admin" (common.role.admin), pas un intitulé de poste fictif.
 *
 * Cloche : tâches en attente ($adminTasks, App\Services\AdminTasks, injecté
 * par App\Core\Controller::view()) — pas un système de notifications. La
 * pastille montre le total à traiter, jamais un "non lu" qu'aucune donnée
 * ne soutient.
 */
$activeNav ??= null;
$adminTasks ??= [];
$adminTasksTotal = array_sum(array_column($adminTasks, 'count'));
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
    <aside class="relative w-full overflow-hidden md:w-64 md:shrink-0 md:fixed md:inset-y-0 md:left-0 md:z-30 bg-primary text-white flex flex-col md:h-screen">
        <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
        <div class="flex flex-col flex-1 md:overflow-y-auto">
            <div class="flex items-center gap-3 px-4 md:px-5 h-16 bg-primary-dark/40 border-b border-white/10 shrink-0">
                <a href="/" class="flex items-center gap-3 shrink-0">
                    <img src="/assets/images/fiucc-seal.png" alt="<?= e(t('admin.brand.name')) ?>" class="h-9 w-9 object-contain shrink-0">
                </a>
                <div class="leading-tight min-w-0 flex-1">
                    <p class="font-label-lg text-label-lg truncate"><?= e(t('admin.brand.name')) ?></p>
                    <p class="hidden sm:block text-label-caps font-label-caps uppercase text-white/90"><?= e(t('admin.brand.tagline')) ?></p>
                </div>
                <!-- Mobile (< md) : la nav est repliée derrière ce bouton
                     (admin-nav.js) plutôt qu'une rangée de 12 liens à faire
                     défiler à l'horizontale. -->
                <button type="button" id="admin-nav-toggle"
                    class="md:hidden shrink-0 h-10 w-10 flex items-center justify-center rounded-control text-white hover:bg-white/10 transition-colors"
                    aria-expanded="false" aria-controls="admin-nav"
                    title="<?= e(t('admin.nav.toggle')) ?>" aria-label="<?= e(t('admin.nav.toggle')) ?>">
                    <span data-icon-open><?= icon('bars', 'text-lg') ?></span>
                    <span data-icon-close class="hidden"><?= icon('xmark', 'text-lg') ?></span>
                </button>
            </div>
            <p class="hidden md:block px-3 pt-3 pb-1 font-label-caps text-label-caps uppercase text-white/90 tracking-wider"><?= e(t('admin.nav.section_label')) ?></p>
            <nav id="admin-nav" class="hidden md:flex flex-1 py-2 md:py-1 px-3 flex-col gap-1">
                <?php
                // Item actif : pastille claire + liseré or à gauche (charte —
                // l'or comme accent, jamais comme fond), en colonne sur
                // mobile (menu déplié) comme sur desktop.
                $activeClasses = 'relative bg-white text-primary font-bold shadow-card before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-1 before:h-5 before:bg-gold before:rounded-r-full';
                $inactiveClasses = 'text-white/90 hover:bg-white/10 hover:text-white';
                ?>
                <a href="/admin"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'dashboard' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>>
                    <?= icon('chart-simple', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.dashboard')) ?>
                </a>
                <a href="/admin/members"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'members' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'members' ? 'aria-current="page"' : '' ?>>
                    <?= icon('users', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.members')) ?>
                </a>
                <a href="/admin/government"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'government' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'government' ? 'aria-current="page"' : '' ?>>
                    <?= icon('people-group', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.government')) ?>
                </a>
                <a href="/admin/events"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'events' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'events' ? 'aria-current="page"' : '' ?>>
                    <?= icon('calendar-days', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.events')) ?>
                </a>
                <a href="/admin/gallery"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'gallery' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'gallery' ? 'aria-current="page"' : '' ?>>
                    <?= icon('images', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.gallery')) ?>
                </a>
                <a href="/admin/news"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'news' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'news' ? 'aria-current="page"' : '' ?>>
                    <?= icon('newspaper', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.news')) ?>
                </a>
                <a href="/admin/documents"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'documents' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'documents' ? 'aria-current="page"' : '' ?>>
                    <?= icon('folder-open', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.documents')) ?>
                </a>
                <a href="/admin/partners"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'partners' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'partners' ? 'aria-current="page"' : '' ?>>
                    <?= icon('building-columns', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.partners')) ?>
                </a>
                <a href="/admin/elections"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'elections' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'elections' ? 'aria-current="page"' : '' ?>>
                    <?= icon('check-to-slot', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.elections')) ?>
                </a>
                <a href="/admin/contact"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'contact' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'contact' ? 'aria-current="page"' : '' ?>>
                    <?= icon('envelope', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.contact')) ?>
                </a>
                <a href="/admin/backups"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'backups' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'backups' ? 'aria-current="page"' : '' ?>>
                    <?= icon('database', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.backups')) ?>
                </a>
                <?php
                // Mises à jour de la base en attente : compteur visible dès
                // un déploiement, sans aller voir la page. Une erreur de
                // lecture ne doit jamais casser la navigation admin.
                try {
                    $pendingMigrations = count(\App\Services\Migrator::pending());
                } catch (\Throwable) {
                    $pendingMigrations = 0;
                }
                ?>
                <a href="/admin/migrations"
                   class="flex items-center gap-3 h-10 px-3 rounded-control text-label-lg font-label-lg whitespace-nowrap transition-colors <?= $activeNav === 'migrations' ? $activeClasses : $inactiveClasses ?>"
                   <?= $activeNav === 'migrations' ? 'aria-current="page"' : '' ?>>
                    <?= icon('rotate', 'w-5 text-center') ?>
                    <?= e(t('admin.nav.migrations')) ?>
                    <?php if ($pendingMigrations > 0): ?>
                        <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-gold text-ink text-label-caps font-label-caps"><?= e((string) $pendingMigrations) ?></span>
                    <?php endif; ?>
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
                    <?= icon('arrow-right-from-bracket') ?>
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 md:ml-64">
        <header class="sticky top-0 z-50 h-14 md:h-16 bg-white border-b border-border flex items-center justify-between px-4 md:px-6 shrink-0">
            <h1 class="font-headline-sm text-headline-sm text-ink"><?= e($title ?? '') ?></h1>
            <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center gap-1 mr-1 text-label-md font-label-md">
                    <a href="/lang/en" class="px-2 py-1 rounded-control transition-colors <?= e($enLocaleClasses) ?>">EN</a>
                    <a href="/lang/fr" class="px-2 py-1 rounded-control transition-colors <?= e($frLocaleClasses) ?>">FR</a>
                </div>
                <details class="relative">
                    <summary title="<?= e(t('admin.header.notifications')) ?>" aria-label="<?= e(t('admin.header.notifications')) ?>"
                        class="relative cursor-pointer list-none h-10 w-10 rounded-control flex items-center justify-center text-muted hover:bg-surface hover:text-ink transition-colors">
                        <?= icon('bell') ?>
                        <?php if ($adminTasksTotal > 0): ?>
                            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-gold text-ink text-[10px] font-bold leading-[18px] text-center"><?= e($adminTasksTotal > 99 ? '99+' : (string) $adminTasksTotal) ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-72 bg-white border border-border rounded-card shadow-card overflow-hidden text-left">
                        <p class="p-3 border-b border-border text-label-md font-label-md text-ink"><?= e(t('admin.tasks.title')) ?></p>
                        <?php if ($adminTasks === []): ?>
                            <p class="flex items-center gap-2 px-3 py-4 text-body-sm text-muted">
                                <?= icon('circle-check', 'text-success') ?>
                                <?= e(t('admin.tasks.none')) ?>
                            </p>
                        <?php else: ?>
                            <ul class="divide-y divide-border">
                                <?php foreach ($adminTasks as $task): ?>
                                    <li>
                                        <a href="<?= e($task['url']) ?>" class="flex items-center justify-between gap-3 px-3 py-2.5 text-body-sm text-ink hover:bg-surface transition-colors">
                                            <span><?= e($task['label']) ?></span>
                                            <?= icon('arrow-right', 'text-muted shrink-0') ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </details>
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
                        <a href="/admin/account/password" class="w-full flex items-center gap-2 px-3 py-2.5 text-body-sm text-ink hover:bg-surface transition-colors">
                            <?= icon('key', 'text-muted') ?>
                            <?= e(t('password.change.title')) ?>
                        </a>
                        <form method="POST" action="/logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2.5 text-body-sm text-ink hover:bg-surface transition-colors">
                                <?= icon('arrow-right-from-bracket', 'text-muted') ?>
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
<script src="https://kit.fontawesome.com/683aca505a.js" crossorigin="anonymous"></script>
<script src="/assets/js/admin-nav.js" defer></script>
<script src="/assets/js/encoded-uploads.js" defer></script>
</body>
</html>
