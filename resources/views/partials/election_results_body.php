<?php
/**
 * Corps du dépouillement (FIUCC-71, §22 du cahier des charges) — partagé
 * entre la page publique (resources/views/elections/results.php, gardée
 * par status = 'published') et l'aperçu admin (resources/views/admin/
 * elections/results.php, gardé par status IN ('closed', 'published') —
 * le bureau doit pouvoir vérifier avant de publier, décision explicite
 * conversation FIUCC-68/70).
 *
 * @param array $groups Vote::groupResultsByPosition() — position,
 *        candidates, total, expressed (blancs exclus), blank, outcome
 *        (Vote::positionOutcome() : winner/tie/withdrawn/none).
 * @param string $cardBg Fond de la carte — "bg-surface" (défaut, page
 *        publique : bande bg-white autour, voir elections/results.php) ou
 *        "bg-white" (aperçu admin : body bg-surface du layout admin, voir
 *        layouts/admin.php — une carte bg-surface s'y fondrait dans le
 *        fond, invisible). Le repli de la piste de progression est
 *        toujours la couleur opposée à la carte, pas une valeur fixe :
 *        elle doit contraster avec SA carte, pas avec la page.
 * @param bool $isSecondRound Lot 5 : au second tour, une égalité renvoie
 *        à la Commission électorale, plus à un nouveau tour.
 * @param bool $showDecisionReason Lot 5 : référence du procès-verbal de la
 *        Commission — aperçu admin et rapport imprimable (archive), pas la
 *        page publique.
 *
 * Décision de la Commission (groups[...]['decision'], lot 5) : affichée à
 * côté des chiffres du vote, jamais à leur place — "Désigné(e) par la
 * Commission", distinct de "Élu(e)".
 */
$groups ??= [];
$cardBg ??= 'bg-surface';
$isSecondRound ??= false;
$showDecisionReason ??= false;
$trackBg = $cardBg === 'bg-white' ? 'bg-surface' : 'bg-white';
?>
<?php foreach ($groups as $group): ?>
    <?php
    // Pourcentages sur les suffrages EXPRIMÉS (blancs exclus, décision du
    // bureau 2026-10-10) ; le blanc est affiché à part, sans pourcentage.
    $expressed = max(1, (int) $group['expressed']);
    $outcome = $group['outcome'] ?? ['status' => 'none', 'candidate_ids' => []];
    $namesOf = static function (array $ids) use ($group): string {
        $names = [];
        foreach ($group['candidates'] as $row) {
            if ($row['candidate_id'] !== null && in_array((int) $row['candidate_id'], $ids, true)) {
                $names[] = (string) $row['candidate_name'];
            }
        }

        return implode(', ', $names);
    };
    $candidateRows = array_filter($group['candidates'], static fn (array $row): bool => $row['candidate_id'] !== null);
    $decision = $group['decision'] ?? null;
    ?>
    <div id="result-position-<?= e((string) $group['position']['id']) ?>" class="<?= e($cardBg) ?> rounded-card border border-border p-6 flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($group['position']['name']) ?></h2>
            <span class="text-body-sm text-muted">
                <?= e(t('elections.results.expressed_votes', ['count' => (string) $group['expressed']])) ?>
                <?php if ((int) $group['blank'] > 0): ?>
                    · <?= e(t('elections.results.blank_count', ['count' => (string) $group['blank']])) ?>
                <?php endif; ?>
            </span>
        </div>

        <?php if ($decision !== null): ?>
            <?= component('alert', ['type' => 'info', 'message' => t('elections.results.commission_decision', [
                'name' => (string) $decision['candidate_name'],
                'date' => local_date((string) $decision['decided_at']),
            ])]) ?>
            <?php if ($showDecisionReason): ?>
                <p class="text-body-sm text-muted whitespace-pre-line"><?= e(t('elections.results.commission_reference', ['reference' => (string) $decision['reason']])) ?></p>
            <?php endif; ?>
        <?php elseif ($outcome['status'] === 'tie'): ?>
            <!-- Égalité en tête : jamais de départage implicite (Vote::positionOutcome()). -->
            <?= component('alert', ['type' => 'warning', 'message' => t($isSecondRound ? 'elections.results.tie_second_round' : 'elections.results.tie', ['names' => $namesOf($outcome['candidate_ids'])])]) ?>
        <?php elseif ($outcome['status'] === 'withdrawn'): ?>
            <?= component('alert', ['type' => 'warning', 'message' => t('elections.results.withdrawn_leader', ['name' => $namesOf($outcome['candidate_ids'])])]) ?>
        <?php endif; ?>

        <div class="flex flex-col gap-3">
            <?php foreach ($candidateRows as $row): ?>
                <?php
                $percent = (int) round(((int) $row['vote_count'] / $expressed) * 100);
                $isWinner = $outcome['status'] === 'winner' && in_array((int) $row['candidate_id'], $outcome['candidate_ids'], true);
                $isDesignated = $decision !== null && (int) $decision['candidate_id'] === (int) $row['candidate_id'];
                ?>
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between gap-3 text-body-sm">
                        <span class="flex flex-wrap items-center gap-2 text-ink text-label-md font-label-md">
                            <?= e((string) $row['candidate_name']) ?>
                            <?php if ($isWinner): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-success/[4%] text-success text-label-md font-label-md"><?= e(t('elections.results.winner')) ?></span>
                            <?php elseif ($isDesignated): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-primary/10 text-primary text-label-md font-label-md"><?= e(t('elections.results.designated')) ?></span>
                            <?php elseif (($row['candidate_status'] ?? null) === 'withdrawn'): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-surface border border-border text-muted text-label-md font-label-md"><?= e(t('elections.results.withdrawn')) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="text-muted whitespace-nowrap"><?= e((string) $row['vote_count']) ?> (<?= e((string) $percent) ?>%)</span>
                    </div>
                    <div class="h-2.5 w-full rounded-full <?= e($trackBg) ?> border border-border overflow-hidden">
                        <div class="h-full rounded-full bg-primary" style="width: <?= e((string) $percent) ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ((int) $group['blank'] > 0): ?>
                <div class="flex items-center justify-between gap-3 text-body-sm pt-1 border-t border-border">
                    <span class="text-muted"><?= e(t('elections.results.blank_vote')) ?></span>
                    <span class="text-muted"><?= e(t('elections.results.blank_not_counted', ['count' => (string) $group['blank']])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
