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
}
