<!-- Page d'accueil temporaire — FIUCC-17, remplacée par la vraie page en phase 3 (FIUCC-9) -->
<h1><?= e($siteName) ?></h1>
<p>
    Database connection:
    <strong style="color: <?= $dbConnected ? '#0063C6' : '#C00F1F' ?>;">
        <?= $dbConnected ? 'OK' : 'FAILED' ?>
    </strong>
</p>
