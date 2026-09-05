<?php
/**
 * Footer institutionnel — 4 colonnes (FIUCC-22) : marque, liens rapides,
 * communauté, contact. Uniquement des routes déjà déclarées dans
 * routes/web.php — pas de contenu inventé (réseaux sociaux en lien inerte,
 * cahier des charges §40 ne fournit pas de handle réel).
 */
$siteName ??= \App\Core\Config::get('app.name', 'FIUCC Portal');
$year = date('Y');
?>
<footer class="w-full bg-white border-t border-border">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="flex flex-col gap-3">
            <span class="font-headline-sm text-headline-sm text-ink"><?= e($siteName) ?></span>
            <p class="text-body-sm text-muted">United by our roots, driven by the future.</p>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted">Quick Links</h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="/community" class="hover:text-primary transition-colors">Community</a></li>
                <li><a href="/events" class="hover:text-primary transition-colors">Events</a></li>
                <li><a href="/news" class="hover:text-primary transition-colors">News</a></li>
                <li><a href="/gallery" class="hover:text-primary transition-colors">Gallery</a></li>
                <li><a href="/elections" class="hover:text-primary transition-colors">Elections</a></li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted">Community</h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="/directory" class="hover:text-primary transition-colors">Members Directory</a></li>
                <li><a href="/alumni" class="hover:text-primary transition-colors">Alumni</a></li>
                <li><a href="/community/government" class="hover:text-primary transition-colors">Government</a></li>
                <li><a href="/documents" class="hover:text-primary transition-colors">Documents</a></li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-label-caps text-label-caps uppercase text-muted">Connect</h4>
            <ul class="flex flex-col gap-2 text-body-sm text-muted">
                <li><a href="#" class="hover:text-primary transition-colors">Instagram</a></li>
                <li><a href="#" class="hover:text-primary transition-colors">WhatsApp Community</a></li>
                <li><a href="/contact" class="hover:text-primary transition-colors">Contact</a></li>
            </ul>
        </div>
    </div>

    <div class="w-full bg-primary">
        <div class="max-w-layout mx-auto px-4 md:px-6 py-4 text-center text-body-sm text-white">
            <p>&copy; <?= e($year) ?> <?= e($siteName) ?>. All rights reserved.</p>
        </div>
    </div>
</footer>
