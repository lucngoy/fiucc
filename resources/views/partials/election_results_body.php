<?php
/**
 * Corps du dépouillement (FIUCC-71, §22 du cahier des charges) — partagé
 * entre la page publique (resources/views/elections/results.php, gardée
 * par status = 'published') et l'aperçu admin (resources/views/admin/
 * elections/results.php, gardé par status IN ('closed', 'published') —
 * le bureau doit pouvoir vérifier avant de publier, décision explicite
 * conversation FIUCC-68/70).
 *
 * @param array $groups Vote::resultsForElection() groupé par poste —
 *        voir App\Controllers\ElectionController::results() pour la forme
 *        exacte (position/candidates/total).
 * @param string $cardBg Fond de la carte — "bg-surface" (défaut, page
 *        publique : bande bg-white autour, voir elections/results.php) ou
 *        "bg-white" (aperçu admin : body bg-surface du layout admin, voir
 *        layouts/admin.php — une carte bg-surface s'y fondrait dans le
 *        fond, invisible). Le repli de la piste de progression est
 *        toujours la couleur opposée à la carte, pas une valeur fixe :
 *        elle doit contraster avec SA carte, pas avec la page.
 */
$groups ??= [];
$cardBg ??= 'bg-surface';
$trackBg = $cardBg === 'bg-white' ? 'bg-surface' : 'bg-white';
?>
<?php foreach ($groups as $group): ?>
    <?php $total = max(1, (int) $group['total']); ?>
    <div class="<?= e($cardBg) ?> rounded-card border border-border p-6 flex flex-col gap-4">
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
                    <div class="h-2.5 w-full rounded-full <?= e($trackBg) ?> border border-border overflow-hidden">
                        <div class="h-full rounded-full bg-primary" style="width: <?= e((string) $percent) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
