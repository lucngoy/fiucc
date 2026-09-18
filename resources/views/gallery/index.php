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
        . icon('images', 'text-4xl text-primary/30')
        . '</div>';
};
?>
<div class="relative w-full overflow-hidden bg-primary">
    <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <style>
            /* simple SVG animations: floating lines and pulsing dots */
            @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            @keyframes pulse { 0% { r: 2; opacity: 0.9 } 50% { r: 4; opacity: 0.5 } 100% { r: 2; opacity: 0.9 } }
            .float { transform-origin: 400px 450px; animation: floatY 6s ease-in-out infinite; }
            .pulse circle { animation: pulse 2.5s ease-in-out infinite; }
        </style>
        <g class="float" stroke="#3B82F6" stroke-width="1">
            <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
            <path d="M340 20 L420 220 L520 140" />
            <path d="M420 220 L460 320 L280 360 L340 20" />
            <path d="M280 360 L120 60" />
            <path d="M460 320 L620 380 L520 500 L280 360" />
            <path d="M520 500 L560 560 L400 620 L280 360" />
            <path d="M560 560 L700 620 L620 760 L400 620" />
            <path d="M620 760 L640 800 L480 860 L400 620" />
        </g>
        <g class="pulse" fill="#60A5FA">
            <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
            <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
            <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
            <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
            <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
            <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
            <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
        </g>
    </svg>
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
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

<div class="w-full">
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
                            <?= icon('images', 'text-xs') ?>
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
