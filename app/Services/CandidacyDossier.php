<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use finfo;
use RuntimeException;

/**
 * Pièces du dossier de candidature (règlement électoral 2026, art. 14 ;
 * décisions du bureau 2026-10-10) et leur stockage privé.
 *
 * - 2 photos d'identité, 2 photos professionnelles (images), un CV (PDF),
 *   un plan d'action (PDF, obligatoire depuis la décision du bureau du
 *   2026-10-12) : obligatoires — en plus du plan d'action en texte,
 *   programme affiché publiquement ;
 * - preuve de paiement (image ou PDF) : seulement si l'élection exige des
 *   frais (elections.candidacy_fee_required).
 *
 * Type réel vérifié par finfo, jamais l'extension (CLAUDE.md, règle 7) ;
 * images ré-encodées par GD (Upload::storePrivateImage()), PDF vérifiés
 * comme tels. Tout sous storage/candidacies/, jamais public/.
 *
 * Avant que la candidature existe (premier dépôt), les pièces valides
 * sont gardées en attente pour la session — même principe que la photo
 * de passeport du recensement : une erreur ailleurs dans le formulaire ne
 * doit pas obliger à renvoyer six fichiers.
 */
final class CandidacyDossier
{
    /** type => [nombre d'emplacements, nature, obligatoire (true|false|'fee')] */
    public const PIECES = [
        'id_photo'      => [2, 'image', true],
        'pro_photo'     => [2, 'image', true],
        'cv'            => [1, 'pdf', true],
        'action_plan'   => [1, 'pdf', true],
        'payment_proof' => [1, 'image_or_pdf', 'fee'],
    ];

    private const DIR = 'candidacies';
    private const PENDING_DIR = 'candidacies/pending';
    private const SESSION_KEY = 'candidacy_pending_files';
    private const PENDING_TTL_SECONDS = 86400;

    /**
     * Champs de fichier du formulaire, dans l'ordre : "id_photo_1", ...
     *
     * @return array<string, array{type: string, slot: int, kind: string, required: bool|string}>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::PIECES as $type => [$slots, $kind, $required]) {
            for ($slot = 1; $slot <= $slots; $slot++) {
                $fields[$type . '_' . $slot] = ['type' => $type, 'slot' => $slot, 'kind' => $kind, 'required' => $required];
            }
        }

        return $fields;
    }

    /**
     * Champs obligatoires encore sans pièce.
     *
     * @param array<string, mixed> $present champ => pièce déjà reçue
     * @return list<string>
     */
    public static function missing(array $present, bool $feeRequired): array
    {
        $missing = [];

        foreach (self::fields() as $field => $definition) {
            $required = $definition['required'] === 'fee' ? $feeRequired : (bool) $definition['required'];

            if ($required && !isset($present[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Stocke une pièce reçue, selon sa nature attendue.
     *
     * @return array{path: string, mime: string}
     * @throws RuntimeException message affichable
     */
    public static function store(array $file, string $kind, bool $pending): array
    {
        self::ensureDirectories();

        $directory = $pending ? self::PENDING_DIR : self::DIR;
        $maxBytes = max(1, (int) Config::get('security.candidacy_file_max_size_mb', 8)) * 1024 * 1024;
        $tmp = (string) ($file['tmp_name'] ?? '');
        $detected = is_file($tmp) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : false;
        $isImage = in_array($detected, ['image/jpeg', 'image/png', 'image/webp'], true);

        if ($kind === 'image' || ($kind === 'image_or_pdf' && $isImage)) {
            $path = Upload::storePrivateImage(
                $file,
                $directory,
                (int) Config::get('security.candidacy_photo_max_dimension', 2000),
                $maxBytes,
                (int) Config::get('security.upload_max_input_dimension', 5000)
            );

            return ['path' => $path, 'mime' => (string) (new finfo(FILEINFO_MIME_TYPE))->file(Upload::storagePath($path))];
        }

        // PDF uniquement. Contrôle du type réel AVANT storeDocument(), pour
        // un message propre à ce champ (celui d'Upload parle de photos), puis
        // revérifié après (storeDocument() accepte aussi Word/Excel...).
        if ($detected !== 'application/pdf') {
            throw new RuntimeException(t($kind === 'pdf' ? 'candidacy.error.pdf_only' : 'candidacy.error.image_or_pdf'));
        }

        $stored = Upload::storeDocument($file, $directory, $maxBytes);

        if ($stored['mime_type'] !== 'application/pdf') {
            @unlink(Upload::storagePath($stored['path']));
            throw new RuntimeException(t($kind === 'pdf' ? 'candidacy.error.pdf_only' : 'candidacy.error.image_or_pdf'));
        }

        return ['path' => $stored['path'], 'mime' => 'application/pdf'];
    }

    /**
     * Pièce en attente déplacée vers son emplacement définitif.
     */
    public static function promote(string $pendingPath): string
    {
        $final = self::DIR . '/' . basename($pendingPath);
        rename(Upload::storagePath($pendingPath), Upload::storagePath($final));

        return $final;
    }

    /**
     * @return array<string, array{path: string, mime: string}> champ => pièce
     */
    public static function pending(int $electionId): array
    {
        $files = $_SESSION[self::SESSION_KEY][$electionId] ?? [];
        $valid = [];

        foreach (is_array($files) ? $files : [] as $field => $piece) {
            if (isset(self::fields()[$field], $piece['path'], $piece['mime'])
                && str_starts_with((string) $piece['path'], self::PENDING_DIR . '/')
                && is_file(Upload::storagePath((string) $piece['path']))) {
                $valid[$field] = ['path' => (string) $piece['path'], 'mime' => (string) $piece['mime']];
            }
        }

        $_SESSION[self::SESSION_KEY][$electionId] = $valid;

        return $valid;
    }

    public static function rememberPending(int $electionId, string $field, array $piece): void
    {
        $previous = self::pending($electionId)[$field] ?? null;

        if ($previous !== null) {
            @unlink(Upload::storagePath($previous['path']));
        }

        $_SESSION[self::SESSION_KEY][$electionId][$field] = $piece;
    }

    public static function forgetPending(int $electionId): void
    {
        unset($_SESSION[self::SESSION_KEY][$electionId]);
    }

    /**
     * Pièces en attente abandonnées (dépôt jamais terminé) : supprimées
     * après 24 h, comme les photos de passeport du recensement.
     */
    public static function purgeStalePending(): void
    {
        self::ensureDirectories();

        foreach (glob(Upload::storagePath(self::PENDING_DIR) . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - self::PENDING_TTL_SECONDS) {
                @unlink($file);
            }
        }
    }

    private static function ensureDirectories(): void
    {
        foreach ([self::DIR, self::PENDING_DIR] as $directory) {
            $path = Upload::storagePath($directory);

            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}
