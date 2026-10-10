<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FIUCC Portal') ?></title>
    <!-- Police auto-hébergée (FIUCC-78) — voir layouts/app.php. -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="/assets/images/fiucc-seal.png">
</head>
<body>
    <?= $content ?>
</body>
</html>
