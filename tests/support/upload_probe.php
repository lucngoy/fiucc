<?php
declare(strict_types=1);

/**
 * Serveur de sous-processus pour tests/Uploads/MaliciousUploadTest.php —
 * PAS un cas de test PHPUnit (exclu de la suite via phpunit.xml). Lancé via
 * `php -S 127.0.0.1:<port> tests/support/upload_probe.php` (serveur de
 * développement intégré à PHP).
 *
 * Pourquoi un vrai serveur HTTP plutôt qu'un appel direct à
 * App\Services\Upload depuis un test PHPUnit : Upload::assertUploadOk()
 * vérifie is_uploaded_file() — une vraie défense en profondeur du service
 * (jamais traiter un fichier qui n'est pas réellement passé par le
 * mécanisme d'upload HTTP de PHP), pas un détail à contourner pour les
 * tests. Cette fonction ne renvoie jamais vrai pour un fichier créé
 * autrement, y compris par un test PHPUnit qui appellerait directement le
 * service en CLI — le seul moyen d'obtenir un VRAI $_FILES géré par PHP est
 * un VRAI POST HTTP multipart, d'où ce serveur.
 *
 * Routes :
 *   GET  /health  -> 200 "ok", pour que le test sache que le serveur a démarré
 *   POST /upload  -> traite $_FILES['file'] via Upload::storeImage() ou
 *                    storeDocument() selon le paramètre de requête "kind"
 *                    (image|document), renvoie un JSON {ok, path?, error?}
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Upload;

$_SESSION = [];

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if ($uri === '/health') {
    echo 'ok';
    exit;
}

if ($uri !== '/upload' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json');

if (!isset($_FILES['file'])) {
    echo json_encode(['ok' => false, 'error' => 'no_file']);
    exit;
}

$kind = (string) ($_GET['kind'] ?? 'image');
$subdirectory = (string) ($_GET['subdirectory'] ?? 'test');

try {
    if ($kind === 'document') {
        $result = Upload::storeDocument($_FILES['file'], $subdirectory, 15 * 1024 * 1024);
        echo json_encode(['ok' => true, 'path' => $result['path'], 'mime_type' => $result['mime_type']]);
    } else {
        $maxInputDimension = (int) ($_GET['max_input_dimension'] ?? 5000);
        $path = Upload::storeImage($_FILES['file'], $subdirectory, 800, 15 * 1024 * 1024, $maxInputDimension);
        echo json_encode(['ok' => true, 'path' => $path]);
    }
} catch (\RuntimeException $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
