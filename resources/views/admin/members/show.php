<?php
/**
 * Fiche de recensement complète d'un membre — admin (migration 018).
 * Seul écran où l'adresse, le passeport et le culte sont lisibles : ni
 * l'annuaire, ni le profil public, ni l'export CSV ne les exposent.
 */
$member ??= [];
$studyLevels ??= [];
$scanDeletionOn ??= null;
$retentionDays ??= 90;
$notice ??= '';
$isSelf ??= false;

$memberId = (int) $member['id'];
$selfUrl = '/admin/members/' . $memberId;
$scanUrl = $selfUrl . '/passport-scan';

// Retour des actions (validate/reject) : même table que la liste, la
// redirection revient ici grâce à return_to (MemberController::safeReturnTo()).
$noticeMap = [
    'validated'          => ['type' => 'success', 'message' => t('admin.members.notice.validated')],
    'rejected'           => ['type' => 'success', 'message' => t('admin.members.notice.rejected')],
    'reason_required'    => ['type' => 'error', 'message' => t('admin.members.notice.reason_required')],
    'self_action_denied' => ['type' => 'error', 'message' => t('admin.members.notice.self_action_denied')],
];
$canValidate = !$isSelf && in_array($member['status'], ['pending', 'rejected', 'suspended'], true);
$canReject = !$isSelf && $member['status'] === 'pending';

$optional = static fn (?string $value): string => ($value !== null && $value !== '') ? $value : '—';
$labelled = static fn (string $prefix, ?string $key): string => ($key !== null && $key !== '') ? t($prefix . $key) : '—';

$sections = [
    t('admin.members.show.section.identity') => [
        t('census.field.last_name')      => $optional($member['last_name'] ?? null),
        t('census.field.middle_name')    => $optional($member['middle_name'] ?? null),
        t('census.field.first_name')     => $optional($member['first_name'] ?? null),
        t('census.field.date_of_birth')  => ($member['date_of_birth'] ?? null) ? local_date($member['date_of_birth']) : '—',
        t('census.field.gender')         => $labelled('census.gender.', $member['gender'] ?? null),
        t('census.field.city_of_origin') => $optional($member['city_of_origin'] ?? null),
        t('census.field.community_role') => $labelled('census.community_role.', $member['community_role'] ?? null),
    ],
    t('admin.members.show.section.studies') => [
        t('census.field.student_id')  => $optional($member['student_id'] ?? null),
        t('census.field.faculty')     => $optional($member['faculty'] ?? null),
        t('census.field.department')  => $optional($member['department'] ?? null),
        t('census.field.year')        => $member['year'] !== null ? (string) ($studyLevels[$member['year']] ?? '—') : '—',
    ],
    t('admin.members.show.section.contact') => [
        t('census.field.email')     => $optional($member['email'] ?? null),
        t('census.field.phone')     => $optional($member['phone'] ?? null),
        t('census.field.whatsapp')  => $optional($member['whatsapp'] ?? null),
        t('census.field.instagram') => $optional($member['instagram'] ?? null),
    ],
    t('census.section.residence') => [
        t('census.field.address_street')    => $optional($member['address_street'] ?? null),
        t('census.field.address_building')  => $optional($member['address_building'] ?? null),
        t('census.field.address_apartment') => $optional($member['address_apartment'] ?? null),
        t('census.field.district')          => $labelled('census.district.', $member['district'] ?? null),
    ],
    t('census.section.passport') => [
        t('census.field.passport_number') => $optional($member['passport_number'] ?? null),
        t('census.field.passport_expiry') => ($member['passport_expiry'] ?? null) ? local_date($member['passport_expiry']) : '—',
    ],
    t('admin.members.show.section.sensitive') => [
        t('census.field.religion') => $labelled('census.religion.', $member['religion'] ?? null),
    ],
];
?>
<div class="flex flex-col gap-6">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <?= component('alert', $noticeMap[$notice]) ?>
    <?php endif; ?>

    <div class="flex flex-col gap-2">
        <a href="/admin/members" class="inline-flex w-fit items-center gap-2 text-label-md font-label-md text-muted hover:text-ink transition-colors">
            <?= icon('arrow-left') ?>
            <?= e(t('admin.members.show.back')) ?>
        </a>
        <div class="flex flex-wrap items-center gap-2 text-body-sm text-muted">
            <span><?= e($member['membership_number']) ?></span>
            <span aria-hidden="true">·</span>
            <span class="font-label-md text-ink"><?= e(t('admin.members.status.' . $member['status'])) ?></span>
            <?php if ($member['status'] === 'pending' && !empty($member['resubmitted_at'])): ?>
                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md">
                    <?= e(t('admin.members.show.resubmitted_on', ['date' => local_date($member['resubmitted_at'])])) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($member['status'] === 'rejected' && !empty($member['rejection_reason'])): ?>
            <p class="text-body-sm text-ink"><?= e(t('profile.status.reason', ['reason' => $member['rejection_reason']])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Décision : la photo du passeport à côté des boutons, pour comparer
         l'identité saisie à la pièce avant de valider ou refuser. -->
    <section class="bg-white rounded-card border border-border p-5 grid grid-cols-1 lg:grid-cols-[3fr_2fr] gap-6">
        <div class="flex flex-col gap-3">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('census.field.passport_scan')) ?></h2>
            <?php if (!empty($member['passport_scan'])): ?>
                <a href="<?= e($scanUrl) ?>" target="_blank" rel="noopener" class="block rounded-control border border-border bg-surface overflow-hidden">
                    <img src="<?= e($scanUrl) ?>" alt="<?= e(t('admin.members.show.passport_scan_alt', ['name' => $member['full_name']])) ?>" class="w-full max-h-[480px] object-contain">
                </a>
                <p class="text-body-sm text-muted">
                    <?= e(t('admin.members.show.passport_scan_open')) ?>
                    <?php if ($scanDeletionOn !== null): ?>
                        <?= e(t('admin.members.show.passport_scan_deleted_on', ['date' => local_date($scanDeletionOn)])) ?>
                    <?php else: ?>
                        <?= e(t('admin.members.show.passport_scan_retention', ['days' => $retentionDays])) ?>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.members.show.no_passport_scan_retention', ['days' => $retentionDays])) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex flex-col gap-4">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.members.show.decision')) ?></h2>
            <?php if ($isSelf): ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.members.notice.self_action_denied')) ?></p>
            <?php elseif (!$canValidate && !$canReject): ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.members.show.no_decision')) ?></p>
            <?php endif; ?>

            <?php if ($canValidate): ?>
                <form method="POST" action="<?= e($selfUrl . '/validate') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($selfUrl) ?>">
                    <button type="submit" class="w-full h-10 px-4 rounded-control bg-success text-white text-label-lg font-label-lg hover:bg-success/90 transition-colors inline-flex items-center justify-center gap-2">
                        <?= icon('check') ?>
                        <?= e(t('admin.members.action.validate')) ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canReject): ?>
                <form method="POST" action="<?= e($selfUrl . '/reject') ?>" class="flex flex-col gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= e($selfUrl) ?>">
                    <label for="reject-reason" class="text-label-md font-label-md text-ink"><?= e(t('admin.members.show.reject_reason_label')) ?></label>
                    <textarea id="reject-reason" name="reason" required rows="4"
                        placeholder="<?= e(t('admin.members.show.reject_reason_placeholder')) ?>"
                        class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                    <p class="text-body-sm text-muted"><?= e(t('admin.members.show.reject_reason_help')) ?></p>
                    <button type="submit" class="h-10 px-4 rounded-control border border-danger text-danger text-label-lg font-label-lg hover:bg-danger/10 transition-colors inline-flex items-center justify-center gap-2">
                        <?= icon('xmark') ?>
                        <?= e(t('admin.members.reason.confirm_reject')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach ($sections as $sectionTitle => $rows): ?>
            <section class="bg-white rounded-card border border-border p-5">
                <h2 class="font-label-lg text-label-lg text-ink mb-3"><?= e($sectionTitle) ?></h2>
                <dl class="flex flex-col divide-y divide-border">
                    <?php foreach ($rows as $label => $value): ?>
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1 py-2">
                            <dt class="text-body-sm text-muted"><?= e($label) ?></dt>
                            <dd class="text-body-sm text-ink sm:text-right break-words"><?= e($value) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
</div>
