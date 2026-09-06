<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FIUCC Portal') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Icônes (Font Awesome Free, CDN) — même principe que Google Fonts
         ci-dessus : une ressource externe déjà acceptée dans ce projet,
         pas un bundler ni une dépendance de build. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?= component('header') ?>
    <main><?= $content ?></main>
    <?= component('footer') ?>
    <script src="/assets/js/nav.js" defer></script>
    <script src="/assets/js/password-toggle.js" defer></script>
</body>
</html>
