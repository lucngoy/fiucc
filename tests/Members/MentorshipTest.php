<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use App\Models\Mentorship;
use Tests\TestCase;

/**
 * Demandes de mentorat (FIUCC-88). Ce qui compte le plus : la boucle
 * complète reste correcte même si un mentor change d'avis entre le
 * chargement de la page et la soumission (create() revérifie tout côté
 * base), et le contact du mentor ne fuite jamais avant acceptation.
 */
final class MentorshipTest extends TestCase
{
    private function makeMentor(array $overrides = []): int
    {
        $memberId = $this->makeMember($overrides);
        Member::markAlumni($memberId);
        Member::updateMentorSettings($memberId, [
            'is_mentor'           => 1,
            'mentor_field'        => 'Génie logiciel',
            'mentor_availability' => 'Le week-end',
            'mentor_bio'          => null,
        ]);
        Member::updatePrivacySettings($memberId, ['is_public' => 1, 'show_email' => 1, 'show_phone' => 0]);

        return $memberId;
    }

    public function test_a_request_to_an_available_mentor_succeeds(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();

        $id = Mentorship::create($mentorId, $studentId, 'Stage', 'Un message.');

        self::assertNotNull($id);
    }

    public function test_a_request_to_a_non_mentor_fails(): void
    {
        $notAMentorId = $this->makeMember();
        $studentId = $this->makeMember();

        $id = Mentorship::create($notAMentorId, $studentId, null, 'Un message.');

        self::assertNull($id);
    }

    public function test_a_mentor_cannot_request_themselves(): void
    {
        $mentorId = $this->makeMentor();

        $id = Mentorship::create($mentorId, $mentorId, null, 'Un message.');

        self::assertNull($id);
    }

    public function test_a_second_pending_request_to_the_same_mentor_is_refused(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();

        $first = Mentorship::create($mentorId, $studentId, null, 'Premier message.');
        $second = Mentorship::create($mentorId, $studentId, null, 'Deuxième message.');

        self::assertNotNull($first);
        self::assertNull($second);
    }

    public function test_a_declined_mentor_can_be_requested_again(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();

        $first = Mentorship::create($mentorId, $studentId, null, 'Premier message.');
        Mentorship::respond($first, $mentorId, 'declined');

        $second = Mentorship::create($mentorId, $studentId, null, 'Deuxième message.');

        self::assertNotNull($second);
    }

    public function test_a_mentor_can_only_respond_to_their_own_requests(): void
    {
        $mentorId = $this->makeMentor();
        $otherMentorId = $this->makeMentor();
        $studentId = $this->makeMember();

        $requestId = Mentorship::create($mentorId, $studentId, null, 'Un message.');

        $success = Mentorship::respond($requestId, $otherMentorId, 'accepted');

        self::assertFalse($success);
    }

    public function test_a_request_cannot_be_responded_to_twice(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();
        $requestId = Mentorship::create($mentorId, $studentId, null, 'Un message.');

        $firstResponse = Mentorship::respond($requestId, $mentorId, 'accepted');
        $secondResponse = Mentorship::respond($requestId, $mentorId, 'declined');

        self::assertTrue($firstResponse);
        self::assertFalse($secondResponse);
    }

    public function test_mentor_contact_is_hidden_until_the_request_is_accepted(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();
        Mentorship::create($mentorId, $studentId, null, 'Un message.');

        $sent = Mentorship::sentByStudent($studentId);

        self::assertSame('pending', $sent[0]['status']);
        self::assertNull($sent[0]['mentor_email']);
        self::assertNull($sent[0]['mentor_phone']);
    }

    public function test_mentor_contact_appears_once_accepted_if_the_channel_is_enabled(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();
        $requestId = Mentorship::create($mentorId, $studentId, null, 'Un message.');
        Mentorship::respond($requestId, $mentorId, 'accepted');

        $sent = Mentorship::sentByStudent($studentId);

        self::assertSame('accepted', $sent[0]['status']);
        self::assertNotNull($sent[0]['mentor_email']);
        self::assertNull($sent[0]['mentor_phone']);
    }

    public function test_incoming_requests_never_carry_student_contact_info(): void
    {
        $mentorId = $this->makeMentor();
        $studentId = $this->makeMember();
        Mentorship::create($mentorId, $studentId, null, 'Un message.');

        $incoming = Mentorship::incomingForMentor($mentorId);

        self::assertArrayNotHasKey('student_email', $incoming[0]);
        self::assertArrayNotHasKey('student_phone', $incoming[0]);
    }
}
