<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\EncodedUploads;
use finfo;
use GdImage;
use RuntimeException;
use ZipArchive;

/**
 * Upload sécurisé (section 34/35 du cahier des charges) :
 * - type MIME réel vérifié avec finfo sur le contenu du fichier — jamais
 *   $_FILES['type'] (fourni par le client, pas fiable) ni l'extension du nom
 *   envoyé ;
 * - nom de fichier aléatoire, jamais celui envoyé par l'utilisateur ;
 * - taille plafonnée (security.upload_max_size_mb pour les images,
 *   security.upload_document_max_size_mb pour les documents) ;
 * - dimensions d'entrée plafonnées (security.upload_max_input_dimension),
 *   vérifiées via getimagesize() — lit seulement l'en-tête, jamais les pixels
 *   — AVANT tout appel GD : une image bien compressée (8000x8000, quelques
 *   centaines de Ko) passe le contrôle de taille en octets mais ferait
 *   exploser la mémoire une fois décodée par imagecreatefromX (decompression
 *   bomb) ;
 * - redimensionnée pour ne jamais stocker plus grand que nécessaire.
 *
 * storeImage()/storeImageWithThumbnail() écrivent sous public/uploads/
 * (servi par Apache, protection par obscurité du nom aléatoire seulement).
 * storeDocument() (FIUCC-58) écrit sous storage/ (jamais servi par Apache) —
 * un document en visibilité members/admin n'a aucune protection tant qu'il
 * reste un fichier statique atteignable ; seul App\Controllers\
 * DocumentController::download() y donne accès, après avoir vérifié les
 * droits sur la ligne en base à chaque requête.
 */
final class Upload
{
    /**
     * Dossier de base des images (public/uploads/ par défaut) — surchargeable
     * via security.upload_images_base_path (FIUCC-79, .env.testing
     * uniquement) pour qu'un test n'écrive jamais dans le vrai dossier servi
     * par Apache. Chemin relatif à BASE_PATH si la valeur ne commence pas
     * par "/".
     */
    /**
     * Chemin absolu d'une image publique (relatif à public/uploads, ou à
     * son équivalent de test) — pour App\Services\SecondRound, qui copie
     * la photo d'un candidat sans passer par un upload.
     */
    public static function publicPath(string $relativePath): string
    {
        return self::imagesBasePath() . '/' . ltrim($relativePath, '/');
    }

    private static function imagesBasePath(): string
    {
        return self::resolveBasePath('upload_images_base_path', BASE_PATH . '/public/uploads');
    }

    /**
     * Même principe qu'imagesBasePath() pour storage/ (documents).
     */
    private static function storageBasePath(): string
    {
        return self::resolveBasePath('upload_storage_base_path', BASE_PATH . '/storage');
    }

    private static function resolveBasePath(string $configKey, string $default): string
    {
        $configured = Config::get('security.' . $configKey);

        if (!is_string($configured) || $configured === '') {
            return $default;
        }

        return str_starts_with($configured, '/') ? $configured : BASE_PATH . '/' . ltrim($configured, '/');
    }

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
        $destinationDir = self::imagesBasePath() . '/' . $relativeDirectory;

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
        $destinationDir = self::imagesBasePath() . '/' . $relativeDirectory;
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

    /**
     * Image privée (scan de passeport, migration 018) : mêmes contrôles que
     * storeImage() — finfo, dimensions d'entrée plafonnées, ré-encodage GD
     * qui élimine les métadonnées EXIF et tout contenu caché dans le
     * fichier d'origine — mais écrite sous storage/, jamais public/uploads/ :
     * comme un document (storeDocument()), le fichier n'est atteignable que
     * par un contrôleur qui vérifie les droits à chaque requête.
     *
     * @param string $subdirectory Relatif à storage/ (ex. "passports")
     * @return string Chemin relatif à storage/ (ex. "passports/ab12...ef.jpg")
     * @throws RuntimeException Message déjà adapté à l'affichage utilisateur.
     */
    public static function storePrivateImage(
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
        $destinationDir = self::storageBasePath() . '/' . $relativeDirectory;

        if (!is_dir($destinationDir)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $image = self::readImage((string) $file['tmp_name'], $realMime);
        $image = self::applyExifOrientation($image, (string) $file['tmp_name'], $realMime);
        $resized = self::resizeToFit($image, $maxDimension);

        self::writeImage($resized, $realMime, $destinationDir . '/' . $filename);

        imagedestroy($image);

        if ($resized !== $image) {
            imagedestroy($resized);
        }

        return $relativeDirectory . '/' . $filename;
    }

    /**
     * Copie publique, redimensionnée, d'une image déjà stockée en privé par
     * storePrivateImage() — ex. la photo professionnelle d'un candidat
     * (lot 3), conservée en privé dans son dossier ET affichée sur la page
     * publique des candidats. Re-décodée et ré-encodée par GD, nom aléatoire.
     *
     * @param string $privatePath Chemin relatif à storage/
     * @return string Chemin relatif à public/uploads/
     * @throws RuntimeException
     */
    public static function publishPrivateImage(string $privatePath, string $subdirectory, int $maxDimension): string
    {
        $source = self::storagePath($privatePath);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = is_file($source) ? $finfo->file($source) : false;

        if ($mime === false || !isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $relativeDirectory = trim($subdirectory, '/');
        $destinationDir = self::imagesBasePath() . '/' . $relativeDirectory;

        if (!is_dir($destinationDir)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_IMAGE_TYPES[$mime];
        $image = self::readImage($source, $mime);
        $resized = self::resizeToFit($image, $maxDimension);

        self::writeImage($resized, $mime, $destinationDir . '/' . $filename);

        imagedestroy($image);

        if ($resized !== $image) {
            imagedestroy($resized);
        }

        return $relativeDirectory . '/' . $filename;
    }

    /**
     * Chemin absolu d'un fichier stocké sous storage/ (storeDocument(),
     * storePrivateImage()), pour le streamer depuis un contrôleur.
     */
    public static function storagePath(string $relativePath): string
    {
        return self::storageBasePath() . '/' . ltrim($relativePath, '/');
    }

    private const ALLOWED_DOCUMENT_TYPES = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
    ];

    /**
     * Documents bureautiques (FIUCC-58, section 30 du cahier des charges) —
     * PDF, Word/Excel/PowerPoint (OOXML) et OpenDocument Text. Écrit sous
     * storage/, jamais public/uploads/ : voir le commentaire de classe.
     *
     * @param array $file Une entrée de $_FILES (ex. $_FILES['file'])
     * @param string $subdirectory Relatif à storage/ (ex. "documents")
     * @return array{path: string, mime_type: string, size: int} path relatif
     *         à storage/ (ex. "documents/ab12...ef.pdf")
     * @throws RuntimeException Message déjà adapté à l'affichage utilisateur.
     */
    public static function storeDocument(array $file, string $subdirectory, int $maxSizeBytes): array
    {
        self::assertUploadOk($file, $maxSizeBytes);

        $tmpPath = (string) $file['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($tmpPath);

        $match = $detectedMime !== false ? self::resolveDocumentType($detectedMime, $tmpPath) : null;

        if ($match === null) {
            throw new RuntimeException(t('upload.invalid_type'));
        }

        [$realMime, $extension] = $match;

        $relativeDirectory = trim($subdirectory, '/');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destinationDir = self::storageBasePath() . '/' . $relativeDirectory;

        if (!is_dir($destinationDir)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $destination = $destinationDir . '/' . $filename;

        if (!EncodedUploads::moveUploaded($tmpPath, $destination)) {
            throw new RuntimeException(t('upload.processing_failed'));
        }

        $size = @filesize($destination);

        return [
            'path'      => $relativeDirectory . '/' . $filename,
            'mime_type' => $realMime,
            'size'      => $size !== false ? $size : (int) $file['size'],
        ];
    }

    /**
     * Les formats Office (docx/xlsx/pptx) et OpenDocument (odt) sont des
     * archives ZIP : selon la version de libmagic installée sur le serveur,
     * finfo peut renvoyer le type spécifique (constaté en local) OU se
     * rabattre sur "application/zip" générique. Dans ce second cas, on
     * n'accepte PAS le fichier sur la seule foi de son extension déclarée —
     * on ouvre l'archive et on vérifie la présence d'un marqueur
     * structurellement obligatoire pour le format prétendu :
     * - ODF : le fichier "mimetype" (obligatoire, premier de l'archive,
     *   non compressé) contient littéralement le type MIME — une convention
     *   du format, pas une déduction ;
     * - OOXML : un chemin interne précis existe pour chaque sous-format
     *   (word/document.xml, xl/workbook.xml, ppt/presentation.xml) — un zip
     *   quelconque renommé en .docx ne les contient pas.
     * Un zip qui ne correspond à aucun de ces marqueurs est rejeté : c'est
     * un zip, pas un document bureautique, quelle que soit son extension.
     *
     * @return array{0: string, 1: string}|null [mime_type, extension]
     */
    private static function resolveDocumentType(string $detectedMime, string $path): ?array
    {
        if (isset(self::ALLOWED_DOCUMENT_TYPES[$detectedMime])) {
            return [$detectedMime, self::ALLOWED_DOCUMENT_TYPES[$detectedMime]];
        }

        if ($detectedMime !== 'application/zip') {
            return null;
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return null;
        }

        $mimetypeEntry = $zip->getFromName('mimetype');

        if ($mimetypeEntry === 'application/vnd.oasis.opendocument.text') {
            $zip->close();

            return ['application/vnd.oasis.opendocument.text', 'odt'];
        }

        $officeMarkers = [
            'word/document.xml'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xl/workbook.xml'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt/presentation.xml' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        foreach ($officeMarkers as $marker => $mime) {
            if ($zip->locateName($marker) !== false) {
                $zip->close();

                return [$mime, self::ALLOWED_DOCUMENT_TYPES[$mime]];
            }
        }

        $zip->close();

        return null;
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
        // réellement passé par le mécanisme d'upload HTTP de PHP — ou
        // reconstitué pendant cette requête (App\Core\EncodedUploads).
        if (!EncodedUploads::isUploaded((string) $file['tmp_name'])) {
            throw new RuntimeException(t('upload.failed'));
        }

        if ((int) $file['size'] > $maxSizeBytes) {
            throw new RuntimeException(t('upload.too_large'));
        }
    }

    private static function readImage(string $path, string $mime): GdImage
    {
        // @ : ces fonctions GD peuvent émettre un warning PHP sur une image
        // par ailleurs valide et bien décodée (ex. "iCCP: known incorrect
        // sRGB profile" — un PNG exporté par un éditeur d'image avec un
        // profil ICC mal formé ; libpng le corrige et continue). Le handler
        // global (public/index.php) transforme tout warning en exception, ce
        // qui ferait échouer un upload par ailleurs valide pour un simple
        // avertissement de métadonnées. Le résultat reste vérifié juste
        // après (même principe que @getimagesize()/@exif_read_data()
        // ci-dessus/dessous).
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
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
