<?php
/**
 * Présidents de la RDC, de l'actuel au premier (President::publishedFor(),
 * gérés depuis /admin/presidents). Frise verticale, du plus récent au plus
 * ancien ; la pastille garde le rang chronologique ('rank'). L'actuel porte
 * un badge
 * or (accent, jamais un fond). Crédits photo en bas de page, exigés par la
 * licence CC BY de l'une d'elles et donnés pour toutes.
 */
$presidents ??= [];
?>
<section class="relative w-full overflow-hidden bg-primary text-white">
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="relative max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16">
        <p class="inline-flex items-center gap-2 font-label-caps text-label-caps uppercase tracking-wider text-white/80 mb-3">
            <span class="h-2 w-2 rounded-full bg-gold" aria-hidden="true"></span>
            <?= e(t('presidents.eyebrow')) ?>
        </p>
        <h1 class="font-headline-lg text-headline-lg mb-3"><?= e(t('presidents.title')) ?></h1>
        <p class="max-w-2xl text-body-md text-white/80"><?= e(t('presidents.subtitle')) ?></p>
    </div>
</section>

<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-14">
    <ol class="relative mx-auto flex flex-col gap-6 before:absolute before:left-[11px] sm:before:left-[15px] before:top-3 before:bottom-3 before:w-0.5 before:bg-border">
        <?php foreach ($presidents as $president): ?>
            <?php
            $isCurrent = $president['until'] === null;
            $period = $isCurrent
                ? t('presidents.period_current', ['from' => (string) $president['from']])
                : t('presidents.period', ['from' => (string) $president['from'], 'until' => (string) $president['until']]);
            ?>
            <li class="relative pl-9 sm:pl-12">
                <span class="absolute left-0 top-6 h-6 w-6 sm:h-8 sm:w-8 rounded-full bg-primary text-white text-label-md font-label-md flex items-center justify-center ring-4 ring-surface" aria-hidden="true">
                    <?= e((string) $president['rank']) ?>
                </span>
                <article class="bg-white rounded-card border <?= $isCurrent ? 'border-primary shadow-card' : 'border-border' ?> overflow-hidden flex flex-col sm:flex-row">
                    <?php if ($president['photo_url'] !== null): ?>
                        <img src="<?= e($president['photo_url']) ?>"
                             alt="<?= e(t('presidents.photo_alt', ['name' => $president['name']])) ?>"
                             width="400" height="500" loading="lazy"
                             class="w-full sm:w-40 h-64 sm:h-auto object-cover object-top shrink-0 bg-surface">
                    <?php endif; ?>
                    <div class="p-5 flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider"><?= e($period) ?></p>
                            <?php if ($isCurrent): ?>
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full bg-gold/20 text-ink text-label-md font-label-md"><?= e(t('presidents.current')) ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($president['name']) ?></h2>
                        <p class="text-body-sm text-muted"><?= e($president['role']) ?></p>
                        <p class="text-body-md text-ink leading-relaxed"><?= e($president['bio']) ?></p>
                    </div>
                </article>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="mx-auto mt-10 pt-6 border-t border-border" aria-labelledby="credits-title">
        <h2 id="credits-title" class="font-label-lg text-label-lg text-ink mb-2"><?= e(t('presidents.credits.title')) ?></h2>
        <ul class="flex flex-col gap-1 text-body-sm text-muted">
            <?php foreach ($presidents as $president): ?>
                <?php $credit = $president['credit']; ?>
                <?php if ($president['photo_url'] === null || $credit['license'] === null) { continue; } ?>
                <li>
                    <?= e($president['name']) ?> :
                    <?= e((string) $credit['author']) ?>,
                    <?php if ($credit['license_url'] !== null): ?>
                        <a href="<?= e((string) $credit['license_url']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline"><?= e(t('presidents.license.' . $credit['license'])) ?></a><?= $credit['source'] !== null ? ',' : '' ?>
                    <?php else: ?>
                        <?= e(t('presidents.license.' . $credit['license'])) ?><?= $credit['source'] !== null ? ',' : '' ?>
                    <?php endif; ?>
                    <?php if ($credit['source'] !== null): ?>
                        <a href="<?= e((string) $credit['source']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline"><?= e(t('presidents.credits.source')) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
