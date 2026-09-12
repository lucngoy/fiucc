<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Demandes de mentorat (FIUCC-88, cahier — "un étudiant peut demander un
 * conseil ou une mise en relation"). Boucle minimale mais complète : un
 * étudiant demande, le mentor accepte ou décline, l'étudiant voit le
 * résultat — une demande qui ne peut jamais recevoir de réponse ne serait
 * pas vraiment "un système de demandes", juste un formulaire qui écrit
 * dans une table que personne ne lit. Pas de fil de discussion (le
 * schéma n'a qu'un seul champ message, pas une table de messages) : la
 * conversation continue hors de l'application une fois la mise en
 * relation acceptée, via le "mode de contact" du mentor
 * (show_email/show_phone, FIUCC-83/87).
 */
final class Mentorship extends Model
{
    /**
     * INSERT ... SELECT ... WHERE : revérifie à l'écriture, pas seulement
     * à l'affichage de /mentors, que le mentor est toujours réellement
     * disponible (même principe que Candidate::create() pour une
     * candidature) — un mentor a pu désactiver is_mentor ou is_public
     * entre le chargement de la page et la soumission du formulaire.
     * NOT EXISTS empêche une deuxième demande tant qu'une précédente
     * reste pending/accepted envers le même mentor — pas de spam, un
     * mentor décliné ou une mise en relation terminée (completed) peut
     * être redemandée.
     */
    public static function create(int $mentorId, int $studentId, ?string $topic, string $message): ?int
    {
        if ($mentorId === $studentId) {
            return null;
        }

        $stmt = static::db()->prepare(
            "INSERT INTO mentorships (mentor_id, student_id, topic, message, status)
             SELECT :mentor_id, :student_id, :topic, :message, 'pending'
             FROM members m
             WHERE m.id = :mentor_id_check
               AND m.status = 'validated' AND m.status_type = 'alumni'
               AND m.is_mentor = 1 AND m.is_public = 1
               AND NOT EXISTS (
                   SELECT 1 FROM mentorships existing
                   WHERE existing.mentor_id = :mentor_id_check2
                     AND existing.student_id = :student_id_check
                     AND existing.status IN ('pending', 'accepted')
               )"
        );
        $stmt->execute([
            'mentor_id'         => $mentorId,
            'student_id'        => $studentId,
            'topic'             => $topic,
            'message'           => $message,
            'mentor_id_check'   => $mentorId,
            'mentor_id_check2'  => $mentorId,
            'student_id_check'  => $studentId,
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) static::db()->lastInsertId();
    }

    /**
     * $mentorId revérifié dans le WHERE, pas seulement passé en
     * confiance : un mentor ne peut répondre qu'à ses propres demandes,
     * jamais à celle d'un autre en devinant un id — même discipline
     * IDOR-safe que le reste du projet. status = 'pending' dans le WHERE
     * empêche de re-répondre à une demande déjà tranchée.
     */
    public static function respond(int $mentorshipId, int $mentorId, string $status): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE mentorships SET status = :status
             WHERE id = :id AND mentor_id = :mentor_id AND status = 'pending'"
        );
        $stmt->execute(['status' => $status, 'id' => $mentorshipId, 'mentor_id' => $mentorId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Demandes reçues par un mentor — jamais les coordonnées de
     * l'étudiant : rien dans le cahier ne prévoit que l'étudiant partage
     * son contact avant même que le mentor ait accepté, contrairement au
     * sens inverse (mentor → étudiant, une fois accepté).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function incomingForMentor(int $mentorId): array
    {
        $stmt = static::db()->prepare(
            "SELECT ms.id, ms.topic, ms.message, ms.status, ms.created_at,
                    m.full_name AS student_name, m.photo AS student_photo
             FROM mentorships ms
             INNER JOIN members m ON m.id = ms.student_id
             WHERE ms.mentor_id = :mentor_id
             ORDER BY ms.created_at DESC"
        );
        $stmt->execute(['mentor_id' => $mentorId]);

        return $stmt->fetchAll();
    }

    /**
     * Demandes envoyées par un étudiant — le contact du mentor
     * (email/téléphone) n'apparaît que si la demande est acceptée ET que
     * le mentor a lui-même activé ce canal (CASE WHEN, même principe que
     * Candidate::publicListForElection()/Member::directoryList()) :
     * jamais déduit du statut de la demande seul, toujours revérifié
     * contre le réglage actuel du mentor au moment de la lecture.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sentByStudent(int $studentId): array
    {
        $stmt = static::db()->prepare(
            "SELECT ms.id, ms.mentor_id, ms.topic, ms.message, ms.status, ms.created_at,
                    m.full_name AS mentor_name, m.photo AS mentor_photo,
                    m.mentor_field, m.mentor_availability,
                    CASE WHEN ms.status = 'accepted' AND m.show_email = 1 THEN u.email ELSE NULL END AS mentor_email,
                    CASE WHEN ms.status = 'accepted' AND m.show_phone = 1 THEN m.phone ELSE NULL END AS mentor_phone
             FROM mentorships ms
             INNER JOIN members m ON m.id = ms.mentor_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE ms.student_id = :student_id
             ORDER BY ms.created_at DESC"
        );
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchAll();
    }
}
