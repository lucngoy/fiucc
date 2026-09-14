<?php
/**
 * Mentorat, côté étudiant (FIUCC-88, cahier — "un étudiant peut demander
 * un conseil ou une mise en relation"). Chaque mentor vient déjà rédigé
 * de Member::mentorList() (is_mentor/is_public/validated/alumni filtrent
 * l'inclusion elle-même) — cette vue n'a aucun contrôle de
 * confidentialité à faire, seulement à afficher ce qu'elle reçoit, même
 * discipline que directory/index.php (FIUCC-82).
 *
 * "Mes demandes envoyées" : le contact du mentor (email/téléphone) ne
 * vient déjà rédigé que si Mentorship::sentByStudent() a jugé la demande
 * acceptée ET le canal activé — cette vue l'affiche tel quel aussi.
 */
$members ??= [];
$filters ??= ['search' => ''];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;
$sentRequests ??= [];
$notice ??= '';

$sparseThreshold = 5;

$noticeMap = [
    'request_sent'    => ['type' => 'success', 'message' => t('mentors.notice.request_sent')],
    'request_failed'  => ['type' => 'error',   'message' => t('mentors.notice.request_failed')],
    'message_required' => ['type' => 'error',  'message' => t('mentors.notice.message_required')],
];

$activeFilterQuery = array_filter(['search' => $filters['search']], static fn ($value) => $value !== '');

$paginationBase = '/mentors?' . ($activeFilterQuery !== []
    ? http_build_query($activeFilterQuery) . '&'
    : '') . 'page={page}';

$statusBadge = static function (string $status): string {
    $variants = [
        'pending'   => ['bg' => 'bg-gold/20', 'text' => 'text-ink'],
        'accepted'  => ['bg' => 'bg-success/[4%]', 'text' => 'text-success'],
        'declined'  => ['bg' => 'bg-danger/10', 'text' => 'text-danger'],
        'completed' => ['bg' => 'bg-muted/[1%]', 'text' => 'text-muted'],
    ];
    $variant = $variants[$status] ?? $variants['pending'];

    return '<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md ' . $variant['bg'] . ' ' . $variant['text'] . '">'
        . e(t('mentors.status.' . $status)) . '</span>';
};

// Un id déjà présent parmi les demandes envoyées (pending/accepted) :
// bouton désactivé plutôt qu'une deuxième demande qui échouerait de
// toute façon côté serveur (Mentorship::create()) — l'utilisateur voit
// pourquoi tout de suite. Comparé par mentor_id, jamais par nom (deux
// mentors homonymes ne doivent pas se masquer l'un l'autre).
$activeSentMentorIds = array_map(
    static fn ($r) => (int) $r['mentor_id'],
    array_filter($sentRequests, static fn ($r) => in_array($r['status'], ['pending', 'accepted'], true))
);
?>
<div class="relative w-full overflow-hidden bg-primary">
    <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('mentors.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('mentors.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('mentors.subtitle')) ?></p>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <?php if ($notice !== '' && isset($noticeMap[$notice])): ?>
        <div class="mb-6">
            <?= component('alert', $noticeMap[$notice]) ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="/mentors" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
        <label for="mentors-search" class="sr-only"><?= e(t('mentors.search_label')) ?></label>
        <input type="search" id="mentors-search" name="search" value="<?= e($filters['search']) ?>"
            placeholder="<?= e(t('mentors.search_placeholder')) ?>"
            class="h-10 min-w-[220px] rounded-control border border-border px-3 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50">
        <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('mentors.filter.apply')) ?></button>
        <?php if ($activeFilterQuery !== []): ?>
            <a href="/mentors" class="h-10 px-2 flex items-center text-label-lg font-label-lg text-muted hover:text-ink transition-colors"><?= e(t('mentors.filter.reset')) ?></a>
        <?php endif; ?>
    </form>

    <p class="text-body-md text-muted mb-6"><?= e(t('mentors.total', ['count' => $total])) ?></p>

    <?php if ($members === []): ?>
        <div class="max-w-layout mx-auto">
            <?php if ($activeFilterQuery !== []): ?>
                <?= component('alert', ['type' => 'info', 'message' => t('mentors.empty.filtered')]) ?>
            <?php else: ?>
                <?= component('alert', ['type' => 'info', 'message' => t('mentors.empty.default')]) ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php if ($total < $sparseThreshold): ?>
            <div class="mb-6">
                <?= component('alert', ['type' => 'info', 'message' => t('mentors.sparse_notice')]) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($members as $mentor): ?>
                <?php $alreadyRequested = in_array((int) $mentor['id'], $activeSentMentorIds, true); ?>
                <div class="bg-surface rounded-card border border-border overflow-hidden p-4 flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($mentor['photo'])): ?>
                            <img src="<?= e('/uploads/' . $mentor['photo']) ?>" alt="" class="h-14 w-14 rounded-full object-cover shrink-0">
                        <?php else: ?>
                            <span class="h-14 w-14 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-label-lg text-label-lg shrink-0">
                                <?= e(initials($mentor['full_name'])) ?>
                            </span>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <h3 class="font-label-lg text-label-lg text-ink truncate">
                                <a href="<?= e('/directory/' . $mentor['id']) ?>" class="hover:text-primary hover:underline"><?= e($mentor['full_name']) ?></a>
                            </h3>
                            <?php if (!empty($mentor['mentor_field'])): ?>
                                <p class="text-label-caps font-label-caps text-muted truncate"><?= e($mentor['mentor_field']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($mentor['mentor_availability'])): ?>
                        <p class="text-body-sm text-muted"><?= e(t('mentors.card.availability', ['availability' => $mentor['mentor_availability']])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($mentor['mentor_bio'])): ?>
                        <p class="text-body-sm text-ink line-clamp-3"><?= e($mentor['mentor_bio']) ?></p>
                    <?php endif; ?>

                    <?php if ($alreadyRequested): ?>
                        <span class="mt-auto text-body-sm text-muted"><?= e(t('mentors.card.already_requested')) ?></span>
                    <?php else: ?>
                        <button type="button" data-open-dialog="request-dialog-<?= e((string) $mentor['id']) ?>"
                            class="mt-auto h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors self-start">
                            <?= e(t('mentors.card.request_button')) ?>
                        </button>
                        <dialog id="request-dialog-<?= e((string) $mentor['id']) ?>" class="rounded-card border border-border shadow-card p-0 w-[90vw] max-w-md backdrop:bg-ink/40">
                            <form method="POST" action="<?= e('/mentors/' . $mentor['id'] . '/request') ?>" class="flex flex-col gap-3 p-4 text-left">
                                <?= csrf_field() ?>
                                <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('mentors.dialog.title', ['name' => $mentor['full_name']])) ?></h2>
                                <?= component('form_field', [
                                    'name' => 'topic', 'label' => t('mentors.dialog.topic'),
                                    'placeholder' => t('mentors.dialog.topic_placeholder'),
                                ]) ?>
                                <div class="flex flex-col gap-1.5">
                                    <label for="message-<?= e((string) $mentor['id']) ?>" class="font-label-lg text-label-lg text-ink"><?= e(t('mentors.dialog.message')) ?> <span class="text-danger">*</span></label>
                                    <textarea id="message-<?= e((string) $mentor['id']) ?>" name="message" required rows="4"
                                        placeholder="<?= e(t('mentors.dialog.message_placeholder')) ?>"
                                        class="rounded-control border border-border p-2 text-body-sm text-ink focus:outline-none focus:ring-2 focus:ring-primary-light/50"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 mt-1">
                                    <button type="button" data-close-dialog class="h-9 px-3 rounded-control text-ink text-label-md font-label-md hover:bg-surface transition-colors"><?= e(t('mentors.dialog.cancel')) ?></button>
                                    <button type="submit" class="h-9 px-4 rounded-control bg-primary text-white text-label-md font-label-md hover:bg-primary-dark transition-colors"><?= e(t('mentors.dialog.submit')) ?></button>
                                </div>
                            </form>
                        </dialog>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => $paginationBase,
            ]) ?>
        </div>
    <?php endif; ?>

    <?php if ($sentRequests !== []): ?>
        <div class="mt-14">
            <h2 class="font-headline-sm text-headline-sm text-ink mb-4"><?= e(t('mentors.sent.title')) ?></h2>
            <div class="flex flex-col gap-3">
                <?php foreach ($sentRequests as $request): ?>
                    <div class="bg-white rounded-card border border-border p-4 flex flex-col gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-label-lg text-label-lg text-ink"><?= e($request['mentor_name']) ?></p>
                            <?= $statusBadge($request['status']) ?>
                        </div>
                        <?php if (!empty($request['topic'])): ?>
                            <p class="text-body-sm text-muted"><?= e($request['topic']) ?></p>
                        <?php endif; ?>
                        <?php if ($request['status'] === 'accepted' && (!empty($request['mentor_email']) || !empty($request['mentor_phone']))): ?>
                            <div class="mt-1 rounded-control bg-success/[4%] p-3 text-body-sm text-ink">
                                <p class="font-label-md font-label-md text-success mb-1"><?= e(t('mentors.sent.contact_title')) ?></p>
                                <?php if (!empty($request['mentor_email'])): ?>
                                    <p><?= e($request['mentor_email']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($request['mentor_phone'])): ?>
                                    <p><?= e($request['mentor_phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>

<script src="/assets/js/admin-dialogs.js" defer></script>
