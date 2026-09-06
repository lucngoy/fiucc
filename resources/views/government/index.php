<?php
/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. Structure rapprochée de la maquette fiucc_portal_community_
 * government, avec des données réelles partout où c'est possible :
 *
 * - statistiques (postes, facultés représentées, mois de mandat) calculées,
 *   jamais de quorum/taux de participation inventé (système d'élection,
 *   phase 4, pas de ce ticket) ;
 * - filtre par faculté réellement fonctionnel (JS, gouvernment-filter.js),
 *   généré à partir des facultés distinctes présentes dans les données —
 *   pas les catégories "Welfare/Academic" de la maquette, qui ne
 *   correspondent à aucune donnée ;
 * - organigramme simplifié (paliers déduits de sort_order : le président
 *   déjà en spotlight, puis le reste), sans lignes de connexion dessinées
 *   (risque de tracés SVG non vérifiés pour un gain purement décoratif) ;
 * - mandats précédents : liste réelle (App\Models\Government::pastMandates())
 *   plutôt qu'un historique inventé — vide aujourd'hui, FIUCC étant à son
 *   mandat fondateur, affiché honnêtement comme tel plutôt que masqué ;
 * - bande "Rule of Law" simplifiée en repère de contact (/contact, route
 *   déjà déclarée) : pas de faux lien de téléchargement vers un règlement
 *   PDF qui n'existe pas (Documents, FIUCC-57/58, pas construit).
 *
 * Archive complète avec anciens membres : FIUCC-42, ticket séparé, route
 * déjà déclarée dans routes/web.php mais pas construite ici.
 */
$government ??= null;
$pastMandates ??= [];
$members = $government['members'] ?? [];
$spotlight = $members[0] ?? null;
$rest = $members !== [] ? array_slice($members, 1) : [];

// Paliers de l'organigramme — déduits de sort_order (donnée réelle), pas de
// niveau hiérarchique inventé : le président est déjà en spotlight, le
// deuxième membre forme un "palier 2", le reste un "palier 3".
$tier2 = $rest[0] ?? null;
$tier3 = count($rest) > 1 ? array_slice($rest, 1) : [];

$officesCount = count($members);
$facultiesCount = count(array_unique(array_filter(array_column($members, 'faculty'))));
$durationMonths = null;

if ($government !== null && !empty($government['start_date']) && !empty($government['end_date'])) {
    $durationMonths = (int) round(
        (strtotime($government['end_date']) - strtotime($government['start_date'])) / (30.44 * 86400)
    );
}

$stats = array_filter([
    ['value' => (string) $officesCount, 'label' => t('government.stat.offices')],
    ['value' => (string) $facultiesCount, 'label' => t('government.stat.faculties')],
    $durationMonths !== null ? ['value' => (string) $durationMonths, 'label' => t('government.stat.duration')] : null,
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
        . 'class="inline-flex ' . $size . ' rounded-full bg-primary/15 text-primary items-center justify-center hover:bg-primary/25 transition-colors text-label-md font-label-md">'
        . '@</a>';
};

$renderOrgBox = static function (array $member, string $size = 'normal') use ($renderSocialLink): string {
    $isCompact = $size === 'compact';
    $namePx = $isCompact ? 'text-body-sm font-label-md' : 'font-headline-sm text-headline-sm';

    return '<div class="bg-white rounded-card border border-border shadow-card px-4 py-3 text-center">'
        . '<span class="inline-flex items-center h-5 px-2 mb-1 rounded-full bg-primary/15 text-primary text-label-caps font-label-caps uppercase">' . e($member['role']) . '</span>'
        . '<p class="' . $namePx . ' text-ink">' . e($member['display_name']) . '</p>'
        . '</div>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <div class="flex items-center justify-between gap-4 mb-6">
            <nav aria-label="Breadcrumb" class="text-body-sm text-white/70">
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
            <p class="text-body-lg text-white/80 max-w-2xl"><?= e(t('government.subtitle', ['mandate' => $government['mandate']])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($stats !== []): ?>
        <div class="w-full bg-primary-dark/40">
            <div class="max-w-layout mx-auto px-4 md:px-6 py-5">
                <div class="grid grid-cols-3 gap-4 md:gap-8">
                    <?php foreach (array_values($stats) as $index => $stat): ?>
                        <div class="<?= $index > 0 ? 'md:pl-8 md:border-l md:border-white/20' : '' ?>">
                            <p class="font-headline-sm text-headline-sm text-white"><?= e($stat['value']) ?></p>
                            <p class="text-label-md font-label-md text-white/70"><?= e($stat['label']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <?php if ($government === null || $members === []): ?>
        <div class="max-w-xl mx-auto">
            <?= component('alert', ['type' => 'info', 'message' => t('government.empty')]) ?>
        </div>
    <?php else: ?>
        <?php if ($spotlight !== null): ?>
            <div class="bg-white rounded-card border border-border shadow-card p-6 md:p-8 mb-10">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-8 text-center md:text-left">
                    <?php if (!empty($spotlight['photo'])): ?>
                        <img src="<?= e('/uploads/' . $spotlight['photo']) ?>" alt=""
                             class="h-32 w-32 md:h-36 md:w-36 rounded-full object-cover border border-border shrink-0">
                    <?php else: ?>
                        <span class="h-32 w-32 md:h-36 md:w-36 rounded-full bg-primary/15 text-primary flex items-center justify-center font-headline-lg text-headline-lg shrink-0">
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
                    <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('government.bureau.eyebrow')) ?></p>
                    <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('government.bureau.title')) ?></h2>
                    <p class="text-body-md text-muted max-w-xl"><?= e(t('government.bureau.subtitle')) ?></p>
                </div>

                <?php if ($facultyFilters !== []): ?>
                    <div class="flex flex-wrap items-center gap-2" id="government-filters">
                        <span class="text-label-md font-label-md text-muted mr-1"><?= e(t('government.filter_by')) ?></span>
                        <button type="button" data-filter-chip="all"
                            class="inline-flex items-center h-8 px-4 rounded-full bg-primary text-white text-label-md font-label-md transition-colors">
                            <?= e(t('government.filter.all')) ?>
                        </button>
                        <?php foreach ($facultyFilters as $faculty): ?>
                            <button type="button" data-filter-chip="<?= e($faculty) ?>"
                                class="inline-flex items-center h-8 px-4 rounded-full bg-surface text-muted text-label-md font-label-md transition-colors">
                                <?= e($faculty) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($rest as $member): ?>
                    <div data-faculty="<?= e($member['faculty'] ?? '') ?>" class="bg-white rounded-card border border-border shadow-card overflow-hidden">
                        <?php if (!empty($member['photo'])): ?>
                            <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="w-full aspect-square object-cover">
                        <?php else: ?>
                            <span class="w-full aspect-square bg-primary/15 text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                                <?= e(initials($member['display_name'])) ?>
                            </span>
                        <?php endif; ?>
                        <div class="p-3">
                            <span class="inline-flex items-center h-5 px-2 mb-1.5 rounded-full bg-primary/15 text-primary text-label-caps font-label-caps uppercase">
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

<?php if ($spotlight !== null): ?>
<div class="w-full bg-surface">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('government.orgchart.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('government.orgchart.title')) ?></h2>
            <p class="text-body-md text-muted"><?= e(t('government.orgchart.subtitle')) ?></p>
        </div>
        <div class="flex flex-col items-center gap-4 max-w-2xl mx-auto">
            <div class="w-56"><?= $renderOrgBox($spotlight) ?></div>
            <?php if ($tier2 !== null): ?>
                <div class="w-56"><?= $renderOrgBox($tier2) ?></div>
            <?php endif; ?>
            <?php if ($tier3 !== []): ?>
                <div class="flex flex-wrap justify-center gap-3 w-full">
                    <?php foreach ($tier3 as $member): ?>
                        <div class="w-40"><?= $renderOrgBox($member, 'compact') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="text-center max-w-2xl mx-auto mb-10">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('government.history.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('government.history.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('government.history.subtitle')) ?></p>
    </div>

    <?php if ($pastMandates === []): ?>
        <div class="max-w-xl mx-auto">
            <?= component('alert', ['type' => 'info', 'message' => t('government.history.empty')]) ?>
        </div>
    <?php else: ?>
        <div class="max-w-xl mx-auto flex flex-col gap-3">
            <?php foreach ($pastMandates as $mandate): ?>
                <div class="bg-white rounded-card border border-border shadow-card p-4 flex items-center justify-between">
                    <span class="font-label-lg text-label-lg text-ink"><?= e($mandate['mandate']) ?></span>
                    <span class="text-body-sm text-muted"><?= e(local_date($mandate['start_date'])) ?> – <?= e(local_date($mandate['end_date'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="w-full bg-surface">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20 text-center">
        <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('government.rule.title')) ?></h2>
        <p class="text-body-md text-muted mb-8 max-w-xl mx-auto"><?= e(t('government.rule.body')) ?></p>
        <?= component('button', ['label' => t('government.rule.contact'), 'variant' => 'primary', 'href' => '/contact']) ?>
    </div>
</div>

<?php if ($facultyFilters !== []): ?>
    <script src="/assets/js/government-filter.js" defer></script>
<?php endif; ?>
