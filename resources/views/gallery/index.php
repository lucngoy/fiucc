<?php
/**
 * Galerie photo par albums (FIUCC-52, section 28 du cahier des charges) —
 * grille d'albums, maquette fiucc_portal_photo_gallery_memories/code.html
 * pour l'esprit général, sur les tokens du projet. Beaucoup d'éléments de
 * la maquette n'ont aucune donnée réelle derrière (compteur "1,420+
 * photographs", filtres par année académique, téléchargement ZIP, mode
 * d'affichage masonry/grille) — volontairement absents ici, comme pour
 * events/index.php (FIUCC-48).
 *
 * Un album n'apparaît que 'published' ET avec au moins une photo
 * (Album::publicList()) : pas de lien vers un album vide, rien à y voir.
 *
 * État vide : aucun album de démonstration — soigné plutôt que générique,
 * comme events.empty.upcoming/past.
 *
 * Couverture : cover_thumbnail (miniature générée à l'upload, FIUCC-54)
 * avec repli sur cover_image (fichier pleine taille) quand elle est vide —
 * migration 008, thumbnail_path est nullable.
 */
$albums ??= [];
$total ??= 0;
$currentPage ??= 1;
$totalPages ??= 1;

$imagePlaceholder = static function (): string {
    return '<div class="absolute inset-0 bg-gradient-to-br from-primary/15 to-primary/5 flex items-center justify-center">'
        . '<i class="fa-solid fa-images text-4xl text-primary/30" aria-hidden="true"></i>'
        . '</div>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('gallery.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e(t('gallery.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-2xl"><?= e(t('gallery.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <?php if ($albums === []): ?>
        <div class="mx-auto">
            <?= component('alert', ['type' => 'info', 'message' => t('gallery.empty')]) ?>
        </div>
    <?php else: ?>
        <p class="text-body-md text-muted mb-8"><?= e(t('gallery.total', ['count' => $total])) ?></p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($albums as $album): ?>
                <a href="<?= e('/gallery/' . $album['slug']) ?>" class="group flex flex-col bg-white rounded-card border border-border hover:shadow-card transition-shadow overflow-hidden">
                    <?php $cover = $album['cover_thumbnail'] ?: $album['cover_image']; ?>
                    <div class="relative h-52 w-full overflow-hidden bg-surface">
                        <?php if (!empty($cover)): ?>
                            <img src="<?= e('/uploads/' . $cover) ?>" alt=""
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <?= $imagePlaceholder() ?>
                        <?php endif; ?>
                        <span class="absolute top-3 right-3 bg-ink/75 text-white text-label-md font-label-md px-2.5 py-1 rounded-control backdrop-blur-sm inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-images text-xs" aria-hidden="true"></i>
                            <?= e(t('gallery.album.photo_count', ['count' => (int) $album['photo_count']])) ?>
                        </span>
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <h2 class="font-headline-sm text-headline-sm text-ink mb-1 group-hover:text-primary transition-colors"><?= e($album['title']) ?></h2>
                        <?php if (!empty($album['description'])): ?>
                            <p class="text-body-sm text-muted line-clamp-2"><?= e($album['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-10">
            <?= component('pagination', [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'baseUrl'     => '/gallery?page={page}',
            ]) ?>
        </div>
    <?php endif; ?>
</div>
</div>
