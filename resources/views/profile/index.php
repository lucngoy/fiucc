<?php
/**
 * Page profil du membre (FIUCC-37) — affichage seul, sa propre fiche.
 */
$notice ??= null;

$statusCopy = [
    'validated' => ['type' => 'success', 'message' => t('profile.status.validated')],
    'pending'   => ['type' => 'info', 'message' => t('profile.status.pending')],
    'rejected'  => ['type' => 'error', 'message' => t('profile.status.rejected')],
    'suspended' => ['type' => 'error', 'message' => t('profile.status.suspended')],
];
$status = $statusCopy[$member['status']] ?? $statusCopy['pending'];
$statusReason = $member['status'] === 'rejected'
    ? $member['rejection_reason']
    : ($member['status'] === 'suspended' ? $member['suspension_reason'] : null);

$initials = '';
foreach (preg_split('/\s+/', trim($member['full_name'])) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2);

$studyLevel = $studyLevels[(int) $member['year']] ?? null;

$details = array_filter([
    t('profile.detail.student_id')     => $member['student_id'],
    t('profile.detail.faculty')        => $member['faculty'],
    t('profile.detail.department')     => $member['department'],
    t('profile.detail.year')           => $studyLevel,
    t('profile.detail.date_of_birth')  => local_date($member['date_of_birth']),
    t('profile.detail.city_of_origin') => $member['city_of_origin'],
    t('profile.detail.phone')          => $member['phone'],
    t('profile.detail.whatsapp')       => $member['whatsapp'],
    t('profile.detail.instagram')      => $member['instagram'] !== null && $member['instagram'] !== '' ? '@' . ltrim($member['instagram'], '@') : null,
], static fn ($value) => $value !== null && $value !== '');

if ($member['status_type'] !== 'student') {
    $details = array_merge($details, array_filter([
        t('profile.detail.profession')      => $member['profession'],
        t('profile.detail.company')         => $member['company'],
        t('profile.detail.city')            => $member['city'],
        t('profile.detail.country')         => $member['country'],
        t('profile.detail.arrival_year')    => $member['arrival_year'],
        t('profile.detail.graduation_year') => $member['graduation_year'],
    ], static fn ($value) => $value !== null && $value !== ''));
}
?>
<div class="max-w-3xl mx-auto px-4 py-12 flex flex-col gap-6">
    <?php if ($notice !== null): ?>
        <?= component('alert', ['type' => 'info', 'message' => $notice]) ?>
    <?php endif; ?>

    <div class="bg-white rounded-card border border-border shadow-card p-6 flex items-center gap-5">
        <?php if (!empty($member['photo'])): ?>
            <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover border border-border">
        <?php else: ?>
            <span class="h-16 w-16 shrink-0 rounded-full bg-primary/15 text-primary flex items-center justify-center font-headline-sm text-headline-sm">
                <?= e($initials) ?>
            </span>
        <?php endif; ?>
        <div class="min-w-0 flex-1">
            <h1 class="font-headline-lg text-headline-lg text-ink truncate"><?= e($member['full_name']) ?></h1>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <?= component('status_badge', ['status' => $member['status_type']]) ?>
                <span class="text-body-sm text-muted"><?= e($member['membership_number']) ?></span>
            </div>
        </div>
        <?= component('button', ['label' => t('profile.edit_profile'), 'variant' => 'secondary', 'href' => '/profile/edit']) ?>
    </div>

    <?= component('alert', ['type' => $status['type'], 'message' => $status['message']]) ?>

    <?php if ($statusReason !== null && $statusReason !== ''): ?>
        <div class="-mt-3 pl-4 text-body-sm text-muted"><?= e(t('profile.status.reason', ['reason' => $statusReason])) ?></div>
    <?php endif; ?>

    <?php if ($details !== []): ?>
        <div class="bg-white rounded-card border border-border shadow-card p-6">
            <h2 class="font-headline-sm text-headline-sm text-ink mb-4"><?= e(t('profile.info_title')) ?></h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <?php foreach ($details as $label => $value): ?>
                    <div>
                        <dt class="text-label-md font-label-md text-muted"><?= e($label) ?></dt>
                        <dd class="text-body-md text-ink"><?= e((string) $value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    <?php endif; ?>

    <p class="text-body-sm text-muted">
        <?= e(t('profile.member_since', ['date' => local_date($member['created_at'])])) ?>
    </p>
</div>
