<?php
declare(strict_types=1);

namespace Tests\Uploads;

use CURLFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Upload malveillant (§52 du cahier) — CLAUDE.md règle 7 : "type MIME réel
 * vérifié avec finfo, jamais $_FILES['type'] ni l'extension. Nom de fichier
 * aléatoire." Testé contre le VRAI service (App\Services\Upload), via un
 * VRAI serveur HTTP (tests/support/upload_probe.php, `php -S`) plutôt qu'un
 * appel direct : Upload::assertUploadOk() vérifie is_uploaded_file(), qui ne
 * renvoie jamais vrai pour un fichier créé autrement qu'un vrai POST HTTP
 * multipart — contourner ça pour "faciliter" le test affaiblirait une vraie
 * défense en profondeur du service, pas un détail d'implémentation.
 *
 * Écrit dans storage/testing_uploads/ (jamais public/uploads/ ni storage/
 * réels — voir .env.testing.example et App\Services\Upload::imagesBasePath()
 * /storageBasePath(), FIUCC-79), explicitement vérifié par
 * test_a_valid_image_is_written_under_the_isolated_test_directory().
 */
final class MaliciousUploadTest extends TestCase
{
    /** @var resource|null */
    private static $serverProcess;
    /** @var array<int, resource> */
    private static array $serverPipes = [];
    private static int $port;
    private static string $scratchDir;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        self::$scratchDir = sys_get_temp_dir() . '/fiucc_upload_fixtures_' . bin2hex(random_bytes(4));
        mkdir(self::$scratchDir, 0777, true);

        // Upload::storeImage()/storeDocument() exigent que le dossier de
        // destination existe déjà (elles ne le créent jamais elles-mêmes) —
        // s'assure qu'il existe plutôt que de dépendre d'un état laissé par
        // une exécution précédente.
        @mkdir($root . '/storage/testing_uploads/images/test', 0777, true);
        @mkdir($root . '/storage/testing_uploads/images/test/thumbs', 0777, true);
        // "documents/test", pas seulement "documents" : le sous-dossier vient
        // du paramètre subdirectory=test envoyé par upload() ci-dessous —
        // Upload::storeDocument() vérifie is_dir() sur le chemin complet.
        @mkdir($root . '/storage/testing_uploads/documents/test', 0777, true);

        self::$port = random_int(39000, 49000);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, 'tests/support/upload_probe.php'],
            $descriptors,
            $pipes,
            $root
        );

        if (!is_resource($process)) {
            self::fail('Impossible de lancer le serveur de test des uploads.');
        }

        self::$serverProcess = $process;
        // Les tubes doivent rester OUVERTS (pas fclose() immédiat) : `php -S`
        // écrit son bandeau de démarrage et une ligne de journal par requête
        // sur STDOUT/STDERR — les fermer tout de suite lui ferait recevoir
        // SIGPIPE à la première écriture et mourrait avant même d'écouter,
        // laissant /health sans réponse jusqu'au délai d'attente. Pas besoin
        // de les vider activement : le volume écrit par une poignée de
        // requêtes de test reste largement sous la taille d'un tube.
        self::$serverPipes = $pipes;

        $deadline = microtime(true) + 5.0;
        while (true) {
            $ch = curl_init('http://127.0.0.1:' . self::$port . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // >= 1000ms : en dessous, ce libcurl échoue avec "remaining
            // timeout ... too small to resolve via SIGALRM method" plutôt
            // que d'essayer — constaté en local, pas une valeur arbitraire.
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
            curl_setopt($ch, CURLOPT_NOSIGNAL, true);
            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === 'ok') {
                return;
            }

            if (microtime(true) > $deadline) {
                self::fail('Le serveur de test des uploads (php -S) n\'a jamais répondu à /health.');
            }

            usleep(20000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }

        self::removeDirectory(self::$scratchDir);
        // Nettoie aussi ce que le service a réellement écrit dans le
        // répertoire de test isolé — jamais public/uploads/ ni storage/.
        self::removeDirectory(dirname(__DIR__, 2) . '/storage/testing_uploads/images/test');
        self::removeDirectory(dirname(__DIR__, 2) . '/storage/testing_uploads/documents');
        mkdir(dirname(__DIR__, 2) . '/storage/testing_uploads/images/test/thumbs', 0777, true);
        mkdir(dirname(__DIR__, 2) . '/storage/testing_uploads/documents/test', 0777, true);
    }

    public function test_a_php_script_disguised_with_a_jpg_extension_is_rejected(): void
    {
        $path = $this->writeFixture('evil.jpg', "<?php echo 'pwned'; ?>");

        $response = $this->upload($path, 'image/jpeg', 'evil.jpg');

        self::assertFalse($response['ok']);
    }

    public function test_a_non_image_file_falsely_claiming_the_image_jpeg_content_type_is_rejected(): void
    {
        // Le client contrôle $_FILES['type'] (ici via CURLFile) — la seule
        // chose qui doit compter est le contenu réel, jamais cette valeur.
        $path = $this->writeFixture('fake.jpg', str_repeat('not a real image', 20));

        $response = $this->upload($path, 'image/jpeg', 'fake.jpg');

        self::assertFalse($response['ok']);
    }

    public function test_a_genuinely_valid_jpeg_is_accepted(): void
    {
        $path = $this->writeJpegFixture('real.jpg', 20, 20);

        $response = $this->upload($path, 'image/jpeg', 'real.jpg');

        self::assertTrue($response['ok'], (string) ($response['error'] ?? ''));
        self::assertStringStartsWith('test/', $response['path']);
        // Nom de fichier aléatoire, jamais celui envoyé (règle 7) : le nom
        // d'origine "real.jpg" ne doit apparaître nulle part dans le chemin
        // renvoyé, seulement l'extension.
        self::assertStringNotContainsString('real', $response['path']);
    }

    public function test_a_valid_image_is_written_under_the_isolated_test_directory(): void
    {
        $path = $this->writeJpegFixture('iso.jpg', 20, 20);

        $response = $this->upload($path, 'image/jpeg', 'iso.jpg');
        self::assertTrue($response['ok'], (string) ($response['error'] ?? ''));

        $root = dirname(__DIR__, 2);
        $writtenPath = $root . '/storage/testing_uploads/images/' . $response['path'];

        self::assertFileExists($writtenPath, 'Le fichier doit être écrit sous le dossier isolé des tests.');
        self::assertFileDoesNotExist(
            $root . '/public/uploads/' . $response['path'],
            'Un test ne doit JAMAIS écrire dans le vrai dossier public/uploads/ servi par Apache.'
        );
    }

    /**
     * Garde-fou anti-bombe de décompression (App\Services\Upload,
     * commentaire de classe) : une image aux dimensions déclarées énormes
     * mais légère en octets (fond uni, très compressible) doit être rejetée
     * AVANT tout décodage GD complet — jamais laissée passer sur la seule
     * foi de sa taille en octets.
     */
    public function test_an_oversized_dimension_image_is_rejected_before_gd_decoding(): void
    {
        $path = $this->writeJpegFixture('huge.jpg', 6000, 6000);
        self::assertLessThan(2 * 1024 * 1024, filesize($path), 'Précondition : le fixture doit rester petit en octets malgré ses dimensions.');

        $response = $this->upload($path, 'image/jpeg', 'huge.jpg', 'image', ['max_input_dimension' => 5000]);

        self::assertFalse($response['ok']);
    }

    public function test_a_text_file_renamed_to_docx_without_the_required_internal_structure_is_rejected(): void
    {
        $path = $this->writeFixture('fake.docx', 'ceci nest pas un document Office');

        $response = $this->upload($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'fake.docx', 'document');

        self::assertFalse($response['ok']);
    }

    public function test_a_plain_zip_with_no_office_or_odf_marker_is_rejected_even_with_a_docx_extension(): void
    {
        $path = self::$scratchDir . '/plain.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'juste un zip, pas un document');
        $zip->close();

        $response = $this->upload($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'plain.docx', 'document');

        self::assertFalse($response['ok']);
    }

    /**
     * Reproduit exactement le cas documenté dans App\Services\Upload::
     * resolveDocumentType() : un .docx dont finfo ne détecte que le type
     * générique "application/zip" (structure OOXML minimale, sans le
     * [Content_Types].xml complet qu'un vrai Word produirait) doit quand
     * même être accepté grâce au marqueur interne word/document.xml —
     * jamais rejeté sur la seule foi du type générique renvoyé par finfo.
     */
    public function test_a_minimal_ooxml_zip_detected_as_generic_zip_by_finfo_is_still_accepted_via_its_internal_marker(): void
    {
        $path = self::$scratchDir . '/minimal.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="x"><w:body/></w:document>');
        $zip->close();

        $response = $this->upload($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'minimal.docx', 'document');

        self::assertTrue($response['ok'], (string) ($response['error'] ?? ''));
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response['mime_type']
        );
    }

    private function writeFixture(string $filename, string $content): string
    {
        $path = self::$scratchDir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    private function writeJpegFixture(string $filename, int $width, int $height): string
    {
        $path = self::$scratchDir . '/' . $filename;
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 120, 140, 200);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path, 60);
        imagedestroy($image);

        return $path;
    }

    /**
     * @return array{ok: bool, path?: string, error?: string, mime_type?: string}
     */
    private function upload(string $localPath, string $mimeTypeClaim, string $filename, string $kind = 'image', array $extraQuery = []): array
    {
        $query = array_merge(['kind' => $kind, 'subdirectory' => 'test'], $extraQuery);
        $url = 'http://127.0.0.1:' . self::$port . '/upload?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($localPath, $mimeTypeClaim, $filename),
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        self::assertNotFalse($response, "Requête curl échouée : {$curlError}");

        $decoded = json_decode((string) $response, true);
        self::assertIsArray($decoded, "Réponse inattendue du serveur de test : {$response}");

        return $decoded;
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            is_dir($itemPath) ? self::removeDirectory($itemPath) : unlink($itemPath);
        }

        rmdir($path);
    }
}
