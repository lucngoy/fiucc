<?php
/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. Structure rapprochée de la maquette fiucc_portal_community_
 * government, avec des données réelles partout où c'est possible :
 *
 * - statistiques (postes, facultés représentées, mois de mandat) calculées,
 *   jamais de quorum/taux de participation inventé (système d'élection,
 *   phase 4, pas de ce ticket) ;
 * - filtre par faculté réellement fonctionnel (JS, government-filter.js),
 *   généré à partir des facultés distinctes présentes dans les données —
 *   pas les catégories "Welfare/Academic" de la maquette, qui ne
 *   correspondent à aucune donnée ;
 * - organigramme : nœud du haut dynamique (spotlight), paliers 2 et 3 repris
 *   textuellement du texte de la maquette (structure constitutionnelle, pas
 *   les 8 membres réels déjà montrés dans la grille) ; branches reliant les
 *   paliers reprises de code.html avec le même procédé (des div positionnées
 *   — trait vertical, point, trait horizontal en absolute), jamais un tracé
 *   SVG ;
 * - mandats précédents : liste réelle (App\Models\Government::pastMandates())
 *   plutôt qu'un historique inventé — vide aujourd'hui, FIUCC étant à son
 *   mandat fondateur, affiché honnêtement comme tel plutôt que masqué ;
 * - "Rule of Law & Statutory Safeguards" : titre et structure repris de la
 *   maquette, mais un seul vrai lien (/contact, route déjà déclarée) — pas
 *   de faux téléchargement PDF vers un règlement qui n'existe pas
 *   (Documents, FIUCC-57/58, pas construit).
 *
 * Fonds de section : chaque section est une bande pleine largeur qui
 * alterne bg-white / bg-surface avec sa voisine — même patron que
 * /community, pas des cartes flottant sur le fond par défaut de la page.
 * Deux couleurs identiques ne se distinguent jamais l'une de l'autre,
 * qu'elles soient en bande ou en carte : ce qui compte, c'est que chaque
 * bande diffère de celle juste au-dessus et juste en dessous. L'en-tête
 * (bg-primary) reste à part, comme dans code.html.
 *
 * Archive complète avec anciens membres : /community/government/archives,
 * App\Controllers\GovernmentController::archives() (FIUCC-42).
 */
$government ??= null;
$pastMandates ??= [];
$members = $government['members'] ?? [];
$spotlight = $members[0] ?? null;
$rest = $members !== [] ? array_slice($members, 1) : [];

// Organigramme : structure constitutionnelle (paliers 2 et 3), reprise
// textuellement de la maquette — ce ne sont pas les 8 membres réels du
// bureau (déjà montrés dans la grille au-dessus), mais les fonctions
// prévues par la structure elle-même, indépendamment de qui les occupe.
// Seul le nœud du haut (président) reste dynamique, avec les vraies
// données du spotlight.
$orgTier2 = [
    ['eyebrow' => t('government.orgchart.pillar1_eyebrow'), 'title' => t('government.orgchart.pillar1_title'), 'subtitle' => t('government.orgchart.pillar1_subtitle')],
    ['eyebrow' => t('government.orgchart.pillar2_eyebrow'), 'title' => t('government.orgchart.pillar2_title'), 'subtitle' => t('government.orgchart.pillar2_subtitle')],
];
$orgTier3 = [
    ['title' => t('government.orgchart.bureau1_title'), 'subtitle' => t('government.orgchart.bureau1_subtitle')],
    ['title' => t('government.orgchart.bureau2_title'), 'subtitle' => t('government.orgchart.bureau2_subtitle')],
    ['title' => t('government.orgchart.bureau3_title'), 'subtitle' => t('government.orgchart.bureau3_subtitle')],
    ['title' => t('government.orgchart.bureau4_title'), 'subtitle' => t('government.orgchart.bureau4_subtitle')],
    ['title' => t('government.orgchart.bureau5_title'), 'subtitle' => t('government.orgchart.bureau5_subtitle')],
];

$officesCount = count($members);
$facultiesCount = count(array_unique(array_filter(array_column($members, 'faculty'))));
$durationMonths = null;

if ($government !== null && !empty($government['start_date']) && !empty($government['end_date'])) {
    $durationMonths = (int) round(
        (strtotime($government['end_date']) - strtotime($government['start_date'])) / (30.44 * 86400)
    );
}

// Format "6 Offices / Constitutional Pillars" de la maquette : une ligne
// principale (valeur + mot), une légende en dessous — pas juste un nombre nu.
$stats = array_filter([
    [
        'icon'    => 'fa-solid fa-building-columns',
        'primary' => t('government.stat.offices_value', ['count' => $officesCount]),
        'caption' => t('government.stat.offices_caption'),
    ],
    [
        'icon'    => 'fa-solid fa-graduation-cap',
        'primary' => t('government.stat.faculties_value', ['count' => $facultiesCount]),
        'caption' => t('government.stat.faculties_caption'),
    ],
    $durationMonths !== null ? [
        'icon'    => 'fa-solid fa-calendar-check',
        'primary' => t('government.stat.duration_value', ['count' => $durationMonths]),
        'caption' => t('government.stat.duration_caption'),
    ] : null,
]);

// Filtre par faculté — réellement fonctionnel (government-filter.js),
// généré depuis les facultés distinctes présentes dans la grille.
$facultyFilters = array_values(array_unique(array_filter(array_column($rest, 'faculty'))));

$renderSocialLink = static function (?string $instagram, string $size = 'h-8 w-8'): string {
    if (empty($instagram)) {
        return '';
    }

    $handle = ltrim($instagram, '@');

    return '<a href="' . e('https://www.instagram.com/' . $handle . '/') . '" target="_blank" rel="noopener noreferrer" '
        . 'title="Instagram" aria-label="Instagram — @' . e($handle) . '" '
        . 'class="inline-flex ' . $size . ' rounded-full bg-primary/[4%] text-primary items-center justify-center hover:bg-primary/25 transition-colors">'
        . '<i class="fa-brands fa-instagram" aria-hidden="true"></i></a>';
};

// Branche verticale au-dessus d'une carte de palier — même procédé que
// code.html (div positionnées en absolute), pas de tracé SVG.
$renderOrgBranch = static function (string $visibility = ''): string {
    return '<div class="' . ($visibility !== '' ? $visibility . ' ' : '') . 'absolute -top-4 left-1/2 -translate-x-1/2 w-0.5 h-4 bg-border" aria-hidden="true"></div>';
};

$renderOrgTier2Box = static function (array $item) use ($renderOrgBranch): string {
    return '<div class="relative bg-white rounded-card border border-border px-4 py-3">'
        . $renderOrgBranch('hidden sm:block')
        . '<p class="text-label-caps font-label-caps uppercase text-muted mb-1">' . e($item['eyebrow']) . '</p>'
        . '<p class="font-label-lg text-label-lg text-ink">' . e($item['title']) . '</p>'
        . '<p class="text-body-sm text-muted">' . e($item['subtitle']) . '</p>'
        . '</div>';
};

$renderOrgTier3Box = static function (array $item) use ($renderOrgBranch): string {
    return '<div class="relative bg-white rounded-card border border-border px-3 py-3 text-center">'
        . $renderOrgBranch('hidden lg:block')
        . '<p class="text-label-md font-label-md text-ink">' . e($item['title']) . '</p>'
        . '<p class="text-label-caps text-muted">' . e($item['subtitle']) . '</p>'
        . '</div>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <div class="flex items-center justify-between gap-4 mb-6">
            <nav aria-label="Breadcrumb" class="text-body-sm text-white/90">
                <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="/community" class="hover:text-white transition-colors"><?= e(t('community.title')) ?></a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white"><?= e(t('government.title')) ?></span>
            </nav>
            <?php if ($government !== null): ?>
                <span class="inline-flex items-center h-8 px-3 rounded-full bg-gold text-ink text-label-md font-label-md shrink-0">
                    <?= e(t('government.mandate_badge', ['mandate' => $government['mandate']])) ?>
                </span>
            <?php endif; ?>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('government.title')) ?></h1>
        <?php if ($government !== null): ?>
            <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('government.subtitle', ['mandate' => $government['mandate']])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($stats !== []): ?>
        <div class="w-full bg-primary-dark/40">
            <div class="max-w-layout mx-auto px-4 md:px-6 py-5">
                <div class="grid grid-cols-3 gap-4 md:gap-8">
                    <?php foreach (array_values($stats) as $index => $stat): ?>
                        <div class="flex items-center gap-3 min-w-0 <?= $index > 0 ? 'md:pl-8 md:border-l md:border-white/20' : '' ?>">
                            <i class="<?= e($stat['icon']) ?> text-white/70 text-xl" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <p class="font-headline-sm text-headline-sm text-white"><?= e($stat['primary']) ?></p>
                                <p class="text-label-md font-label-md text-white/90 break-words"><?= e($stat['caption']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <?php if ($government === null || $members === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('government.empty')]) ?>
    <?php else: ?>
        <?php if ($spotlight !== null): ?>
            <div class="bg-surface rounded-card border border-border p-6 md:p-8 mb-10">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-8 text-center md:text-left">
                    <?php if (!empty($spotlight['photo'])): ?>
                        <img src="<?= e('/uploads/' . $spotlight['photo']) ?>" alt=""
                             class="h-32 w-32 md:h-36 md:w-36 rounded-full object-cover border border-border shrink-0">
                    <?php else: ?>
                        <span class="h-32 w-32 md:h-36 md:w-36 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-headline-lg text-headline-lg shrink-0">
                            <?= e(initials($spotlight['display_name'])) ?>
                        </span>
                    <?php endif; ?>
                    <div>
                        <span class="inline-flex items-center h-7 px-3 rounded-full bg-gold/20 text-ink text-label-md font-label-md mb-3">
                            <?= e($spotlight['role']) ?>
                        </span>
                        <h2 class="font-headline-md text-headline-md text-ink mb-1"><?= e($spotlight['display_name']) ?></h2>
                        <?php if (!empty($spotlight['faculty'])): ?>
                            <p class="text-body-sm text-muted mb-3"><?= e($spotlight['faculty']) ?></p>
                        <?php endif; ?>
                        <?= $renderSocialLink($spotlight['instagram'] ?? null) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($rest !== []): ?>
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
                <div>
                    <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><i class="fa-solid fa-shield-halved mr-1.5" aria-hidden="true"></i><?= e(t('government.bureau.eyebrow')) ?></p>
                    <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('government.bureau.title')) ?></h2>
                    <p class="text-body-md text-muted max-w-xl"><?= e(t('government.bureau.subtitle')) ?></p>
                </div>

                <?php if ($facultyFilters !== []): ?>
                    <div class="flex items-center gap-2">
                        <label for="government-filter" class="text-label-md font-label-md text-muted shrink-0"><?= e(t('government.filter_by')) ?></label>
                        <select id="government-filter"
                            style="max-width: 200px;"
                            class="h-9 w-full rounded-control border border-border px-3 text-body-sm text-ink bg-white focus:outline-none focus:ring-2 focus:ring-primary-light/50">
                            <option value="all"><?= e(t('government.filter.all')) ?></option>
                            <?php foreach ($facultyFilters as $faculty): ?>
                                <option value="<?= e($faculty) ?>"><?= e($faculty) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($rest as $member): ?>
                    <div data-faculty="<?= e($member['faculty'] ?? '') ?>" class="bg-surface rounded-card border border-border overflow-hidden">
                        <?php if (!empty($member['photo'])): ?>
                            <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="w-full aspect-square object-cover">
                        <?php else: ?>
                            <span class="w-full aspect-square bg-primary/[4%] text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                                <?= e(initials($member['display_name'])) ?>
                            </span>
                        <?php endif; ?>
                        <div class="p-3">
                            <span class="inline-flex items-center h-5 px-2 mb-1.5 rounded-full bg-primary/[4%] text-primary text-label-caps font-label-caps uppercase">
                                <?= e($member['role']) ?>
                            </span>
                            <h3 class="font-label-lg text-label-lg text-ink mb-0.5"><?= e($member['display_name']) ?></h3>
                            <?php if (!empty($member['faculty'])): ?>
                                <p class="text-label-caps font-label-caps text-muted mb-2"><?= e($member['faculty']) ?></p>
                            <?php endif; ?>
                            <?= $renderSocialLink($member['instagram'] ?? null, 'h-7 w-7') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<?php if ($spotlight !== null): ?>
<div class="w-full bg-surface">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <!-- En-tête de section aligné à gauche, comme code.html (pas centré). -->
    <div class="mb-8">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><i class="fa-solid fa-sitemap mr-1.5" aria-hidden="true"></i><?= e(t('government.orgchart.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('government.orgchart.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('government.orgchart.subtitle')) ?></p>
    </div>
    <div class="flex flex-col items-center">
        <!-- Nœud du haut : dynamique, vraies données du spotlight. -->
        <div class="w-full max-w-sm bg-primary text-white rounded-card shadow-card px-4 py-3 text-center">
            <p class="text-label-caps font-label-caps uppercase text-white/90 mb-1"><?= e(t('government.orgchart.top_eyebrow')) ?></p>
            <p class="font-label-lg text-label-lg"><?= e(t('government.orgchart.top_title')) ?></p>
            <p class="text-body-sm text-white/90"><?= e(t('government.orgchart.top_subtitle', ['name' => $spotlight['display_name']])) ?></p>
        </div>

        <!-- Branche président → palier 2. -->
        <div class="w-0.5 h-6 bg-border" aria-hidden="true"></div>
        <div class="w-3 h-3 rounded-full bg-primary -mt-1.5 mb-1" aria-hidden="true"></div>

        <!-- Paliers 2 et 3 : structure constitutionnelle, reprise textuellement
             de la maquette (pas les membres réels, déjà montrés au-dessus).
             Branches en div positionnées, même procédé que code.html — pas
             de tracé SVG. -->
        <div class="relative w-full max-w-xl pt-4">
            <div class="hidden sm:block absolute top-0 left-1/4 right-1/4 h-0.5 bg-border" aria-hidden="true"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
                <?php foreach ($orgTier2 as $item): ?>
                    <?= $renderOrgTier2Box($item) ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Branche palier 2 → palier 3. -->
        <div class="w-0.5 h-6 bg-border" aria-hidden="true"></div>
        <div class="w-3 h-3 rounded-full bg-primary -mt-1.5 mb-1" aria-hidden="true"></div>

        <div class="relative w-full pt-4">
            <div class="hidden lg:block absolute top-0 left-[10%] right-[10%] h-0.5 bg-border" aria-hidden="true"></div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 w-full">
                <?php foreach ($orgTier3 as $item): ?>
                    <?= $renderOrgTier3Box($item) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php endif; ?>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <!-- En-tête aligné à gauche avec l'action à droite, comme code.html —
         lien vers l'archive complète (FIUCC-42) seulement s'il y a
         réellement quelque chose à y voir. -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
        <div>
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><i class="fa-solid fa-clock-rotate-left mr-1.5" aria-hidden="true"></i><?= e(t('government.history.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('government.history.title')) ?></h2>
            <p class="text-body-md text-muted max-w-xl"><?= e(t('government.history.subtitle')) ?></p>
        </div>
        <?php if ($pastMandates !== []): ?>
            <a href="/community/government/archives" class="inline-flex items-center gap-2 text-primary font-label-lg text-label-lg font-semibold hover:underline shrink-0">
                <?= e(t('government.history.view_all')) ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($pastMandates === []): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('government.history.empty')]) ?>
    <?php else: ?>
        <div class="max-w-xl mx-auto flex flex-col gap-3">
            <?php foreach ($pastMandates as $mandate): ?>
                <div class="bg-surface rounded-card border border-border p-4 flex items-center justify-between">
                    <span class="font-label-lg text-label-lg text-ink"><?= e($mandate['mandate']) ?></span>
                    <span class="text-body-sm text-muted"><?= e(local_date($mandate['start_date'])) ?> – <?= e(local_date($mandate['end_date'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<div class="w-full bg-surface">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-4 text-center md:text-left">
            <span class="hidden md:flex h-12 w-12 rounded-full bg-primary/10 text-primary items-center justify-center shrink-0" aria-hidden="true"><i class="fa-solid fa-scale-balanced text-xl"></i></span>
            <div>
                <p class="font-label-caps text-label-caps uppercase text-primary mb-1"><?= e(t('government.rule.title')) ?></p>
                <p class="text-body-sm text-ink max-w-2xl"><?= e(t('government.rule.body')) ?></p>
            </div>
        </div>
        <div class="shrink-0">
            <?= component('button', ['label' => t('government.rule.contact'), 'variant' => 'primary', 'href' => '/contact']) ?>
        </div>
    </div>
</div>
</div>

<?php if ($facultyFilters !== []): ?>
    <script src="/assets/js/government-filter.js" defer></script>
<?php endif; ?>
