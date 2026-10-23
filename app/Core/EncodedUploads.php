<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Fichiers envoyés encodés en base64 dans un formulaire classique
 * (application/x-www-form-urlencoded), reconstitués en uploads ordinaires
 * avant le routage.
 *
 * Pourquoi (2026-10-21) : le pare-feu d'InfinityFree répond 403
 * « openresty » à tout envoi multipart/form-data contenant un fichier,
 * avant même PHP — démontré par une page de test hors du portail. Le même
 * fichier, converti en texte par le navigateur (public/assets/js/
 * encoded-uploads.js), passe. Les contrôleurs n'en savent rien : $_FILES
 * a la même forme qu'après un upload multipart, et tous les contrôles
 * restent ceux d'App\Services\Upload (type réel par finfo, ré-encodage GD,
 * tailles) — jamais le nom ni le type annoncés par le navigateur.
 *
 * is_uploaded_file()/move_uploaded_file() refusent ces fichiers : ils
 * n'ont pas été écrits par le mécanisme d'upload de PHP. isUploaded() et
 * moveUploaded() les remplacent, et n'acceptent en plus QUE les fichiers
 * temporaires créés ici pendant la requête — jamais un chemin arbitraire
 * (la défense que garantissait is_uploaded_file()).
 */
final class EncodedUploads
{
    public const FIELD = '__encoded_files';
    private const MAX_FILES = 30;

    /** @var array<string, true> fichiers temporaires créés pendant cette requête */
    private static array $paths = [];
    private static bool $cleanupRegistered = false;

    /**
     * $_POST[FIELD][] = JSON {field, name, data} -> $_FILES. Un champ déjà
     * rempli par un vrai upload multipart n'est jamais écrasé.
     */
    public static function hydrate(): void
    {
        $items = $_POST[self::FIELD] ?? null;
        unset($_POST[self::FIELD]);

        if (!is_array($items)) {
            return;
        }

        $maxBytes = self::iniBytes((string) ini_get('upload_max_filesize'));
        $received = [];

        foreach (array_slice(array_values($items), 0, self::MAX_FILES) as $item) {
            $decoded = is_string($item) ? json_decode($item, true) : null;

            if (!is_array($decoded) || preg_match('/^([A-Za-z][A-Za-z0-9_]{0,63})(\[\])?$/', (string) ($decoded['field'] ?? ''), $m) !== 1) {
                continue;
            }

            [$field, $isList] = [$m[1], isset($m[2])];

            if (isset($_FILES[$field])) {
                continue;
            }

            $received[$field]['list'] = $isList;
            $received[$field]['files'][] = self::store((string) ($decoded['name'] ?? ''), (string) ($decoded['data'] ?? ''), $maxBytes);
        }

        foreach ($received as $field => ['list' => $isList, 'files' => $files]) {
            if (!$isList) {
                $_FILES[$field] = $files[0];
                continue;
            }

            // Même forme qu'un <input type="file" name="x[]" multiple> :
            // une clé par propriété, chacune indexée par fichier.
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $_FILES[$field][$key] = array_column($files, $key);
            }
        }
    }

    /** Remplace is_uploaded_file() : vrai upload PHP, ou fichier reconstitué ici. */
    public static function isUploaded(string $path): bool
    {
        return is_uploaded_file($path) || isset(self::$paths[$path]);
    }

    /** Remplace move_uploaded_file(), avec la même restriction. */
    public static function moveUploaded(string $from, string $to): bool
    {
        if (is_uploaded_file($from)) {
            return move_uploaded_file($from, $to);
        }

        if (!isset(self::$paths[$from]) || !rename($from, $to)) {
            return false;
        }

        unset(self::$paths[$from]);

        return true;
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private static function store(string $name, string $data, int $maxBytes): array
    {
        $name = mb_substr(basename(str_replace('\\', '/', $name)), 0, 255);
        $failed = static fn (int $error, int $size = 0): array => ['name' => $name, 'type' => '', 'tmp_name' => '', 'error' => $error, 'size' => $size];

        // Taille vérifiée avant de décoder : 4 caractères base64 = 3 octets.
        if ($maxBytes > 0 && intdiv(strlen($data), 4) * 3 > $maxBytes + 3) {
            return $failed(UPLOAD_ERR_INI_SIZE, intdiv(strlen($data), 4) * 3);
        }

        $bytes = base64_decode($data, true);

        if ($bytes === false || $bytes === '') {
            return $failed(UPLOAD_ERR_PARTIAL);
        }

        if ($maxBytes > 0 && strlen($bytes) > $maxBytes) {
            return $failed(UPLOAD_ERR_INI_SIZE, strlen($bytes));
        }

        $directory = self::temporaryDirectory();
        $path = $directory !== null ? $directory . '/fiucc_upload_' . bin2hex(random_bytes(16)) : null;
        $handle = $path !== null ? @fopen($path, 'xb') : false;

        if ($handle === false || fwrite($handle, $bytes) !== strlen($bytes)) {
            if ($handle !== false) {
                fclose($handle);
                @unlink((string) $path);
            }

            return $failed(UPLOAD_ERR_CANT_WRITE);
        }

        fclose($handle);

        self::$paths[$path] = true;
        self::registerCleanup();

        // 'type' laissé vide : jamais utilisé (CLAUDE.md, règle 7 — finfo seul fait foi).
        return ['name' => $name, 'type' => '', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    /**
     * Le dossier où PHP écrit ses propres uploads, à défaut le dossier
     * temporaire du système, à défaut storage/cache — le premier
     * inscriptible. (tempnam() change de dossier en silence avec un
     * avertissement, que le gestionnaire d'erreurs global transforme en
     * exception.)
     */
    private static function temporaryDirectory(): ?string
    {
        foreach ([(string) ini_get('upload_tmp_dir'), sys_get_temp_dir(), BASE_PATH . '/storage/cache'] as $directory) {
            $directory = rtrim($directory, '/');

            if ($directory !== '' && is_dir($directory) && is_writable($directory)) {
                return $directory;
            }
        }

        return null;
    }

    /** Comme PHP pour ses propres uploads : rien ne survit à la requête. */
    private static function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;
        register_shutdown_function(static function (): void {
            foreach (array_keys(self::$paths) as $path) {
                @unlink($path);
            }

            self::$paths = [];
        });
    }

    /** "20M" -> octets (notation php.ini). */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
