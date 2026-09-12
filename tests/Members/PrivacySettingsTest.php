<?php
declare(strict_types=1);

namespace Tests\Members;

use App\Models\Member;
use Tests\TestCase;

/**
 * Confidentialité opt-in (FIUCC-83) : le défaut réel du schéma (migration
 * 016 — la case n'a jamais dû être pré-cochée, voir le cahier des
 * charges section Annuaire des membres) et la persistance de
 * Member::updatePrivacySettings(). Pas de test HTTP du contrôleur ici :
 * ProfileController::updatePrivacy() dérive toujours memberId de la
 * session ($_SESSION['user_id'] → Member::findFullByUserId()), jamais
 * d'un id de requête — même garantie structurelle que update()
 * ci-dessus, déjà vérifiable par lecture du code plutôt que par un test
 * d'intégration HTTP (absent aussi pour update()).
 */
final class PrivacySettingsTest extends TestCase
{
    public function test_privacy_settings_returns_null_for_an_unknown_member(): void
    {
        self::assertNull(Member::privacySettings(999999));
    }

    /**
     * Insertion brute, sans passer par Member::create() (qui fournit
     * toujours explicitement les trois colonnes — voir son commentaire) :
     * seule façon de vérifier le DEFAULT réel de la colonne plutôt que la
     * valeur choisie par l'appelant.
     */
    public function test_a_row_that_omits_the_privacy_columns_defaults_to_fully_private(): void
    {
        $userId = $this->makeUser();

        $stmt = $this->db->prepare(
            "INSERT INTO members (user_id, membership_number, full_name, student_id, status_type, status)
             VALUES (:user_id, :number, 'Membre par défaut', :student_id, 'student', 'pending')"
        );
        $stmt->execute([
            'user_id'    => $userId,
            'number'     => 'TMP-' . bin2hex(random_bytes(8)),
            'student_id' => 'T' . bin2hex(random_bytes(5)),
        ]);
        $memberId = (int) $this->db->lastInsertId();

        $settings = Member::privacySettings($memberId);

        self::assertSame(0, (int) $settings['is_public']);
        self::assertSame(0, (int) $settings['show_email']);
        self::assertSame(0, (int) $settings['show_phone']);
    }

    public function test_update_privacy_settings_persists_all_three_flags(): void
    {
        $memberId = $this->makeMember();

        Member::updatePrivacySettings($memberId, [
            'is_public'  => 1,
            'show_email' => 1,
            'show_phone' => 0,
        ]);

        $settings = Member::privacySettings($memberId);

        self::assertSame(1, (int) $settings['is_public']);
        self::assertSame(1, (int) $settings['show_email']);
        self::assertSame(0, (int) $settings['show_phone']);
    }

    public function test_update_privacy_settings_only_touches_the_target_member(): void
    {
        $memberA = $this->makeMember();
        $memberB = $this->makeMember();

        Member::updatePrivacySettings($memberA, ['is_public' => 1, 'show_email' => 1, 'show_phone' => 1]);
        Member::updatePrivacySettings($memberB, ['is_public' => 0, 'show_email' => 0, 'show_phone' => 0]);

        $settingsA = Member::privacySettings($memberA);
        $settingsB = Member::privacySettings($memberB);

        self::assertSame(1, (int) $settingsA['is_public']);
        self::assertSame(0, (int) $settingsB['is_public']);
    }
}
