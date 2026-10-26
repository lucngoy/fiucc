<?php
/**
 * Fiche d'une demande d'aide : message du membre, ses coordonnées, et le
 * traitement (statut + réponse envoyée par email si elle change).
 */
$request ??= [];
$error ??= null;
$notice ??= '';
$responseMaxLength ??= 3000;

$statusOptions = [];
foreach (\App\Models\HelpRequest::STATUSES as $statusKey) {
    $statusOptions[$statusKey] = t('help.status.' . $statusKey);
}

$noticeMap = [
    'saved'   => t('admin.help.notice.saved'),
    'replied' => t('admin.help.notice.replied'),
];

$whatsappHref = ($request['whatsapp'] ?? '') !== ''
    ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string) $request['whatsapp'])
    : null;
?>
<div class="flex flex-col gap-6">
    <a href="/admin/help" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.help.back')) ?>
    </a>

    <?php if (isset($noticeMap[$notice])): ?>
        <?= component('alert', ['type' => 'success', 'message' => $noticeMap[$notice]]) ?>
    <?php elseif ($error !== null): ?>
        <?= component('alert', ['type' => 'error', 'message' => $error]) ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-white rounded-card border border-border p-6 flex flex-col gap-4">
            <div>
                <p class="text-label-caps font-label-caps uppercase text-muted">
                    <?= e(\App\Models\HelpRequest::reference($request)) ?> · <?= e(t('help.category.' . $request['category'])) ?>
                </p>
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($request['subject']) ?></h2>
                <p class="text-body-sm text-muted"><?= e(t('help.mine.sent_on', ['date' => local_date($request['created_at'])])) ?></p>
            </div>
            <p class="text-body-md text-ink leading-relaxed"><?= nl2br(e($request['message'])) ?></p>
        </section>

        <aside class="bg-white rounded-card border border-border p-6 flex flex-col gap-3">
            <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.help.member.title')) ?></h2>
            <p class="text-body-md text-ink"><?= e($request['full_name']) ?></p>
            <?php if (($request['membership_number'] ?? '') !== ''): ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.help.member.number', ['number' => $request['membership_number']])) ?></p>
            <?php endif; ?>
            <ul class="flex flex-col gap-2 text-body-sm">
                <li class="flex items-center gap-2 min-w-0"><?= icon('envelope', 'text-muted shrink-0') ?><a href="<?= e('mailto:' . $request['email']) ?>" class="text-primary hover:underline truncate"><?= e($request['email']) ?></a></li>
                <?php if ($whatsappHref !== null): ?>
                    <li class="flex items-center gap-2"><?= icon('phone', 'text-muted shrink-0') ?><a href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline"><?= e(t('admin.help.member.whatsapp', ['number' => $request['whatsapp']])) ?></a></li>
                <?php endif; ?>
                <?php if (($request['phone'] ?? '') !== '' && $request['phone'] !== $request['whatsapp']): ?>
                    <li class="flex items-center gap-2"><?= icon('phone', 'text-muted shrink-0') ?><a href="<?= e(\App\Services\ContactSettings::telHref((string) $request['phone'])) ?>" class="text-primary hover:underline"><?= e($request['phone']) ?></a></li>
                <?php endif; ?>
            </ul>
            <a href="<?= e('/admin/members/' . (int) $request['member_id']) ?>" class="mt-2 inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline">
                <?= icon('user') ?>
                <?= e(t('admin.help.member.profile')) ?>
            </a>
        </aside>
    </div>

    <form method="POST" action="<?= e('/admin/help/' . (int) $request['id']) ?>" class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
        <?= csrf_field() ?>
        <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.help.handling.title')) ?></h2>
        <div class="max-w-xs">
            <?= component('select_field', [
                'name' => 'status', 'label' => t('admin.help.handling.status'), 'required' => true,
                'options' => $statusOptions, 'value' => (string) $request['status'],
            ]) ?>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="field-response" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.help.handling.response')) ?></label>
            <textarea id="field-response" name="response" rows="6" maxlength="<?= e((string) $responseMaxLength) ?>"
                class="w-full rounded-control border border-border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 focus:border-primary focus:ring-primary-light/50"><?= e((string) ($request['response'] ?? '')) ?></textarea>
            <p class="text-body-sm text-muted"><?= e(t('admin.help.handling.response_help')) ?></p>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.help.handling.save')) ?></button>
        </div>
    </form>
</div>
