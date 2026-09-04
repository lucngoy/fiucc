<?php
declare(strict_types=1);

/**
 * FIUCC Portal — point d'entrée unique.
 * Tout le trafic passe par ce fichier (voir public/.htaccess).
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

// TODO Phase 1 :
// 1. charger .env et config/
// 2. démarrer la session sécurisée (httponly, samesite, secure en prod)
// 3. enregistrer le handler d'erreurs
// 4. charger routes/web.php et routes/admin.php
// 5. dispatcher la requête
