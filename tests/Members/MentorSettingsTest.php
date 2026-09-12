<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Déclaration de mentor (FIUCC-87). Member::updateMentorSettings() ne
 * touche jamais show_email/show_phone — leur seul chemin d'écriture reste
 * Member::updatePrivacySettings() (FIUCC-83), même si mentorSettings()
 * les lit pour affichage.
 */
final class MentorSettingsTest extends TestCase
{
    public function test_mentor_settings_returns_null_for_an_unknown_member(): void
    {
        self::assertNull(Member::mentorSettings(999999));
    }

    public function test_update_mentor_settings_persists_all_fields(): void
    {
        $memberId = $this->makeMember();

        Member::updateMentorSettings($memberId, [
            'is_mentor'           => 1,
            'mentor_field'        => 'Génie logiciel',
            'mentor_availability' => 'Le week-end',
            'mentor_bio'          => 'Quelques mots.',
        ]);

        $settings = Member::mentorSettings($memberId);

        self::assertSame(1, (int) $settings['is_mentor']);
        self::assertSame('Génie logiciel', $settings['mentor_field']);
        self::assertSame('Le week-end', $settings['mentor_availability']);
        self::assertSame('Quelques mots.', $settings['mentor_bio']);
    }

    public function test_update_mentor_settings_never_touches_privacy_flags(): void
    {
        $memberId = $this->makeMember();
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 1, 'show_phone' => 0]);

        Member::updateMentorSettings($memberId, [
            'is_mentor'           => 1,
            'mentor_field'        => 'Finance',
            'mentor_availability' => 'En soirée',
            'mentor_bio'          => null,
        ]);

        $privacy = Member::privacySettings($memberId);

        self::assertSame(1, (int) $privacy['is_public']);
        self::assertSame(1, (int) $privacy['show_email']);
        self::assertSame(0, (int) $privacy['show_phone']);
    }

    public function test_mentor_settings_reads_the_current_contact_channels(): void
    {
        $memberId = $this->makeMember();
        Member::updatePrivacySettings($memberId, ['is_public' => 0, 'show_email' => 1, 'show_phone' => 1]);

        $settings = Member::mentorSettings($memberId);

        self::assertSame(1, (int) $settings['show_email']);
        self::assertSame(1, (int) $settings['show_phone']);
    }
}
