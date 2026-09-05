<?php
/**
 * @param string $message  Texte de l'alerte
 * @param string $type      info|success|warning|error (défaut "info")
 *
 * Pas de vert : la charte CLAUDE.md n'en définit aucun. "success" reprend donc
 * le bleu (info) plutôt que d'inventer une couleur hors charte.
 */
$type ??= 'info';

$variants = [
    'info'    => ['bg' => 'bg-primary-light/20', 'text' => 'text-primary', 'icon' => 'i'],
    'success' => ['bg' => 'bg-primary-light/20', 'text' => 'text-primary', 'icon' => '✓'],
    'warning' => ['bg' => 'bg-gold/20',          'text' => 'text-ink',     'icon' => '!'],
    'error'   => ['bg' => 'bg-danger/10',        'text' => 'text-danger', 'icon' => '✕'],
];
$variant = $variants[$type] ?? $variants['info'];
$classes = 'flex items-start gap-3 rounded-control border border-border p-4 ' . $variant['bg'] . ' ' . $variant['text'];
?>
<div class="<?= e($classes) ?>" role="alert">
    <span aria-hidden="true" class="font-bold"><?= e($variant['icon']) ?></span>
    <p class="text-body-sm"><?= e($message) ?></p>
</div>
