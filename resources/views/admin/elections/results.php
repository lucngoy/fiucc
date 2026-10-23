<?php
/**
 * Aperçu admin du dépouillement (FIUCC-71) — voir le commentaire
 * d'Admin\ElectionController::results(). Accessible dès 'closed', pas
 * seulement 'published' : c'est précisément la fenêtre où le bureau doit
 * pouvoir vérifier avant de décider de publier.
 *
 * Lot 5 : une fois les résultats publiés, c'est d'ici que l'admin crée le
 * second tour (postes à égalité, art. 24) et enregistre les décisions de
 * la Commission (égalité persistante au second tour, leader retiré —
 * art. 24/30). Les choix permis viennent du contrôleur
 * (PositionDecision::eligibility()), jamais d'un calcul dans la vue.
 */
$election ??= [];
$groups ??= [];
$tiedPositions ??= [];
$secondRound ??= null;
$firstRound ??= null;
$decisionOptions ??= [];
$notice ??= '';

$electionId = (int) $election['id'];
$isPublished = $election['status'] === 'published';

$noticeMap = [
    'decision_recorded'             => ['type' => 'success', 'message' => t('admin.elections.decision.notice.recorded')],
    'decision_reason_required'      => ['type' => 'error',   'message' => t('admin.elections.decision.notice.reason_required')],
    'decision_invalid_candidate'    => ['type' => 'error',   'message' => t('admin.elections.decision.notice.invalid_candidate')],
    'decision_already_decided'      => ['type' => 'error',   'message' => t('admin.elections.decision.notice.already_decided')],
    'decision_not_allowed'          => ['type' => 'error',   'message' => t('admin.elections.decision.notice.not_allowed')],
    'second_round_title_invalid'    => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.title_invalid')],
    'second_round_dates_invalid'    => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.dates_invalid')],
    'second_round_after_handover'   => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.after_handover')],
    'second_round_not_allowed'      => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.not_allowed')],
    'second_round_already_exists'   => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.already_exists')],
    'second_round_no_tie'           => ['type' => 'error',   'message' => t('admin.elections.second_round.notice.no_tie')],
];

$inputClasses = 'h-[42px] w-full rounded-control border border-border px-3 text-body-md text-ink bg-white focus:outline-none focus:ring-2 focus:border-primary focus:ring-primary-light/50';
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections/<?= e((string) $electionId) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e($election['title']) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <?php if (!$isPublished): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.elections.results.preview_notice')]) ?>
    <?php endif; ?>

    <?php if ($firstRound !== null): ?>
        <p class="text-body-sm text-muted">
            <?= e(t('admin.elections.second_round.of_first_round')) ?>
            <a href="/admin/elections/<?= e((string) $firstRound['id']) ?>/results" class="text-primary font-label-md hover:underline"><?= e($firstRound['title']) ?></a>
        </p>
    <?php endif; ?>

    <div class="flex items-center justify-between gap-3">
        <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.elections.results.title')) ?></h1>
        <a href="/admin/elections/<?= e((string) $electionId) ?>/report"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-control bg-surface text-ink text-label-md font-label-md hover:bg-ink/10 transition-colors">
            <?= icon('file-lines') ?>
            <?= e(t('admin.elections.report.link')) ?>
        </a>
    </div>

    <?php if ($secondRound !== null): ?>
        <section id="second-round" class="bg-white rounded-card border border-border p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.second_round.title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.second_round.exists', ['title' => $secondRound['title']])) ?></p>
            </div>
            <a href="/admin/elections/<?= e((string) $secondRound['id']) ?>"
               class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0">
                <?= e(t('admin.elections.second_round.open_link')) ?>
                <?= icon('arrow-right') ?>
            </a>
        </section>
    <?php elseif ($tiedPositions !== []): ?>
        <section id="second-round" class="bg-white rounded-card border border-gold/60 p-5 md:p-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.second_round.title')) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.second_round.help')) ?></p>
            </div>
            <ul class="flex flex-col gap-1 text-body-sm text-ink">
                <?php foreach ($tiedPositions as $positionId => $entry): ?>
                    <?php
                    $names = [];
                    foreach ($groups[$positionId]['candidates'] as $row) {
                        if ($row['candidate_id'] !== null && in_array((int) $row['candidate_id'], $entry['candidate_ids'], true)) {
                            $names[] = (string) $row['candidate_name'];
                        }
                    }
                    ?>
                    <li class="flex gap-2"><?= icon('scale-balanced', 'text-primary mt-1') ?><span><strong><?= e($entry['position']['name']) ?></strong> — <?= e(implode(', ', $names)) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/second-round" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <?= csrf_field() ?>
                <div class="flex flex-col gap-1.5 md:col-span-3">
                    <label for="second-round-title" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.second_round.field.title')) ?></label>
                    <input type="text" id="second-round-title" name="title" required maxlength="200"
                        value="<?= e(t('admin.elections.second_round.default_title', ['title' => $election['title']])) ?>" class="<?= e($inputClasses) ?>">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="second-round-start" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.field.start_at')) ?></label>
                    <input type="datetime-local" id="second-round-start" name="start_at" required class="<?= e($inputClasses) ?>">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="second-round-end" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.field.end_at')) ?></label>
                    <input type="datetime-local" id="second-round-end" name="end_at" required class="<?= e($inputClasses) ?>">
                </div>
                <button type="submit" class="h-[42px] px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                    <?= e(t('admin.elections.second_round.submit')) ?>
                </button>
                <p class="text-body-sm text-muted md:col-span-3">
                    <?= e(t('admin.elections.second_round.dates_help', ['handover' => local_date($election['handover_date'])])) ?>
                </p>
            </form>
        </section>
    <?php endif; ?>

    <?php foreach ($decisionOptions as $positionId => $option): ?>
        <section id="position-<?= e((string) $positionId) ?>" class="bg-white rounded-card border border-gold/60 p-5 md:p-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.decision.title', ['position' => $groups[$positionId]['position']['name']])) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('admin.elections.decision.help.' . $option['kind'])) ?></p>
            </div>
            <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/positions/<?= e((string) $positionId) ?>/decision" class="flex flex-col gap-4">
                <?= csrf_field() ?>
                <fieldset class="flex flex-col gap-2">
                    <legend class="font-label-lg text-label-lg text-ink mb-1"><?= e(t('admin.elections.decision.field.candidate')) ?></legend>
                    <?php foreach ($option['candidates'] as $candidate): ?>
                        <label class="inline-flex items-center gap-2 text-body-md text-ink">
                            <input type="radio" name="candidate_id" value="<?= e((string) $candidate['id']) ?>" required class="h-4 w-4 border-border text-primary focus:ring-primary-light/50">
                            <?= e($candidate['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <div class="flex flex-col gap-1.5">
                    <label for="decision-reason-<?= e((string) $positionId) ?>" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.elections.decision.field.reason')) ?></label>
                    <textarea id="decision-reason-<?= e((string) $positionId) ?>" name="reason" rows="3" required maxlength="2000"
                        placeholder="<?= e(t('admin.elections.decision.field.reason_placeholder')) ?>"
                        class="<?= e($inputClasses) ?> !h-auto py-2"></textarea>
                    <p class="text-body-sm text-muted"><?= e(t('admin.elections.decision.final_help')) ?></p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                        <?= e(t('admin.elections.decision.submit')) ?>
                    </button>
                </div>
            </form>
        </section>
    <?php endforeach; ?>

    <?= component('election_results_body', ['groups' => $groups, 'cardBg' => 'bg-white', 'isSecondRound' => $election['first_round_id'] !== null, 'showDecisionReason' => true]) ?>
</div>
