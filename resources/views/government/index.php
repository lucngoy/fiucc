<?php
/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. Mandats précédents (archive) : FIUCC-42, route déjà déclarée
 * (routes/web.php) mais volontairement pas construite ici.
 *
 * Mise en page rapprochée de la maquette fiucc_portal_community_government :
 * bandeau de statistiques, premier membre (sort_order) mis en avant
 * séparément ("spotlight"), en-tête "Executive Bureau" + puces de filtre
 * au-dessus de la grille.
 *
 * Statistiques : calculées à partir de données réelles (nombre de postes,
 * facultés distinctes, durée du mandat), jamais un quorum/taux de
 * participation inventé — ça viendra du système d'élection (phase 4),
 * pas de ce ticket.
 *
 * Puces de filtre : rendues telles quelles, décoratives pour l'instant —
 * aucune donnée de département n'existe pour filtrer réellement dessus.
 * À dynamiser quand ce sera pertinent (demande explicite de l'utilisateur),
 * pas anticipé plus loin ici.
 */
$government ??= null;
$members = $government['members'] ?? [];
$spotlight = $members[0] ?? null;
$rest = $members !== [] ? array_slice($members, 1) : [];

$officesCount = count($members);
$facultiesCount = count(array_unique(array_filter(array_column($members, 'faculty'))));
$durationMonths = null;

if ($government !== null && !empty($government['start_date']) && !empty($government['end_date'])) {
    $durationMonths = (int) round(
        (strtotime($government['end_date']) - strtotime($government['start_date'])) / (30.44 * 86400)
    );
}

$renderSocialLink = static function (?string $instagram): string {
    if (empty($instagram)) {
        return '';
    }

    $handle = ltrim($instagram, '@');

    return '<a href="' . e('https://www.instagram.com/' . $handle . '/') . '" target="_blank" rel="noopener noreferrer" '
        . 'title="Instagram" aria-label="Instagram — @' . e($handle) . '" '
        . 'class="inline-flex h-8 w-8 rounded-full bg-primary/15 text-primary items-center justify-center hover:bg-primary/25 transition-colors text-label-md font-label-md">'
        . '@</a>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-16 md:pt-10 md:pb-20">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/70 mb-8">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/community" class="hover:text-white transition-colors"><?= e(t('community.title')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('government.title')) ?></span>
        </nav>
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-10">
            <div class="max-w-2xl">
                <h1 class="font-headline-lg text-headline-lg text-white mb-4"><?= e(t('government.title')) ?></h1>
                <?php if ($government !== null): ?>
                    <p class="text-body-lg text-white/80"><?= e(t('government.subtitle', ['mandate' => $government['mandate']])) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($government !== null): ?>
                <span class="inline-flex items-center h-9 px-4 rounded-full bg-white/15 text-white text-label-md font-label-md shrink-0">
                    <?= e(t('government.mandate_badge', ['mandate' => $government['mandate']])) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($members !== []): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 max-w-2xl">
                <div class="bg-white/10 rounded-control px-4 py-3">
                    <p class="font-headline-md text-headline-md text-white"><?= e((string) $officesCount) ?></p>
                    <p class="text-label-md font-label-md text-white/70"><?= e(t('government.stat.offices')) ?></p>
                </div>
                <div class="bg-white/10 rounded-control px-4 py-3">
                    <p class="font-headline-md text-headline-md text-white"><?= e((string) $facultiesCount) ?></p>
                    <p class="text-label-md font-label-md text-white/70"><?= e(t('government.stat.faculties')) ?></p>
                </div>
                <?php if ($durationMonths !== null): ?>
                    <div class="bg-white/10 rounded-control px-4 py-3">
                        <p class="font-headline-md text-headline-md text-white"><?= e((string) $durationMonths) ?></p>
                        <p class="text-label-md font-label-md text-white/70"><?= e(t('government.stat.duration')) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
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
            <div class="text-center max-w-2xl mx-auto mb-8">
                <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('government.bureau.eyebrow')) ?></p>
                <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('government.bureau.title')) ?></h2>
                <p class="text-body-md text-muted"><?= e(t('government.bureau.subtitle')) ?></p>
            </div>

            <!-- Filtre décoratif pour l'instant : aucune donnée de département
                 à filtrer dessus. À dynamiser plus tard, pas anticipé ici. -->
            <div class="flex flex-wrap items-center justify-center gap-2 mb-8" role="group" aria-label="<?= e(t('government.bureau.title')) ?>">
                <span class="inline-flex items-center h-8 px-4 rounded-full bg-primary text-white text-label-md font-label-md"><?= e(t('government.filter.all')) ?></span>
                <span class="inline-flex items-center h-8 px-4 rounded-full bg-surface text-muted text-label-md font-label-md"><?= e(t('government.filter.welfare')) ?></span>
                <span class="inline-flex items-center h-8 px-4 rounded-full bg-surface text-muted text-label-md font-label-md"><?= e(t('government.filter.academic')) ?></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($rest as $member): ?>
                    <div class="bg-white rounded-card border border-border shadow-card overflow-hidden">
                        <?php if (!empty($member['photo'])): ?>
                            <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="w-full aspect-square object-cover">
                        <?php else: ?>
                            <span class="w-full aspect-square bg-primary/15 text-primary flex items-center justify-center font-headline-lg text-headline-lg">
                                <?= e(initials($member['display_name'])) ?>
                            </span>
                        <?php endif; ?>
                        <div class="p-5">
                            <span class="inline-flex items-center h-6 px-2.5 mb-2 rounded-full bg-primary/15 text-primary text-label-md font-label-md">
                                <?= e($member['role']) ?>
                            </span>
                            <h3 class="font-headline-sm text-headline-sm text-ink mb-1"><?= e($member['display_name']) ?></h3>
                            <?php if (!empty($member['faculty'])): ?>
                                <p class="text-body-sm text-muted mb-3"><?= e($member['faculty']) ?></p>
                            <?php endif; ?>
                            <?= $renderSocialLink($member['instagram'] ?? null) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
