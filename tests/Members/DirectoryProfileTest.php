<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Profil public d'un membre (FIUCC-84) : un id de membre existant mais
 * privé doit produire exactement la même réponse qu'un id inexistant —
 * Member::directoryProfile() ne doit jamais laisser un appelant distinguer
 * les deux cas, même principe que DocumentController::download() pour un
 * document restreint. C'est l'assertion qui compte le plus pour ce ticket.
 */
final class DirectoryProfileTest extends TestCase
{
    public function test_an_unknown_id_returns_null(): void
    {
        self::assertNull(Member::directoryProfile(999999));
    }

    public function test_a_private_member_returns_null_even_though_the_id_is_real(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre Prive']);
        Member::updatePrivacySettings($memberId, ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0]);

        self::assertNull(Member::directoryProfile($memberId));
    }

    public function test_a_public_but_pending_member_returns_null(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre En Attente', 'status' => 'pending']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        self::assertNull(Member::directoryProfile($memberId));
    }

    public function test_a_validated_public_member_is_returned(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre Visible']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 0, 'show_phone' => 0]);

        $profile = Member::directoryProfile($memberId);

        self::assertNotNull($profile);
        self::assertSame('Membre Visible', $profile['full_name']);
    }

    public function test_the_profile_never_carries_student_id_email_or_phone(): void
    {
        $memberId = $this->makeMember(['full_name' => 'Membre Visible']);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 1, 'show_phone' => 1]);

        $profile = Member::directoryProfile($memberId);

        self::assertArrayNotHasKey('student_id', $profile);
        self::assertArrayNotHasKey('email', $profile);
        self::assertArrayNotHasKey('phone', $profile);
    }
}
