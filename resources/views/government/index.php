<?php
/**
 * Page Government du mandat en cours (FIUCC-41) — section 12 du cahier des
 * charges. Mandats précédents (archive) : FIUCC-42, route déjà déclarée
 * (routes/web.php) mais volontairement pas construite ici.
 */
$government ??= null;
?>
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="text-center max-w-2xl mx-auto mb-4">
        <?php if ($government !== null): ?>
            <span class="inline-flex items-center h-7 px-3 rounded-full bg-gold/20 text-ink text-label-md font-label-md mb-4">
                <?= e(t('government.mandate_badge', ['mandate' => $government['mandate']])) ?>
            </span>
        <?php endif; ?>
        <h1 class="font-headline-lg text-headline-lg text-ink mb-3"><?= e(t('government.title')) ?></h1>
        <?php if ($government !== null): ?>
            <p class="text-body-md text-muted"><?= e(t('government.subtitle', ['mandate' => $government['mandate']])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($government === null || $government['members'] === []): ?>
        <div class="max-w-xl mx-auto mt-12">
            <?= component('alert', ['type' => 'info', 'message' => t('government.empty')]) ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
            <?php foreach ($government['members'] as $member): ?>
                <div class="bg-white rounded-card border border-border shadow-card p-6 flex flex-col items-center text-center gap-3">
                    <?php if (!empty($member['photo'])): ?>
                        <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-24 w-24 rounded-full object-cover border border-border">
                    <?php else: ?>
                        <span class="h-24 w-24 rounded-full bg-primary/15 text-primary flex items-center justify-center font-headline-md text-headline-md">
                            <?= e(initials($member['display_name'])) ?>
                        </span>
                    <?php endif; ?>
                    <div>
                        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e($member['display_name']) ?></h2>
                        <span class="inline-flex items-center h-6 px-2.5 mt-1 rounded-full bg-primary/15 text-primary text-label-md font-label-md">
                            <?= e($member['role']) ?>
                        </span>
                    </div>
                    <?php if (!empty($member['instagram'])): ?>
                        <a href="<?= e('https://www.instagram.com/' . ltrim($member['instagram'], '@') . '/') ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="text-body-sm text-primary hover:underline">
                            @<?= e(ltrim($member['instagram'], '@')) ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
