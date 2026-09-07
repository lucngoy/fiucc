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
 * - dimensions d'entrée plafonnées (security.upload_max_input_dimension),
 *   vérifiées via getimagesize() — lit seulement l'en-tête, jamais les pixels
 *   — AVANT tout appel GD : une image bien compressée (8000x8000, quelques
 *   centaines de Ko) passe le contrôle de taille en octets mais ferait
 *   exploser la mémoire une fois décodée par imagecreatefromX (decompression
 *   bomb) ;
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
    public static function storeImage(
        array $file,
        string $subdirectory,
        int $maxDimension,
        int $maxSizeBytes,
        int $maxInputDimension
    ): string {
        self::assertUploadOk($file, $maxSizeBytes);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file((string) $file['tmp_name']);

        if ($realMime === false || !isset(self::ALLOWED_IMAGE_TYPES[$realMime])) {
            throw new RuntimeException(t('upload.invalid_type'));
        }

        // getimagesize() ne lit que l'en-tête (dimensions déclarées), jamais
        // les pixels — sûr à appeler avant tout contrôle de mémoire.
        $dimensions = @getimagesize((string) $file['tmp_name']);

        if ($dimensions === false) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        if ($dimensions[0] > $maxInputDimension || $dimensions[1] > $maxInputDimension) {
            throw new RuntimeException(t('upload.dimensions_too_large', ['max' => $maxInputDimension]));
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

    /**
     * Comme storeImage(), mais produit aussi une miniature à partir du même
     * décodage (FIUCC-54, galerie) — un fichier stocké une fois à l'upload,
     * jamais un redimensionnement à la volée à chaque affichage. Applique
     * la correction d'orientation EXIF avant tout redimensionnement : les
     * deux dérivés (grande taille + miniature) en héritent, sinon l'un des
     * deux resterait couché.
     *
     * @return array{path: string, thumbnail_path: string} Chemins relatifs
     *         à public/uploads/ (ex. "gallery/ab12...ef.jpg" et
     *         "gallery/thumbs/ab12...ef.jpg").
     * @throws RuntimeException Message déjà adapté à l'affichage utilisateur.
     */
    public static function storeImageWithThumbnail(
        array $file,
        string $subdirectory,
        int $maxDimension,
        int $thumbnailDimension,
        int $maxSizeBytes,
        int $maxInputDimension
    ): array {
        self::assertUploadOk($file, $maxSizeBytes);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file((string) $file['tmp_name']);

        if ($realMime === false || !isset(self::ALLOWED_IMAGE_TYPES[$realMime])) {
            throw new RuntimeException(t('upload.invalid_type'));
        }

        $dimensions = @getimagesize((string) $file['tmp_name']);

        if ($dimensions === false) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        if ($dimensions[0] > $maxInputDimension || $dimensions[1] > $maxInputDimension) {
            throw new RuntimeException(t('upload.dimensions_too_large', ['max' => $maxInputDimension]));
        }

        $extension = self::ALLOWED_IMAGE_TYPES[$realMime];
        $relativeDirectory = trim($subdirectory, '/');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationDir = BASE_PATH . '/public/uploads/' . $relativeDirectory;
        $thumbnailDir = $destinationDir . '/thumbs';

        if (!is_dir($destinationDir) || !is_dir($thumbnailDir)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $image = self::readImage((string) $file['tmp_name'], $realMime);
        $image = self::applyExifOrientation($image, (string) $file['tmp_name'], $realMime);

        $main = self::resizeToFit($image, $maxDimension);
        self::writeImage($main, $realMime, $destinationDir . '/' . $filename);

        $thumbnail = self::resizeToFit($image, $thumbnailDimension);
        self::writeImage($thumbnail, $realMime, $thumbnailDir . '/' . $filename);

        // resizeToFit() renvoie la même instance quand aucun redimensionnement
        // n'est nécessaire (photo déjà plus petite que la cible) — comparaison
        // stricte (identité d'objet), pas de repli sur une égalité de valeur
        // pas définie pour GdImage, pour ne jamais libérer deux fois la même
        // ressource ni en laisser fuiter une.
        $toDestroy = [$image];
        if ($main !== $image) {
            $toDestroy[] = $main;
        }
        if ($thumbnail !== $image && $thumbnail !== $main) {
            $toDestroy[] = $thumbnail;
        }
        foreach ($toDestroy as $resource) {
            imagedestroy($resource);
        }

        return [
            'path'           => $relativeDirectory . '/' . $filename,
            'thumbnail_path' => $relativeDirectory . '/thumbs/' . $filename,
        ];
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

    /**
     * Corrige l'orientation avant tout redimensionnement (FIUCC-54) : une
     * photo prise au téléphone porte son orientation réelle dans l'EXIF,
     * pas dans les pixels eux-mêmes — GD ne la lit jamais tout seul. Sans
     * ça, une photo prise en portrait s'affiche couchée. Seul le JPEG porte
     * de l'EXIF de façon fiable (PNG/WEBP transportés ici n'en ont
     * quasiment jamais) ; best-effort si l'extension exif n'est pas chargée
     * ou si le fichier n'a pas de tag Orientation — l'image part telle
     * quelle plutôt que de faire échouer l'upload pour ça.
     */
    private static function applyExifOrientation(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if ($orientation === 1) {
            return $image;
        }

        // 2/5/7 : mirés horizontalement ; 4 seul : miré verticalement.
        // imageflip() mute $image en place (pas de nouvelle ressource).
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        // imagerotate() tourne dans le sens anti-horaire — d'où les angles
        // négatifs pour une rotation horaire (5/6). Contrairement à
        // imageflip(), elle renvoie une NOUVELLE ressource (le canevas
        // change de dimensions dès que l'angle n'est pas un multiple de
        // 180°) : l'appelant doit détruire $image séparément.
        $angle = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (!$rotated instanceof GdImage) {
            // Best-effort : une orientation non corrigée reste préférable à
            // un upload qui échoue pour une rotation qu'on n'a pas pu faire.
            return $image;
        }

        imagedestroy($image);

        return $rotated;
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
