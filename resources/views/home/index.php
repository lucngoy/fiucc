<!-- Page d'accueil temporaire — FIUCC-17, remplacée par la vraie page en phase 3 (FIUCC-9) -->
<h1><?= e($siteName) ?></h1>
<p>
    Database connection:
    <strong style="color: <?= e($dbConnected ? '#0063C6' : '#C00F1F') ?>;">
        <?= e($dbConnected ? 'OK' : 'FAILED') ?>
    </strong>
</p>
