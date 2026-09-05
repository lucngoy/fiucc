<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Member extends Model
{
    public static function studentIdExists(string $studentId): bool
    {
        $stmt = static::db()->prepare('SELECT 1 FROM members WHERE student_id = :student_id LIMIT 1');
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Recensement (FIUCC-32) : status 'pending' (validation admin à venir,
     * section 24 du cahier des charges) et email_verified_at NULL (OTP à
     * venir, FIUCC-33/34) — deux étapes indépendantes du cycle de vie.
     *
     * membership_number est généré après l'insertion à partir de l'id auto-
     * incrémenté (FIUCC-2026-00042), pas avant : la colonne est NOT NULL et
     * UNIQUE, donc un placeholder temporaire garantit l'unicité sans risque
     * de collision entre deux inscriptions concurrentes.
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO members (
                user_id, membership_number, full_name, student_id, date_of_birth, gender,
                city_of_origin, faculty, department, year, phone, whatsapp, instagram,
                status_type, status, is_public, show_email, show_phone
            ) VALUES (
                :user_id, :membership_number, :full_name, :student_id, :date_of_birth, :gender,
                :city_of_origin, :faculty, :department, :year, :phone, :whatsapp, :instagram,
                'student', 'pending', :is_public, :show_email, :show_phone
            )"
        );

        $stmt->execute([
            'user_id'         => $data['user_id'],
            'membership_number' => 'TMP-' . bin2hex(random_bytes(8)),
            'full_name'       => $data['full_name'],
            'student_id'      => $data['student_id'],
            'date_of_birth'   => $data['date_of_birth'],
            'gender'          => $data['gender'],
            'city_of_origin'  => $data['city_of_origin'],
            'faculty'         => $data['faculty'],
            'department'      => $data['department'],
            'year'            => $data['year'],
            'phone'           => $data['phone'],
            'whatsapp'        => $data['whatsapp'],
            'instagram'       => $data['instagram'],
            'is_public'       => $data['is_public'],
            'show_email'      => $data['show_email'],
            'show_phone'      => $data['show_phone'],
        ]);

        $memberId = (int) static::db()->lastInsertId();

        $update = static::db()->prepare('UPDATE members SET membership_number = :number WHERE id = :id');
        $update->execute([
            'number' => sprintf('FIUCC-%s-%05d', date('Y'), $memberId),
            'id'     => $memberId,
        ]);

        return $memberId;
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, full_name, email_verified_at FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function markEmailVerified(int $userId): void
    {
        $stmt = static::db()->prepare('UPDATE members SET email_verified_at = NOW() WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Cycle de statut (section 24 du cahier des charges) : pending, validated,
     * rejected, suspended. Aucune contrainte de transition entre eux — le
     * cahier liste ces trois actions comme des capacités indépendantes de
     * l'admin, pas un enchaînement strict. validate()/reject()/suspend()
     * effacent chacune les raisons de l'état négatif qu'elles remplacent,
     * pour qu'une ancienne raison de rejet ne survive pas à une validation.
     *
     * $adminUserId n'est jamais l'auteur de sa propre validation — c'est
     * toujours un compte admin distinct du membre concerné (appliqué par le
     * futur contrôleur d'administration, FIUCC-44 ; pas revérifié ici, ce
     * modèle ne connaît pas la notion de session).
     */
    public static function validate(int $memberId, int $adminUserId): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'validated', rejection_reason = NULL, suspension_reason = NULL,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['admin_id' => $adminUserId, 'id' => $memberId]);
    }

    public static function reject(int $memberId, int $adminUserId, string $reason): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'rejected', rejection_reason = :reason, suspension_reason = NULL,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['reason' => $reason, 'admin_id' => $adminUserId, 'id' => $memberId]);
    }

    public static function suspend(int $memberId, int $adminUserId, ?string $reason = null): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'suspended', suspension_reason = :reason,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['reason' => $reason, 'admin_id' => $adminUserId, 'id' => $memberId]);
    }

    /**
     * Droit de vote (CLAUDE.md, règle non négociable) : status='validated'
     * ET status_type='student' — un alumni reste membre sans être électeur.
     * Point d'entrée unique pour ne pas réimplémenter cette règle ailleurs
     * (élections, phase 4).
     */
    public static function isVoterEligible(array $member): bool
    {
        return ($member['status'] ?? null) === 'validated'
            && ($member['status_type'] ?? null) === 'student';
    }

    public static function findByUserIdWithStatus(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, full_name, status, status_type FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Vue complète d'un membre pour sa propre page profil (FIUCC-37) : la
     * restriction "ne jamais afficher publiquement" (section 15 du cahier —
     * student_id, email, téléphone) ne s'applique qu'au profil PUBLIC
     * (FIUCC-84, annuaire), pas ici — un membre voit toujours ses propres
     * données. is_public/show_email/show_phone/mentor_* et photo (upload)
     * appartiennent à l'édition du profil (FIUCC-38), pas à cet affichage.
     */
    public static function findFullByUserId(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, membership_number, full_name, student_id, date_of_birth, gender,
                    city_of_origin, faculty, department, year, arrival_year, graduation_year,
                    phone, whatsapp, instagram, status_type, profession, company, city, country,
                    status, rejection_reason, suspension_reason, email_verified_at, created_at
             FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
