<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Core\EncodedUploads;
use App\Services\Upload;
use PHPUnit\Framework\TestCase;

/**
 * Fichiers envoyés en base64 (contournement du pare-feu InfinityFree,
 * 2026-10-21) : reconstitués en uploads ordinaires, sans jamais ouvrir de
 * brèche que is_uploaded_file() fermait.
 */
final class EncodedUploadsTest extends TestCase
{
    /** PNG valide en base64, produit par GD. */
    private static function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return base64_encode((string) ob_get_clean());
    }

    protected function setUp(): void
    {
        $_POST = [];
        $_FILES = [];
    }

    private function encoded(string $field, string $name, string $data): string
    {
        return json_encode(['field' => $field, 'name' => $name, 'data' => $data]);
    }

    public function test_an_encoded_file_becomes_an_ordinary_upload_and_leaves_post(): void
    {
        $_POST = ['_csrf_token' => 't', EncodedUploads::FIELD => [$this->encoded('photo', '../../etc/photo.png', self::png())]];

        EncodedUploads::hydrate();

        self::assertSame(['_csrf_token' => 't'], $_POST);
        self::assertSame(UPLOAD_ERR_OK, $_FILES['photo']['error']);
        self::assertSame('photo.png', $_FILES['photo']['name'], 'Chemin du nom retiré.');
        self::assertSame(base64_decode(self::png()), file_get_contents($_FILES['photo']['tmp_name']));
        self::assertTrue(EncodedUploads::isUploaded($_FILES['photo']['tmp_name']));
    }

    public function test_a_multiple_field_has_the_same_shape_as_a_multipart_upload(): void
    {
        $_POST = [EncodedUploads::FIELD => [$this->encoded('photos[]', 'a.png', self::png()), $this->encoded('photos[]', 'b.png', self::png())]];

        EncodedUploads::hydrate();

        self::assertSame(['a.png', 'b.png'], $_FILES['photos']['name']);
        self::assertSame([UPLOAD_ERR_OK, UPLOAD_ERR_OK], $_FILES['photos']['error']);
        self::assertCount(2, $_FILES['photos']['tmp_name']);
    }

    public function test_an_arbitrary_path_is_never_accepted_as_an_upload(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_uploaded');

        try {
            self::assertFalse(EncodedUploads::isUploaded($path));
            self::assertFalse(EncodedUploads::isUploaded('/etc/passwd'));
            self::assertFalse(EncodedUploads::moveUploaded($path, $path . '.moved'));
            self::assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_an_image_received_this_way_goes_through_the_normal_checks(): void
    {
        $_POST = [EncodedUploads::FIELD => [
            $this->encoded('good', 'logo.png', self::png()),
            $this->encoded('fake', 'logo.png', base64_encode('<?php echo 1;')),
        ]];
        EncodedUploads::hydrate();

        // Dossier isolé des tests (security.upload_images_base_path, .env.testing).
        $directory = dirname(Upload::publicPath('encoded_test/x'));
        @mkdir($directory, 0777, true);

        $stored = Upload::storeImage($_FILES['good'], 'encoded_test', 400, 1024 * 1024, 5000);
        self::assertFileExists(Upload::publicPath($stored));
        unlink(Upload::publicPath($stored));
        @rmdir($directory);

        $this->expectException(\RuntimeException::class);
        Upload::storeImage($_FILES['fake'], 'encoded_test', 400, 1024 * 1024, 5000);
    }

    public function test_invalid_entries_are_ignored_or_reported_never_trusted(): void
    {
        $tooBig = str_repeat('A', (int) (self::uploadMax() / 3 * 4) + 400);
        $_POST = [EncodedUploads::FIELD => [
            $this->encoded('photo', 'x.png', '***pas du base64***'),
            $this->encoded('_x[evil]', 'x.png', self::png()),
            $this->encoded('../photo', 'x.png', self::png()),
            $this->encoded('big', 'x.png', $tooBig),
            'pas du json',
        ]];

        EncodedUploads::hydrate();

        self::assertSame(['photo', 'big'], array_keys($_FILES));
        self::assertSame(UPLOAD_ERR_PARTIAL, $_FILES['photo']['error']);
        self::assertSame(UPLOAD_ERR_INI_SIZE, $_FILES['big']['error'], 'Même limite que pour un upload classique.');
    }

    public function test_a_real_multipart_upload_is_never_overwritten(): void
    {
        $_FILES = ['photo' => ['name' => 'vrai.jpg', 'type' => '', 'tmp_name' => '/tmp/php123', 'error' => 0, 'size' => 10]];
        $_POST = [EncodedUploads::FIELD => [$this->encoded('photo', 'autre.png', self::png())]];

        EncodedUploads::hydrate();

        self::assertSame('vrai.jpg', $_FILES['photo']['name']);
    }

    private static function uploadMax(): int
    {
        $value = (string) ini_get('upload_max_filesize');

        return (int) $value * match (strtoupper(substr($value, -1))) { 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1 };
    }
}
