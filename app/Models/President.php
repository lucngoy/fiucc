<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Présidents de la RDC (migration 030), page /community/presidents et
 * aperçu sur /community. Gérés depuis /admin/presidents.
 */
final class President extends Model
{
    public const LICENSES = ['public_domain', 'cc0', 'cc_by_4', 'cc_by_sa_4', 'permission'];

    /** Licences qui imposent un lien vers leur texte dans le crédit. */
    public const LICENSE_URLS = [
        'cc_by_4'    => 'https://creativecommons.org/licenses/by/4.0/',
        'cc_by_sa_4' => 'https://creativecommons.org/licenses/by-sa/4.0/',
    ];

    /**
     * Tous, du plus récent au plus ancien : l'ordre de la page publique.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return static::db()->query(
            'SELECT * FROM presidents ORDER BY from_year DESC, id DESC'
        )->fetchAll();
    }

    /**
     * Présidents publiés, du plus récent au plus ancien, dans la langue
     * demandée (la traduction anglaise si elle existe, sinon le français).
     * 'rank' : rang chronologique (1 = Kasa-Vubu), parmi les publiés.
     *
     * @return list<array<string, mixed>>
     */
    public static function publishedFor(string $locale): array
    {
        $rows = static::db()->query(
            'SELECT * FROM presidents WHERE is_published = 1 ORDER BY from_year DESC, id DESC'
        )->fetchAll();

        $count = count($rows);

        return array_map(static function (array $row, int $index) use ($locale, $count): array {
            $english = $locale === 'en';

            return [
                'name'      => (string) $row['name'],
                'role'      => (string) ($english && ($row['role_en'] ?? '') !== '' ? $row['role_en'] : $row['role']),
                'bio'       => (string) ($english && ($row['bio_en'] ?? '') !== '' ? $row['bio_en'] : $row['bio']),
                'from'      => (int) $row['from_year'],
                'until'     => $row['until_year'] === null ? null : (int) $row['until_year'],
                'photo_url' => self::photoUrl($row),
                'credit'    => [
                    'author'      => $row['credit_author'],
                    'license'     => $row['credit_license'],
                    'license_url' => self::LICENSE_URLS[$row['credit_license'] ?? ''] ?? null,
                    'source'      => $row['credit_source'],
                ],
                'rank'      => $count - $index,
            ];
        }, $rows, array_keys($rows));
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM presidents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Président en exercice (until_year NULL) autre que $exceptId.
     */
    public static function currentOtherThan(?int $exceptId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM presidents WHERE until_year IS NULL AND id <> :id ORDER BY from_year DESC LIMIT 1'
        );
        $stmt->execute(['id' => $exceptId ?? 0]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Ferme le mandat d'un président (passation : son successeur vient
     * d'être enregistré en exercice).
     */
    public static function endTerm(int $id, int $untilYear): void
    {
        $stmt = static::db()->prepare('UPDATE presidents SET until_year = :until_year WHERE id = :id');
        $stmt->execute(['until_year' => $untilYear, 'id' => $id]);
    }

    /**
     * @param array<string, mixed> $data Champs de self::fields(), plus 'photo'.
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO presidents (name, role, role_en, bio, bio_en, from_year, until_year, photo,
                 credit_author, credit_license, credit_source, is_published)
             VALUES (:name, :role, :role_en, :bio, :bio_en, :from_year, :until_year, :photo,
                 :credit_author, :credit_license, :credit_source, :is_published)'
        );
        $stmt->execute(self::fields($data) + ['photo' => $data['photo']]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Le portrait n'est changé que par updatePhoto(), et seulement si
     * l'admin en envoie un nouveau (même principe que Partner::update()).
     */
    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE presidents SET name = :name, role = :role, role_en = :role_en, bio = :bio, bio_en = :bio_en,
                 from_year = :from_year, until_year = :until_year, credit_author = :credit_author,
                 credit_license = :credit_license, credit_source = :credit_source, is_published = :is_published
             WHERE id = :id'
        );
        $stmt->execute(self::fields($data) + ['id' => $id]);
    }

    public static function updatePhoto(int $id, string $photo): void
    {
        $stmt = static::db()->prepare('UPDATE presidents SET photo = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photo, 'id' => $id]);
    }

    /**
     * Le fichier du portrait n'est pas géré par SQL : à l'appelant de le
     * supprimer (voir Admin\PresidentController::delete()).
     */
    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM presidents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * URL du portrait, avec numéro de version pour ceux livrés avec le
     * code (asset()). null sans photo.
     */
    public static function photoUrl(array $row): ?string
    {
        $photo = (string) ($row['photo'] ?? '');

        return $photo === '' ? null : asset($photo);
    }

    /**
     * Fichier d'un portrait envoyé par l'admin, à supprimer avec lui ; null
     * pour ceux livrés avec le code (/assets), qui ne s'effacent jamais.
     */
    public static function uploadedFile(array $row): ?string
    {
        $photo = (string) ($row['photo'] ?? '');

        return str_starts_with($photo, '/uploads/') ? BASE_PATH . '/public' . $photo : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fields(array $data): array
    {
        return [
            'name'           => $data['name'],
            'role'           => $data['role'],
            'role_en'        => $data['role_en'],
            'bio'            => $data['bio'],
            'bio_en'         => $data['bio_en'],
            'from_year'      => $data['from_year'],
            'until_year'     => $data['until_year'],
            'credit_author'  => $data['credit_author'],
            'credit_license' => $data['credit_license'],
            'credit_source'  => $data['credit_source'],
            'is_published'   => $data['is_published'],
        ];
    }
}
