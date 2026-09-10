<?php
/**
 * Résultats (FIUCC-68, §22 du cahier des charges) — "Results unavailable"
 * tant que status != 'published', puis décompte par poste avec pourcentage
 * et barre simple en CSS pur (pas de bibliothèque de graphiques — vanilla
 * uniquement, un pourcentage en largeur suffit ici).
 *
 * $groups reste vide tant que $published est faux — App\Controllers\
 * ElectionController::results() ne lance même pas la requête d'agrégation
 * avant publication.
 */
$election ??= [];
$published ??= false;
$groups ??= [];

$electionId = (int) $election['id'];
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/70 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= e('/elections/' . $electionId) ?>" class="hover:text-white transition-colors"><?= e($election['title']) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('elections.results.breadcrumb')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white"><?= e($election['title']) ?></h1>
        <?php if ($published && !empty($election['results_published_at'])): ?>
            <p class="text-body-md text-white/80 mt-2"><?= e(t('elections.results.published_note', ['date' => local_date($election['results_published_at'])])) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
    <div class="max-w-2xl mx-auto flex flex-col gap-8">

        <?php if (!$published): ?>
            <div class="bg-surface rounded-card border border-border p-8 flex flex-col items-center gap-3 text-center">
                <span class="h-14 w-14 rounded-full bg-muted/15 text-muted flex items-center justify-center">
                    <i class="fa-solid fa-lock text-2xl" aria-hidden="true"></i>
                </span>
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('elections.results.unavailable_title')) ?></h2>
                <p class="text-body-md text-muted"><?= e(t('elections.results.unavailable_message')) ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <?php $total = max(1, (int) $group['total']); ?>
                <div class="bg-surface rounded-card border border-border p-6 flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($group['position']['name']) ?></h2>
                        <span class="text-body-sm text-muted"><?= e(t('elections.results.total_votes', ['count' => (string) $group['total']])) ?></span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <?php foreach ($group['candidates'] as $row): ?>
                            <?php
                            $percent = (int) round(((int) $row['vote_count'] / $total) * 100);
                            $label = $row['candidate_id'] !== null ? $row['candidate_name'] : t('elections.results.blank_vote');
                            ?>
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-3 text-body-sm">
                                    <span class="text-ink text-label-md font-label-md"><?= e((string) $label) ?></span>
                                    <span class="text-muted"><?= e((string) $row['vote_count']) ?> (<?= e((string) $percent) ?>%)</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-white border border-border overflow-hidden">
                                    <div class="h-full rounded-full bg-primary" style="width: <?= e((string) $percent) ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="<?= e('/elections/' . $electionId) ?>" class="inline-flex items-center gap-2 text-primary font-label-lg font-label-lg hover:underline self-start">
            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
            <?= e(t('elections.results.back_to_election')) ?>
        </a>
    </div>
</div>
</div>
