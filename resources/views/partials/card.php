<?php
/**
 * @param string|null $title    Titre optionnel (échappé)
 * @param string $content       Corps de la carte — HTML déjà composé/échappé par l'appelant,
 *                               rendu tel quel (même convention que $content du layout)
 * @param bool $featured        Ajoute le liseré bleu supérieur (carte mise en avant)
 */
$title ??= null;
$content ??= '';
$featured ??= false;

$classes = 'bg-white rounded-card border border-border shadow-card p-6'
    . ($featured ? ' border-t-4 border-t-primary' : '');
?>
<div class="<?= e($classes) ?>">
    <?php if ($title !== null): ?>
        <h3 class="font-headline-sm text-headline-sm text-ink mb-3"><?= e($title) ?></h3>
    <?php endif; ?>
    <?= $content ?>
</div>
