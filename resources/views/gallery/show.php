<?php
/**
 * Détail d'un album (FIUCC-52) — grille de photos en colonnes CSS
 * (columns-*, pas de vraie librairie masonry) : respecte le ratio réel de
 * chaque photo uploadée, sans hauteurs inventées comme la maquette
 * (fiucc_portal_photo_gallery_memories/code.html) qui fixe des hauteurs
 * fictives par photo.
 *
 * Chaque photo ouvre son fichier original dans un nouvel onglet — pas de
 * visionneuse plein écran avec navigation précédent/suivant pour l'instant
 * (lightbox : FIUCC-53, ticket suivant).
 *
 * Lien "Voir l'événement" (migration 007) : affiché seulement si l'album
 * est rattaché à un événement lui-même visible publiquement
 * (Album::findBySlug() ne renseigne event_slug que dans ce cas — jamais un
 * lien mort vers un brouillon).
 *
 * Grille : thumbnail_path (miniature générée à l'upload, FIUCC-54) avec
 * repli sur image_path quand elle est vide (migration 008, colonne
 * nullable). Le lien d'ouverture ("voir l'original") pointe toujours sur
 * image_path (la dérivée pleine taille), jamais la miniature.
 */
$album ??= [];
$photos ??= [];
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/70 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/gallery" class="hover:text-white transition-colors"><?= e(t('gallery.title')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e($album['title']) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e($album['title']) ?></h1>
        <?php if (!empty($album['description'])): ?>
            <p class="text-body-lg text-white/80 max-w-2xl whitespace-pre-line"><?= e($album['description']) ?></p>
        <?php endif; ?>
        <p class="text-body-md text-white/70 mt-3"><?= e(t('gallery.detail.photo_count', ['count' => count($photos)])) ?></p>
        <?php if (!empty($album['event_slug'])): ?>
            <a href="<?= e('/events/' . $album['event_slug']) ?>"
               class="inline-flex items-center gap-2 mt-4 text-body-sm text-white/80 hover:text-white transition-colors">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                <?= e(t('gallery.detail.view_event', ['title' => $album['event_title']])) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <a href="/gallery" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-8">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <?= e(t('gallery.detail.back')) ?>
    </a>

    <?php if ($photos === []): ?>
        <div class="max-w-xl mx-auto">
            <?= component('alert', ['type' => 'info', 'message' => t('gallery.detail.empty')]) ?>
        </div>
    <?php else: ?>
        <div class="columns-1 sm:columns-2 lg:columns-3 gap-4 md:gap-6 [column-fill:_balance]">
            <?php foreach ($photos as $photo): ?>
                <?php $thumb = $photo['thumbnail_path'] ?: $photo['image_path']; ?>
                <a href="<?= e('/uploads/' . $photo['image_path']) ?>" target="_blank" rel="noopener"
                   class="group block mb-4 md:mb-6 break-inside-avoid rounded-card overflow-hidden bg-surface border border-border hover:shadow-card transition-shadow">
                    <img src="<?= e('/uploads/' . $thumb) ?>" alt="<?= e((string) ($photo['caption'] ?? '')) ?>"
                         class="w-full h-auto group-hover:scale-[1.02] transition-transform duration-300">
                    <?php if (!empty($photo['caption'])): ?>
                        <p class="p-3 text-body-sm text-muted"><?= e($photo['caption']) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>
