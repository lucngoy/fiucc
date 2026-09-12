<?php
/**
 * Jetons de vote — génération et envoi (FIUCC-64, sections 19-20 du cahier
 * des charges). Le ticket le plus sensible du projet — voir CLAUDE.md
 * règle 3 pour le raisonnement complet sur la taille et la forme du jeton.
 *
 * Jamais de code en clair affiché ici : App\Models\VotingToken ne renvoie
 * le code qu'à l'instant précis de l'émission/régénération, directement à
 * App\Services\VoteTokenNotifier pour l'email — cet écran ne montre que des
 * états dérivés (émis/envoyé/voté/tentatives), jamais le secret lui-même.
 *
 * Avertissement de régénération affiché AVANT confirmation (décision
 * explicite conversation FIUCC-64) : régénérer casse l'ancien lien
 * immédiatement, même si le membre le retrouve plus tard dans ses spams.
 */
$election ??= [];
$tokens ??= [];
$canManage ??= false;
$maxAttempts ??= 5;
$notice ??= '';

$electionId = (int) $election['id'];
$now = time();

$statusFor = static function (array $row) use ($maxAttempts, $now): string {
    if ($row['used_at'] !== null) {
        return 'voted';
    }

    if (!$row['has_token']) {
        return 'not_issued';
    }

    if ((int) $row['attempts'] >= $maxAttempts) {
        return 'locked';
    }

    if ($row['sent_at'] === null) {
        return 'send_failed';
    }

    if (strtotime((string) $row['expires_at']) < $now) {
        return 'expired';
    }

    return 'sent';
};

$statusBadge = static function (string $status): string {
    $variants = [
        'not_issued'  => 'bg-muted/[1%] text-muted',
        'send_failed' => 'bg-danger/10 text-danger',
        'sent'        => 'bg-success/[4%] text-success',
        'expired'     => 'bg-gold/20 text-ink',
        'locked'      => 'bg-danger/10 text-danger',
        'voted'       => 'bg-primary/10 text-primary',
    ];
    $classes = $variants[$status] ?? $variants['not_issued'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full ' . $classes . ' text-label-md font-label-md">'
        . e(t('admin.voting_tokens.status.' . $status)) . '</span>';
};

$noticeMap = [
    'batch_sent'  => ['type' => 'success', 'message' => t('admin.voting_tokens.notice.batch_sent', [
        'issued' => (string) ($_GET['issued'] ?? '0'),
        'resent' => (string) ($_GET['resent'] ?? '0'),
        'failed' => (string) ($_GET['failed'] ?? '0'),
    ])],
    'not_open'              => ['type' => 'error', 'message' => t('admin.voting_tokens.notice.not_open')],
    'cannot_regenerate'     => ['type' => 'error', 'message' => t('admin.voting_tokens.notice.cannot_regenerate')],
    'regenerated'           => ['type' => 'success', 'message' => t('admin.voting_tokens.notice.regenerated')],
    'regenerated_send_failed' => ['type' => 'error', 'message' => t('admin.voting_tokens.notice.regenerated_send_failed')],
    'not_found'             => ['type' => 'error', 'message' => t('admin.voting_tokens.notice.not_found')],
];
?>
<div class="flex flex-col gap-6">
    <a href="/admin/elections/<?= e((string) $electionId) ?>" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
        <?= icon('arrow-left') ?>
        <?= e($election['title']) ?>
    </a>

    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <?php if (!$canManage): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('admin.voting_tokens.notice.not_open')]) ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <h1 class="font-headline-md text-headline-md text-ink"><?= e(t('admin.voting_tokens.title')) ?></h1>
        <?php if ($canManage): ?>
            <div class="flex flex-col items-start md:items-end gap-1.5">
                <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/tokens/send">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                        <?= icon('paper-plane') ?>
                        <?= e(t('admin.voting_tokens.action.send')) ?>
                    </button>
                </form>
                <p class="text-body-sm text-muted max-w-sm md:text-right"><?= e(t('admin.voting_tokens.action.send_help')) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-card border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-primary text-white">
                <tr>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.voting_tokens.column.member')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.voting_tokens.column.status')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase"><?= e(t('admin.voting_tokens.column.attempts')) ?></th>
                    <th class="p-3 text-label-caps font-label-caps uppercase text-right"><?= e(t('admin.voting_tokens.column.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tokens === []): ?>
                    <tr>
                        <td colspan="4" class="p-6">
                            <?= component('alert', ['type' => 'info', 'message' => t('admin.voting_tokens.empty')]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tokens as $row): ?>
                        <?php
                        $memberId = (int) $row['member_id'];
                        $status = $statusFor($row);
                        $canRegenerate = $canManage && $status !== 'voted';
                        ?>
                        <tr class="border-b border-border last:border-0 align-top">
                            <td class="p-3 font-label-lg text-label-lg text-ink"><?= e($row['full_name']) ?></td>
                            <td class="p-3"><?= $statusBadge($status) ?></td>
                            <td class="p-3 text-body-sm text-ink"><?= e((string) $row['attempts']) ?> / <?= e((string) $maxAttempts) ?></td>
                            <td class="p-3">
                                <?php if ($canRegenerate): ?>
                                    <div class="flex justify-end">
                                        <button type="button" data-open-dialog="regen-dialog-<?= e((string) $memberId) ?>"
                                            class="h-9 px-3 rounded-control bg-surface text-primary hover:bg-primary/15 flex items-center gap-2 text-label-md font-label-md transition-colors">
                                            <?= icon('rotate') ?>
                                            <?= e(t('admin.voting_tokens.action.regenerate')) ?>
                                        </button>
                                    </div>
                                    <dialog id="regen-dialog-<?= e((string) $memberId) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-sm backdrop:bg-ink/40">
                                        <form method="POST" action="/admin/elections/<?= e((string) $electionId) ?>/tokens/<?= e((string) $memberId) ?>/regenerate" class="flex flex-col gap-2 p-4 text-left">
                                            <?= csrf_field() ?>
                                            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.voting_tokens.regenerate.confirm_title')) ?></h2>
                                            <p class="text-body-sm text-muted"><?= e(t('admin.voting_tokens.regenerate.confirm_message', ['name' => $row['full_name']])) ?></p>
                                            <p class="text-body-sm font-label-md text-danger"><?= e(t('admin.voting_tokens.regenerate.confirm_warning')) ?></p>
                                            <div class="flex flex-col gap-1.5 mt-1">
                                                <label for="regen-reason-<?= e((string) $memberId) ?>" class="text-label-md font-label-md text-muted"><?= e(t('admin.voting_tokens.regenerate.reason_label')) ?></label>
                                                <textarea id="regen-reason-<?= e((string) $memberId) ?>" name="reason" rows="2"
                                                    class="rounded-control border border-border px-3 py-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                                            </div>
                                            <div class="flex justify-end gap-2 mt-1">
                                                <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('admin.voting_tokens.action.cancel')) ?></button>
                                                <button type="submit" class="h-9 px-4 rounded-control bg-danger text-white text-label-md font-label-md hover:bg-danger/90 transition-colors"><?= e(t('admin.voting_tokens.regenerate.confirm_button')) ?></button>
                                            </div>
                                        </form>
                                    </dialog>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
