<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Passage en alumni (FIUCC-85) : manuel (Member::markAlumni()) et
 * automatique par année de diplôme (Member::promoteGraduatedStudents(),
 * bin/promote_alumni.php). Ce qui compte le plus : un membre devenu
 * alumni perd immédiatement son éligibilité au vote
 * (Member::isVoterEligible()), sans que status (validated) ne change.
 */
final class AlumniStatusTest extends TestCase
{
    public function test_mark_alumni_changes_status_type_but_not_status(): void
    {
        $memberId = $this->makeMember();

        Member::markAlumni($memberId);

        $stmt = $this->db->prepare('SELECT status, status_type FROM members WHERE id = :id');
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();

        self::assertSame('validated', $row['status']);
        self::assertSame('alumni', $row['status_type']);
    }

    public function test_an_alumni_member_is_no_longer_voter_eligible(): void
    {
        $memberId = $this->makeMember();
        Member::markAlumni($memberId);

        $stmt = $this->db->prepare('SELECT status, status_type FROM members WHERE id = :id');
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();

        self::assertFalse(Member::isVoterEligible($row));
    }

    public function test_promote_graduated_students_only_affects_validated_students_past_their_graduation_year(): void
    {
        $pastYear = (int) date('Y') - 1;
        $futureYear = (int) date('Y') + 1;

        $graduatedId = $this->makeMember(['full_name' => 'Diplome']);
        $this->setGraduationYear($graduatedId, $pastYear);

        $notYetId = $this->makeMember(['full_name' => 'Pas Encore']);
        $this->setGraduationYear($notYetId, $futureYear);

        $pendingId = $this->makeMember(['full_name' => 'En Attente', 'status' => 'pending']);
        $this->setGraduationYear($pendingId, $pastYear);

        $noYearId = $this->makeMember(['full_name' => 'Sans Annee']);

        $promoted = Member::promoteGraduatedStudents();

        self::assertSame(1, $promoted);
        self::assertSame('alumni', $this->statusTypeOf($graduatedId));
        self::assertSame('student', $this->statusTypeOf($notYetId));
        self::assertSame('student', $this->statusTypeOf($pendingId));
        self::assertSame('student', $this->statusTypeOf($noYearId));
    }

    public function test_promote_graduated_students_is_idempotent(): void
    {
        $memberId = $this->makeMember();
        $this->setGraduationYear($memberId, (int) date('Y') - 1);

        Member::promoteGraduatedStudents();
        $secondRunCount = Member::promoteGraduatedStudents();

        self::assertSame(0, $secondRunCount);
    }

    private function setGraduationYear(int $memberId, int $year): void
    {
        $stmt = $this->db->prepare('UPDATE members SET graduation_year = :year WHERE id = :id');
        $stmt->execute(['year' => $year, 'id' => $memberId]);
    }

    private function statusTypeOf(int $memberId): string
    {
        $stmt = $this->db->prepare('SELECT status_type FROM members WHERE id = :id');
        $stmt->execute(['id' => $memberId]);

        return (string) $stmt->fetchColumn();
    }
}
