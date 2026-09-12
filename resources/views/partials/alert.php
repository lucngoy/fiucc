<?php
/**
 * @param string $message  Texte de l'alerte
 * @param string $type      info|success|warning|error (défaut "info")
 *
 * "success" utilise le vert dédié (extension de la charte, CLAUDE.md) et non
 * le bleu : une confirmation et une information générale ne doivent pas se
 * ressembler.
 */
$type ??= 'info';

$variants = [
    'info'    => ['bg' => 'bg-primary-light/20', 'text' => 'text-primary', 'icon' => 'i'],
    'success' => ['bg' => 'bg-success/[4%]',       'text' => 'text-success', 'icon' => '✓'],
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
