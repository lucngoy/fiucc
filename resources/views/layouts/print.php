<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FIUCC Portal') ?></title>
    <!-- Police auto-hébergée (FIUCC-78) — voir layouts/app.php. -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        /*
         * Sans ceci, Chrome/Safari n'impriment aucune background-color
         * (économie d'encre par défaut) : les barres bg-primary du
         * dépouillement (partials/election_results_body.php) disparaissent
         * à l'impression/export PDF, ne laissant que la bordure du rail vide
         * — constaté en usage réel (conversation FIUCC-72). Ne remplace pas
         * la case "Graphiques d'arrière-plan" du navigateur (voir la note
         * sous le bouton Imprimer), mais est nécessaire pour qu'elle
         * fonctionne une fois cochée.
         */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    </style>
</head>
<body class="bg-white">
    <?= $content ?>
</body>
</html>
