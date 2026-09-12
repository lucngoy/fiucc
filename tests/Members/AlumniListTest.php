<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Réseau alumni (FIUCC-86) : mêmes garanties que DirectoryListTest — un
 * alumni resté privé n'apparaît nulle part, et seul status_type = 'alumni'
 * est concerné (les student et honorary n'y figurent pas, même publics).
 */
final class AlumniListTest extends TestCase
{
    public function test_a_private_alumnus_never_appears(): void
    {
        $publicId = $this->makeMember(['full_name' => 'Alumni Public']);
        Member::markAlumni($publicId);
        Member::updatePrivacySettings($publicId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $privateId = $this->makeMember(['full_name' => 'Alumni Prive']);
        Member::markAlumni($privateId);
        Member::updatePrivacySettings($privateId, ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::alumniList([], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertContains('Alumni Public', $names);
        self::assertNotContains('Alumni Prive', $names);
    }

    public function test_a_public_current_student_does_not_appear(): void
    {
        $studentId = $this->makeMember(['full_name' => 'Etudiant Public']);
        Member::updatePrivacySettings($studentId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::alumniList([], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertNotContains('Etudiant Public', $names);
    }

    public function test_search_filters_by_name(): void
    {
        $matchId = $this->makeMember(['full_name' => 'Alumni Recherche Unique']);
        Member::markAlumni($matchId);
        Member::updatePrivacySettings($matchId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $otherId = $this->makeMember(['full_name' => 'Autre Alumni']);
        Member::markAlumni($otherId);
        Member::updatePrivacySettings($otherId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $result = Member::alumniList(['search' => 'Recherche Unique'], 1);
        $names = array_column($result['rows'], 'full_name');

        self::assertContains('Alumni Recherche Unique', $names);
        self::assertNotContains('Autre Alumni', $names);
    }
}
