<?php
/**
 * Visionneuse plein écran (FIUCC-53) — <dialog> générique, pilotée par
 * gallery-lightbox.js qui cherche #lightbox + [data-lightbox-trigger]
 * n'importe où sur la page. Un seul <dialog id="lightbox"> par page (id
 * non répétable) : n'inclure ce partiel qu'une fois par vue, même si
 * plusieurs groupes de vignettes [data-lightbox-trigger] coexistent —
 * gallery-lightbox.js les traite tous comme une seule série navigable.
 *
 * L'appelant est responsable d'inclure le script lui-même
 * (<script src="/assets/js/gallery-lightbox.js" defer>) et de ne le faire
 * que si des vignettes sont réellement présentes.
 */
?>
<dialog id="lightbox" class="rounded-card border-0 bg-transparent p-0 max-w-[95vw] w-full sm:max-w-4xl backdrop:bg-ink/90">
    <div class="relative flex flex-col items-center gap-3 p-2 sm:p-4">
        <button type="button" id="lightbox-close" aria-label="<?= e(t('gallery.lightbox.close')) ?>"
            class="absolute -top-3 -right-3 sm:-top-2 sm:-right-2 z-20 h-10 w-10 rounded-full bg-ink/70 hover:bg-ink text-white flex items-center justify-center transition-colors">
            <?= icon('xmark') ?>
        </button>
        <div class="relative w-full flex items-center justify-center">
            <button type="button" id="lightbox-prev" aria-label="<?= e(t('gallery.lightbox.previous')) ?>"
                class="absolute left-1 sm:left-2 z-10 h-10 w-10 rounded-full bg-ink/50 hover:bg-ink/70 text-white flex items-center justify-center transition-colors">
                <?= icon('chevron-left') ?>
            </button>
            <img id="lightbox-image" src="" alt="" class="max-h-[75vh] w-auto max-w-full rounded-control object-contain">
            <button type="button" id="lightbox-next" aria-label="<?= e(t('gallery.lightbox.next')) ?>"
                class="absolute right-1 sm:right-2 z-10 h-10 w-10 rounded-full bg-ink/50 hover:bg-ink/70 text-white flex items-center justify-center transition-colors">
                <?= icon('chevron-right') ?>
            </button>
        </div>
        <div class="flex flex-col items-center gap-1 text-center px-8">
            <p id="lightbox-caption" class="text-white text-body-sm max-w-xl" hidden></p>
            <p id="lightbox-counter" class="text-white/60 text-label-caps font-label-caps uppercase"></p>
        </div>
    </div>
</dialog>
