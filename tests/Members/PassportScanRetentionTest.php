<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use App\Services\PassportScanRetention;
use App\Services\Upload;
use Tests\TestCase;

/**
 * Conservation de la photo du passeport (décision 2026-10-09) : supprimée
 * 90 jours après la dernière décision admin, jamais tant que le dossier est
 * en attente. Fichiers écrits sous le dossier de stockage de TEST
 * (UPLOAD_STORAGE_BASE_PATH, .env.testing), jamais le vrai storage/.
 */
final class PassportScanRetentionTest extends TestCase
{
    /**
     * Membre avec une photo réelle sur disque et une décision datée de
     * $daysAgo jours (validated_at), dans le statut donné.
     *
     * @return array{0: int, 1: string} [member.id, chemin absolu de la photo]
     */
    private function memberWithScan(string $status, ?int $daysAgo): array
    {
        $memberId = $this->makeMember(['status' => $status]);
        $relative = 'passports/' . bin2hex(random_bytes(16)) . '.jpg';
        $absolute = Upload::storagePath($relative);

        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        file_put_contents($absolute, 'test');
        Member::updatePassportScan($memberId, $relative);

        $stmt = $this->db->prepare(
            'UPDATE members SET validated_at = ' . ($daysAgo === null ? 'NULL' : 'DATE_SUB(NOW(), INTERVAL :days DAY)') . ' WHERE id = :id'
        );
        $stmt->execute($daysAgo === null ? ['id' => $memberId] : ['days' => $daysAgo, 'id' => $memberId]);

        return [$memberId, $absolute];
    }

    private function scanPath(int $memberId): ?string
    {
        $stmt = $this->db->prepare('SELECT passport_scan FROM members WHERE id = :id');
        $stmt->execute(['id' => $memberId]);

        return $stmt->fetchColumn() ?: null;
    }

    public function test_a_scan_older_than_90_days_after_the_decision_is_deleted(): void
    {
        [$validated, $validatedFile] = $this->memberWithScan('validated', 91);
        [$rejected, $rejectedFile] = $this->memberWithScan('rejected', 120);

        self::assertSame(2, PassportScanRetention::purgeExpired());

        self::assertNull($this->scanPath($validated));
        self::assertNull($this->scanPath($rejected));
        self::assertFileDoesNotExist($validatedFile);
        self::assertFileDoesNotExist($rejectedFile);
    }

    public function test_a_recent_decision_keeps_the_scan(): void
    {
        [$memberId, $file] = $this->memberWithScan('validated', 89);

        self::assertSame(0, PassportScanRetention::purgeExpired());
        self::assertNotNull($this->scanPath($memberId));
        self::assertFileExists($file);

        @unlink($file);
    }

    public function test_a_pending_record_is_never_purged_even_if_old(): void
    {
        // Dossier renvoyé après un refus ancien : validated_at est vieux,
        // mais la photo sert encore à la prochaine décision.
        [$memberId, $file] = $this->memberWithScan('pending', 200);

        self::assertSame(0, PassportScanRetention::purgeExpired());
        self::assertNotNull($this->scanPath($memberId));

        @unlink($file);
    }

    public function test_the_deletion_date_is_90_days_after_the_decision(): void
    {
        $member = ['passport_scan' => 'passports/x.jpg', 'status' => 'validated', 'validated_at' => '2026-10-01 10:00:00'];

        self::assertSame('2026-12-30', PassportScanRetention::deletionDate($member));
        self::assertNull(PassportScanRetention::deletionDate(['status' => 'pending'] + $member));
    }
}
