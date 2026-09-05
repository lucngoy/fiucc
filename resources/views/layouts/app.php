<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FIUCC Portal') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?= component('header') ?>
    <main><?= $content ?></main>
    <?= component('footer') ?>
    <script src="/assets/js/nav.js" defer></script>
</body>
</html>
