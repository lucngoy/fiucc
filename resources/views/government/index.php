<?php
/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. Mandats précédents (archive) : FIUCC-42, route déjà déclarée
 * (routes/web.php) mais volontairement pas construite ici.
 *
 * Mise en page rapprochée de la maquette fiucc_portal_community_government :
 * premier membre (sort_order) mis en avant séparément ("spotlight"), le
 * reste en grille avec photo carrée et badge de poste au-dessus du nom.
 * Écarté volontairement : bandeau de statistiques (quorum/turnout —
 * concepts du système d'élection, phase 4, pas de ce ticket ; les
 * fabriquer ici serait une fausse donnée sur une page publique), filtre
 * par département (aucune donnée de département sur government_members),
 * organigramme séparé (le spotlight + la grille donnent déjà la hiérarchie
 * visuelle), archive des mandats précédents (FIUCC-42).
 */
$government ??= null;
$members = $government['members'] ?? [];
$spotlight = $members[0] ?? null;
$rest = $members !== [] ? array_slice($members, 1) : [];
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
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
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
                        <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e($spotlight['display_name']) ?></h2>
                        <?php if (!empty($spotlight['instagram'])): ?>
                            <a href="<?= e('https://www.instagram.com/' . ltrim($spotlight['instagram'], '@') . '/') ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="text-body-sm text-primary hover:underline">
                                @<?= e(ltrim($spotlight['instagram'], '@')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($rest !== []): ?>
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
                            <?php if (!empty($member['instagram'])): ?>
                                <a href="<?= e('https://www.instagram.com/' . ltrim($member['instagram'], '@') . '/') ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="text-body-sm text-primary hover:underline">
                                    @<?= e(ltrim($member['instagram'], '@')) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
