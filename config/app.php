<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'FIUCC Portal'),
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => Env::get('APP_DEBUG', false),
    'url'      => Env::get('APP_URL', 'http://localhost'),
    // Français par défaut (cahier des charges §38), anglais via i18n.
    'locale'   => Env::get('APP_LOCALE', 'fr'),
    // Utilisé en phase 4 comme sel de vote_hash — pas encore obligatoire.
    'key'      => Env::get('APP_KEY'),
    'timezone' => 'Europe/Istanbul',
    // Dossier des logs (App\Core\Logger) — absent (null) partout sauf
    // .env.testing (FIUCC-79) : sans ça, les tests écriraient dans le vrai
    // storage/logs/ partagé avec le dev/Apache (constaté : mail.log y est
    // possédé par l'utilisateur Apache, illisible en écriture pour un
    // process de test lancé en CLI — un vrai conflit d'accès, pas
    // seulement un principe). Chemin relatif à BASE_PATH si non absolu.
    'log_path' => Env::get('LOG_PATH'),
];
