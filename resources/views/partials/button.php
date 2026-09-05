<?php
/**
 * @param string $label      Texte du bouton
 * @param string $variant    primary|secondary|accent|destructive (défaut primary)
 * @param string|null $href  Si fourni, rend un <a> ; sinon un <button>
 * @param string $type       Type du <button> (ignoré si $href est fourni)
 *
 * Rouge (destructive) réservé aux actions destructives (CLAUDE.md, charte
 * graphique) : ne jamais l'utiliser comme bouton par défaut.
 */
$variant ??= 'primary';
$href ??= null;
$type ??= 'button';

$variants = [
    'primary'     => 'bg-primary text-white hover:bg-primary-dark',
    'secondary'   => 'bg-transparent border border-primary text-primary hover:bg-primary-light/10',
    'accent'      => 'bg-gold text-ink font-bold hover:bg-gold/90',
    'destructive' => 'bg-danger text-white hover:bg-danger/90',
];
$variantClasses = $variants[$variant] ?? $variants['primary'];

$classes = 'inline-flex items-center justify-center gap-2 h-11 md:h-10 px-6 rounded-control '
    . 'font-label-lg text-label-lg transition-colors ' . $variantClasses;
?>
<?php if ($href !== null): ?>
    <a href="<?= e($href) ?>" class="<?= e($classes) ?>"><?= e($label) ?></a>
<?php else: ?>
    <button type="<?= e($type) ?>" class="<?= e($classes) ?>"><?= e($label) ?></button>
<?php endif; ?>
