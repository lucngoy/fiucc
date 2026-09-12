<?php
/**
 * Footer institutionnel — 4 colonnes (FIUCC-22) : marque, liens rapides,
 * communauté, contact. Uniquement des routes déjà déclarées dans
 * routes/web.php — pas de contenu inventé (réseaux sociaux en lien inerte,
 * cahier des charges §40 ne fournit pas de handle réel).
 */
$brandName ??= 'FIUCC';
$year = date('Y');
?>
<footer class="w-full bg-white border-t border-border">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-3">
                <img src="/assets/images/fiucc-seal.png" alt="FIUCC official seal" class="h-10 w-10 object-contain">
                <span class="font-headline-sm text-headline-sm text-ink"><?= e($brandName) ?></span>
            </div>
            <p class="text-body-sm text-muted"><?= e(t('footer.tagline')) ?></p>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted"><?= e(t('footer.quick_links')) ?></h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="/community" class="hover:text-primary transition-colors"><?= e(t('nav.community')) ?></a></li>
                <li><a href="/events" class="hover:text-primary transition-colors"><?= e(t('nav.events')) ?></a></li>
                <li><a href="/news" class="hover:text-primary transition-colors"><?= e(t('nav.news')) ?></a></li>
                <li><a href="/gallery" class="hover:text-primary transition-colors"><?= e(t('nav.gallery')) ?></a></li>
                <li><a href="/elections" class="hover:text-primary transition-colors"><?= e(t('nav.elections')) ?></a></li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted"><?= e(t('footer.community')) ?></h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="/directory" class="hover:text-primary transition-colors"><?= e(t('footer.directory')) ?></a></li>
                <li><a href="/alumni" class="hover:text-primary transition-colors"><?= e(t('footer.alumni')) ?></a></li>
                <li><a href="/mentors" class="hover:text-primary transition-colors"><?= e(t('footer.mentors')) ?></a></li>
                <li><a href="/community/government" class="hover:text-primary transition-colors"><?= e(t('footer.government')) ?></a></li>
                <li><a href="/documents" class="hover:text-primary transition-colors"><?= e(t('footer.documents')) ?></a></li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted"><?= e(t('footer.connect')) ?></h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="#" class="hover:text-primary transition-colors"><?= e(t('footer.instagram')) ?></a></li>
                <li><a href="#" class="hover:text-primary transition-colors"><?= e(t('footer.whatsapp')) ?></a></li>
                <li><a href="/contact" class="hover:text-primary transition-colors"><?= e(t('footer.contact')) ?></a></li>
            </ul>
        </div>
    </div>

    <div class="w-full bg-primary">
        <div class="max-w-layout mx-auto px-4 md:px-6 py-4 text-center text-body-sm text-white">
            <p>&copy; <?= e($year) ?> <?= e($brandName) ?>. <?= e(t('footer.rights')) ?></p>
        </div>
    </div>
</footer>
