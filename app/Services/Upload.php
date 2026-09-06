<?php
declare(strict_types=1);

namespace App\Services;

use finfo;
use GdImage;
use RuntimeException;

/**
 * Upload sécurisé d'images (section 34/35 du cahier des charges) :
 * - type MIME réel vérifié avec finfo sur le contenu du fichier — jamais
 *   $_FILES['type'] (fourni par le client, pas fiable) ni l'extension du nom
 *   envoyé ;
 * - nom de fichier aléatoire, jamais celui envoyé par l'utilisateur ;
 * - taille plafonnée (security.upload_max_size_mb) ;
 * - redimensionnée pour ne jamais stocker plus grand que nécessaire.
 *
 * Ne gère que les images (jpg/jpeg/png/webp) : les documents (pdf, section 30)
 * n'ont pas besoin de redimensionnement et n'ont pas encore de ticket qui les
 * upload — à ajouter ici le jour où c'est réellement nécessaire.
 */
final class Upload
{
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array $file Une entrée de $_FILES (ex. $_FILES['photo'])
     * @return string Chemin relatif à public/uploads/ (ex. "members/ab12...ef.jpg")
     * @throws RuntimeException Message déjà adapté à l'affichage utilisateur.
     */
    public static function storeImage(array $file, string $subdirectory, int $maxDimension, int $maxSizeBytes): string
    {
        self::assertUploadOk($file, $maxSizeBytes);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file((string) $file['tmp_name']);

        if ($realMime === false || !isset(self::ALLOWED_IMAGE_TYPES[$realMime])) {
            throw new RuntimeException(t('upload.invalid_type'));
        }

        $extension = self::ALLOWED_IMAGE_TYPES[$realMime];
        $relativeDirectory = trim($subdirectory, '/');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationDir = BASE_PATH . '/public/uploads/' . $relativeDirectory;

        if (!is_dir($destinationDir)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $image = self::readImage((string) $file['tmp_name'], $realMime);
        $resized = self::resizeToFit($image, $maxDimension);

        self::writeImage($resized, $realMime, $destinationDir . '/' . $filename);

        imagedestroy($image);

        if ($resized !== $image) {
            imagedestroy($resized);
        }

        return $relativeDirectory . '/' . $filename;
    }

    private static function assertUploadOk(array $file, int $maxSizeBytes): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException(t('upload.too_large'));
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t('upload.failed'));
        }

        // Défense en profondeur : ne jamais traiter un fichier qui n'est pas
        // réellement passé par le mécanisme d'upload HTTP de PHP.
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException(t('upload.failed'));
        }

        if ((int) $file['size'] > $maxSizeBytes) {
            throw new RuntimeException(t('upload.too_large'));
        }
    }

    private static function readImage(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => false,
        };

        if (!$image instanceof GdImage) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        return $image;
    }

    private static function resizeToFit(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxDimension && $height <= $maxDimension) {
            return $image;
        }

        $ratio = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        // Préserve la transparence (PNG/WEBP) au lieu de la remplir de noir.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private static function writeImage(GdImage $image, string $mime, string $destination): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $destination, 85),
            'image/png'  => imagepng($image, $destination),
            'image/webp' => imagewebp($image, $destination, 85),
            default      => false,
        };

        if (!$ok) {
            throw new RuntimeException(t('upload.processing_failed'));
        }
    }
}
