<?php
/**
 * Page profil du membre (FIUCC-37) — affichage seul, sa propre fiche.
 */
$notice ??= null;

$statusCopy = [
    'validated' => ['type' => 'success', 'message' => 'Your membership is validated. Welcome to the community!'],
    'pending'   => ['type' => 'info', 'message' => 'Your registration is awaiting review by a community administrator. We will email you once it has been processed.'],
    'rejected'  => ['type' => 'error', 'message' => 'Your registration was not approved.'],
    'suspended' => ['type' => 'error', 'message' => 'Your account has been suspended.'],
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

$formatDate = static fn (?string $value, string $format = 'F j, Y'): ?string =>
    $value !== null ? date($format, (int) strtotime($value)) : null;

$details = array_filter([
    'Student ID'      => $member['student_id'],
    'Faculty'         => $member['faculty'],
    'Department'      => $member['department'],
    'Year'            => $studyLevel,
    'Date of birth'   => $formatDate($member['date_of_birth']),
    'City of origin'  => $member['city_of_origin'],
    'Phone'           => $member['phone'],
    'WhatsApp'        => $member['whatsapp'],
    'Instagram'       => $member['instagram'] !== null && $member['instagram'] !== '' ? '@' . ltrim($member['instagram'], '@') : null,
], static fn ($value) => $value !== null && $value !== '');

if ($member['status_type'] !== 'student') {
    $details = array_merge($details, array_filter([
        'Profession'       => $member['profession'],
        'Company'          => $member['company'],
        'City'             => $member['city'],
        'Country'          => $member['country'],
        'Arrival year'     => $member['arrival_year'],
        'Graduation year'  => $member['graduation_year'],
    ], static fn ($value) => $value !== null && $value !== ''));
}
?>
<div class="max-w-3xl mx-auto px-4 py-12 flex flex-col gap-6">
    <?php if ($notice !== null): ?>
        <?= component('alert', ['type' => 'info', 'message' => $notice]) ?>
    <?php endif; ?>

    <div class="bg-white rounded-card border border-border shadow-card p-6 flex items-center gap-5">
        <span class="h-16 w-16 shrink-0 rounded-full bg-primary/15 text-primary flex items-center justify-center font-headline-sm text-headline-sm">
            <?= e($initials) ?>
        </span>
        <div class="min-w-0">
            <h1 class="font-headline-lg text-headline-lg text-ink truncate"><?= e($member['full_name']) ?></h1>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <?= component('status_badge', ['status' => $member['status_type']]) ?>
                <span class="text-body-sm text-muted"><?= e($member['membership_number']) ?></span>
            </div>
        </div>
    </div>

    <?= component('alert', ['type' => $status['type'], 'message' => $status['message']]) ?>

    <?php if ($statusReason !== null && $statusReason !== ''): ?>
        <div class="-mt-3 pl-4 text-body-sm text-muted">Reason: <?= e($statusReason) ?></div>
    <?php endif; ?>

    <?php if ($details !== []): ?>
        <div class="bg-white rounded-card border border-border shadow-card p-6">
            <h2 class="font-headline-sm text-headline-sm text-ink mb-4">Your information</h2>
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
        Member since <?= e($formatDate($member['created_at']) ?? '') ?>
    </p>
</div>
