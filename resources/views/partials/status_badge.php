<?php
/**
 * Badge de statut membre.
 *
 * @param string $status      student|alumni|mentor|admin
 * @param string|null $label  Libellé affiché (défaut : nom du statut)
 */
$status ??= 'student';
$label ??= null;

$variants = [
    'student' => 'bg-primary/15 text-primary',
    'alumni'  => 'bg-gold/20 text-ink',
    'mentor'  => 'bg-primary-light/30 text-primary',
    'admin'   => 'bg-danger/15 text-danger',
];
$defaultLabels = [
    'student' => 'Student',
    'alumni'  => 'Alumni',
    'mentor'  => 'Mentor',
    'admin'   => 'Admin',
];

$key = strtolower((string) $status);
$classes = $variants[$key] ?? $variants['student'];
$label ??= $defaultLabels[$key] ?? ucfirst($key);
?>
<span class="inline-flex items-center h-6 px-2.5 rounded-full text-label-md font-label-md <?= e($classes) ?>">
    <?= e($label) ?>
</span>
