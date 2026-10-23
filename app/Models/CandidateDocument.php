<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Pièces d'un dossier de candidature (migration 024 ; règlement électoral
 * art. 14). Fichiers privés sous storage/candidacies/, lisibles par l'admin
 * seul (Admin\CandidateController::document()), conservés sans limite de
 * durée (art. 34). Une pièce par (candidat, type, emplacement) : déposer à
 * nouveau remplace la précédente.
 */
final class CandidateDocument extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forCandidate(int $candidateId): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, type, slot, file_path, mime_type, uploaded_at
             FROM candidate_documents WHERE candidate_id = :candidate_id
             ORDER BY FIELD(type, \'id_photo\', \'pro_photo\', \'cv\', \'action_plan\', \'payment_proof\'), slot'
        );
        $stmt->execute(['candidate_id' => $candidateId]);

        return $stmt->fetchAll();
    }

    public static function find(int $id, int $candidateId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM candidate_documents WHERE id = :id AND candidate_id = :candidate_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'candidate_id' => $candidateId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Ajoute ou remplace la pièce d'un emplacement.
     *
     * @return string|null chemin de l'ancienne pièce remplacée (à supprimer
     *         du disque par l'appelant), null si l'emplacement était vide
     */
    public static function put(int $candidateId, string $type, int $slot, string $filePath, string $mimeType): ?string
    {
        $stmt = static::db()->prepare(
            'SELECT file_path FROM candidate_documents WHERE candidate_id = :candidate_id AND type = :type AND slot = :slot'
        );
        $stmt->execute(['candidate_id' => $candidateId, 'type' => $type, 'slot' => $slot]);
        $old = $stmt->fetchColumn();

        static::db()->prepare(
            'INSERT INTO candidate_documents (candidate_id, type, slot, file_path, mime_type)
             VALUES (:candidate_id, :type, :slot, :file_path, :mime_type)
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), mime_type = VALUES(mime_type), uploaded_at = NOW()'
        )->execute([
            'candidate_id' => $candidateId,
            'type'         => $type,
            'slot'         => $slot,
            'file_path'    => $filePath,
            'mime_type'    => $mimeType,
        ]);

        return $old !== false ? (string) $old : null;
    }
}
