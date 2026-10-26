<?php
/**
 * Page « Cookies » (/cookies, CookieController) : la liste exacte des
 * cookies déposés par le portail. À tenir à jour si un cookie est ajouté
 * (voir la note de CookieController sur le consentement).
 */
$sessionMinutes ??= 120;
$noticeDays ??= 365;

$cookies = [
    [
        'name'     => (string) \App\Core\Config::get('security.session_name', 'fiucc_session'),
        'purpose'  => t('cookies.list.session.purpose'),
        'duration' => t('cookies.list.session.duration', ['minutes' => $sessionMinutes]),
    ],
    [
        'name'     => \App\Controllers\CookieController::NOTICE_COOKIE,
        'purpose'  => t('cookies.list.notice.purpose'),
        'duration' => t('cookies.list.notice.duration', ['days' => $noticeDays]),
    ],
];
?>
<section class="relative w-full overflow-hidden bg-primary text-white">
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="relative max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
        <p class="inline-flex items-center gap-2 font-label-caps text-label-caps uppercase tracking-wider text-white/80 mb-3">
            <span class="h-2 w-2 rounded-full bg-gold" aria-hidden="true"></span>
            <?= e(t('cookies.eyebrow')) ?>
        </p>
        <h1 class="font-headline-lg text-headline-lg mb-3"><?= e(t('cookies.title')) ?></h1>
        <p class="max-w-2xl text-body-md text-white/80"><?= e(t('cookies.subtitle')) ?></p>
    </div>
</section>

<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-14 flex flex-col gap-8">
    <section aria-labelledby="cookies-list-title" class="flex flex-col gap-4">
        <h2 id="cookies-list-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('cookies.list.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('cookies.list.intro')) ?></p>
        <ul class="flex flex-col gap-3">
            <?php foreach ($cookies as $cookie): ?>
                <li class="bg-white rounded-card border border-border p-5 flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <code class="font-label-lg text-label-lg text-ink break-all"><?= e($cookie['name']) ?></code>
                        <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-primary/10 text-primary text-label-md font-label-md"><?= e(t('cookies.list.necessary')) ?></span>
                    </div>
                    <p class="text-body-md text-ink"><?= e($cookie['purpose']) ?></p>
                    <p class="text-body-sm text-muted"><?= e($cookie['duration']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section aria-labelledby="cookies-none-title" class="bg-white rounded-card border border-border p-5 md:p-6 flex flex-col gap-3">
        <h2 id="cookies-none-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('cookies.none.title')) ?></h2>
        <ul class="flex flex-col gap-2 text-body-md text-ink">
            <?php foreach (['ads', 'analytics', 'sharing'] as $item): ?>
                <li class="flex items-start gap-2">
                    <span class="mt-1 text-muted shrink-0"><?= icon('ban') ?></span>
                    <span><?= e(t('cookies.none.' . $item)) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section aria-labelledby="cookies-manage-title" class="flex flex-col gap-3">
        <h2 id="cookies-manage-title" class="font-headline-sm text-headline-sm text-ink"><?= e(t('cookies.manage.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('cookies.manage.body')) ?></p>
    </section>
</div>
