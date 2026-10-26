<?php
/**
 * Bandeau d'information sur les cookies (CookieController), affiché tant
 * que le visiteur ne l'a pas fermé. Le formulaire fonctionne sans
 * JavaScript ; cookie-notice.js le ferme sans recharger la page.
 */
if (isset($_COOKIE[\App\Controllers\CookieController::NOTICE_COOKIE])) {
    return;
}
?>
<div data-cookie-notice role="region" aria-label="<?= e(t('cookies.notice.label')) ?>"
     class="fixed inset-x-0 bottom-0 z-40 px-4 pb-4 pointer-events-none">
    <div class="pointer-events-auto max-w-2xl mx-auto bg-white rounded-card border border-border shadow-card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <span class="hidden sm:flex h-10 w-10 shrink-0 rounded-full bg-primary/10 text-primary items-center justify-center" aria-hidden="true"><?= icon('circle-info') ?></span>
        <p class="text-body-sm text-ink flex-1">
            <?= e(t('cookies.notice.text')) ?>
            <a href="/cookies" class="text-primary font-label-md underline hover:no-underline"><?= e(t('cookies.notice.more')) ?></a>
        </p>
        <form method="POST" action="/cookies/dismiss" class="shrink-0">
            <?= csrf_field() ?>
            <button type="submit" class="w-full sm:w-auto h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('cookies.notice.ok')) ?></button>
        </form>
    </div>
</div>
