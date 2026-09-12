<?php
/**
 * Détail d'un article (FIUCC-55) — maquette
 * fiucc_portal_news_announcements/code.html pour l'esprit général (mise
 * en page article, "Related Dispatches"). Pas de signature d'auteur
 * personnelle : users n'a pas de nom d'affichage (seul l'email existe
 * réellement, CLAUDE.md) — author_id sert au journal d'activité interne
 * (comme events.created_by), jamais affiché ici. "Related" = même
 * catégorie, calculé par News::related(), jamais l'article courant.
 *
 * Contenu (content) : texte libre, rendu avec les retours à la ligne
 * respectés — même principe que events.description (FIUCC-49, décision
 * CLAUDE.md), pas d'éditeur riche/HTML.
 */
$article ??= [];
$related ??= [];

$categoryBadge = static function (string $category): string {
    return '<span class="inline-flex items-center h-7 px-3 rounded-full bg-ink/25 text-white text-label-md font-label-md uppercase tracking-wide">'
        . e(t('news.category.' . $category)) . '</span>';
};
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-10 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/news" class="hover:text-white transition-colors"><?= e(t('news.title')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e($article['title']) ?></span>
        </nav>
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <?= $categoryBadge($article['category']) ?>
            <span class="text-body-sm text-white/90"><?= e(local_date($article['published_at'])) ?></span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-white max-w-3xl"><?= e($article['title']) ?></h1>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12">
    <div class="mx-auto flex flex-col gap-8">
        <a href="/news" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors self-start">
            <?= icon('arrow-left') ?>
            <?= e(t('news.detail.back')) ?>
        </a>

        <?php if (!empty($article['cover_image'])): ?>
            <img src="<?= e('/uploads/' . $article['cover_image']) ?>" alt=""
                 class="w-full max-h-[420px] object-cover rounded-card border border-border">
        <?php endif; ?>

        <?php if (!empty($article['content'])): ?>
            <div class="text-body-lg text-ink whitespace-pre-line leading-relaxed"><?= e($article['content']) ?></div>
        <?php elseif (!empty($article['excerpt'])): ?>
            <p class="text-body-lg text-ink"><?= e($article['excerpt']) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($related !== []): ?>
        <div class="mx-auto mt-16 pt-10 border-t border-border">
            <h2 class="font-headline-md text-headline-md text-ink mb-6"><?= e(t('news.detail.related')) ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <?php foreach ($related as $item): ?>
                    <a href="<?= e('/news/' . $item['slug']) ?>" class="group flex flex-col gap-2">
                        <div class="relative h-32 w-full rounded-control overflow-hidden bg-surface border border-border">
                            <?php if (!empty($item['cover_image'])): ?>
                                <img src="<?= e('/uploads/' . $item['cover_image']) ?>" alt=""
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <div class="absolute inset-0 bg-gradient-to-br from-primary/15 to-primary/5 flex items-center justify-center">
                                    <?= icon('newspaper', 'text-2xl text-primary/30') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-label-lg text-label-lg text-ink group-hover:text-primary transition-colors line-clamp-2"><?= e($item['title']) ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
