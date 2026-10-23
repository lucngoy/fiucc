<?php
/**
 * SEO (FIUCC-75, section 36 du cahier des charges) — "chaque page doit
 * avoir : title, meta description, Open Graph, Twitter/X card, URL propre,
 * slug." URL propre/slug : déjà acquis (routes/web.php, /events/{slug},
 * /news/{slug}, /gallery/{slug}...), rien à faire ici. Le reste vient d'un
 * seul bloc dans ce layout — tout contrôleur qui passe déjà $title à
 * $this->view() a maintenant aussi accès à $description/$ogImage
 * facultatifs, avec un repli sain (seo.default_*) si absents : jamais une
 * page sans description plutôt que d'attendre que chaque contrôleur soit
 * mis à jour un par un.
 *
 * $canonicalUrl dérivée de REQUEST_URI, jamais construite à la main par
 * l'appelant — une seule source de vérité, jamais deux URLs différentes
 * pour la même page selon qui a pensé à la fournir.
 */
$title ??= t('seo.default_title');
$description ??= t('seo.default_description');
$ogImage ??= '/assets/images/fiucc-seal.png';
$canonicalUrl = rtrim((string) \App\Core\Config::get('app.url', ''), '/') . (string) ($_SERVER['REQUEST_URI'] ?? '/');
$ogImageUrl = rtrim((string) \App\Core\Config::get('app.url', ''), '/') . $ogImage;
?>
<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:site_name" content="<?= e(t('seo.site_name')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImageUrl) ?>">
    <meta property="og:locale" content="<?= e(\App\Core\Lang::locale() === 'fr' ? 'fr_FR' : 'en_US') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
    <!-- Police (Inter) et icônes : auto-hébergées (FIUCC-78) — voir
         resources/css/app.css pour le raisonnement complet -->
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="/assets/images/fiucc-seal.png">
</head>
<body class="flex flex-col min-h-screen bg-surface">
    <?= component('header') ?>
    <main class="flex-grow"><?= $content ?></main>
    <?= component('footer') ?>
    <script src="https://kit.fontawesome.com/683aca505a.js" crossorigin="anonymous"></script>
    <script src="/assets/js/nav.js" defer></script>
    <script src="/assets/js/encoded-uploads.js" defer></script>
</body>
</html>
