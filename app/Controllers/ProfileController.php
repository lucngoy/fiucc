<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Mentorship;
use App\Services\CensusFields;
use App\Services\MemberNotifier;
use App\Services\Passwords;
use App\Services\Upload;
use DateTime;
use RuntimeException;

/**
 * Page profil du membre (FIUCC-37) : affichage seul, sa propre fiche et
 * l'état de son dossier (pending/validated/rejected/suspended).
 * Édition + upload de la photo (FIUCC-38) : edit()/update() ci-dessous.
 * Confidentialité (FIUCC-83) : privacy()/updatePrivacy() ci-dessous, un
 * formulaire et un chemin d'écriture séparés d'update() — voir
 * Member::updateProfile()/updatePrivacySettings().
 * Déclaration de mentor (FIUCC-87) : mentor()/updateMentor() ci-dessous,
 * réservé aux alumni (section "Mentorat & partenariats" du cahier —
 * "un alumni peut se déclarer mentor").
 */
final class ProfileController extends Controller
{
    public function index(): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null) {
            // Compte admin (aucune ligne members associée) ou incohérence de
            // données : cette page n'a rien à montrer, pas de 500 pour autant.
            $this->redirect('/');
            return;
        }

        // email_verified : arrivée directe après le code de vérification
        // (VerificationController::verify(), connexion automatique).
        $notices = [
            'validation_required' => ['type' => 'info', 'message' => t('profile.notice_validation_required')],
            'email_verified'      => ['type' => 'success', 'message' => t('profile.notice_email_verified')],
            'resubmitted'         => ['type' => 'success', 'message' => t('profile.notice_resubmitted')],
        ];
        $notice = $notices[(string) ($_GET['notice'] ?? '')] ?? null;

        // Juste is_public, pas les trois réglages (FIUCC-83 en garde la
        // lecture complète séparée) : cette page n'a besoin que de savoir
        // si l'invitation "vous n'apparaissez pas dans l'annuaire" doit
        // s'afficher.
        $privacySettings = Member::privacySettings((int) $member['id']);

        $this->view('profile.index', [
            'title'       => t('profile.title'),
            'member'      => $member,
            'studyLevels' => Config::get('registration.study_levels', []),
            'notice'      => $notice,
            'isPublic'    => $privacySettings !== null && (int) $privacySettings['is_public'] === 1,
            // "Mes candidatures" (lot 3) : statut de chaque dépôt, rien du vote.
            'candidacies' => \App\Models\Candidate::allForMember((int) $member['id']),
        ]);
    }

    public function edit(): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null) {
            $this->redirect('/');
            return;
        }

        $this->showForm($member, $this->toInput($member), []);
    }

    public function update(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $member = Member::findFullByUserId($userId);

        if ($member === null) {
            $this->redirect('/');
            return;
        }

        $input = $this->collectInput();
        $errors = $this->validate($input);

        $newPhotoPath = null;

        if ($errors === [] && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPhotoPath = Upload::storeImage(
                    $_FILES['photo'],
                    'members',
                    (int) Config::get('security.upload_photo_max_dimension', 800),
                    (int) Config::get('security.upload_max_size_mb', 5) * 1024 * 1024,
                    (int) Config::get('security.upload_max_input_dimension', 5000)
                );
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        // Photo du passeport (migration 018) : remplaçable tant que le
        // dossier n'est pas validé — typiquement après un refus pour photo
        // illisible. Obligatoire pour renvoyer un dossier refusé qui n'en a
        // plus (supprimée après 90 jours, PassportScanRetention).
        $isRejected = $member['status'] === 'rejected';
        $newScanPath = null;

        if (self::canReplaceScan($member) && ($_FILES['passport_scan']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($errors === []) {
                try {
                    $newScanPath = Upload::storePrivateImage(
                        $_FILES['passport_scan'],
                        'passports',
                        (int) Config::get('registration.passport_scan_max_dimension', 2000),
                        (int) Config::get('registration.passport_scan_max_size_mb', 8) * 1024 * 1024,
                        (int) Config::get('security.upload_max_input_dimension', 5000)
                    );
                } catch (RuntimeException $exception) {
                    $errors['passport_scan'] = $exception->getMessage();
                }
            }
        } elseif ($isRejected && empty($member['passport_scan'])) {
            $errors['passport_scan'] = t('validation.passport_scan_required');
        }

        if ($errors !== []) {
            if ($newScanPath !== null) {
                @unlink(Upload::storagePath($newScanPath));
            }

            $this->showForm($member, $input, $errors);
            return;
        }

        $faculties = Config::get('registration.faculties', []);

        Member::updateProfile((int) $member['id'], CensusFields::toColumns($input) + [
            'date_of_birth'  => $input['date_of_birth'] !== '' ? $input['date_of_birth'] : null,
            'gender'         => $input['gender'] !== '' ? $input['gender'] : null,
            'city_of_origin' => $input['city_of_origin'] !== '' ? $input['city_of_origin'] : null,
            'faculty'        => $faculties[$input['faculty']] ?? $input['faculty'],
            'department'     => $input['department'],
            'year'           => (int) $input['year'],
            'phone'          => $input['phone'],
            'whatsapp'       => $input['whatsapp'] !== '' ? $input['whatsapp'] : null,
            'instagram'      => $input['instagram'] !== '' ? $input['instagram'] : null,
        ]);

        if ($newPhotoPath !== null) {
            $oldPhoto = $member['photo'];
            Member::updatePhoto((int) $member['id'], $newPhotoPath);

            // Nettoyage best-effort : un fichier orphelin n'est jamais une
            // erreur bloquante pour l'utilisateur, juste de l'espace disque.
            if ($oldPhoto !== null && $oldPhoto !== '') {
                @unlink(BASE_PATH . '/public/uploads/' . $oldPhoto);
            }
        }

        if ($newScanPath !== null) {
            Member::updatePassportScan((int) $member['id'], $newScanPath);

            if (!empty($member['passport_scan'])) {
                @unlink(Upload::storagePath((string) $member['passport_scan']));
            }
        }

        ActivityLog::record($userId, 'profile_updated', 'member', (int) $member['id']);

        // Dossier refusé : enregistrer les corrections le renvoie (retour en
        // attente) et prévient les admins par email — le motif précédent
        // reste consultable dans activity_logs. Décision utilisateur,
        // 2026-10-09.
        if ($isRejected && Member::resubmit((int) $member['id'])) {
            ActivityLog::record($userId, 'member.resubmitted', 'member', (int) $member['id'], (string) $member['rejection_reason']);
            MemberNotifier::notifyAdminsResubmitted((int) $member['id'], CensusFields::fullName($input));

            $this->redirect('/profile?notice=resubmitted');
            return;
        }

        $this->redirect('/profile');
    }

    /**
     * Photo du passeport remplaçable depuis le profil tant que le dossier
     * n'est pas validé (pending, rejected) — après validation, l'identité a
     * été vérifiée, il n'y a plus de raison d'en recevoir une nouvelle.
     */
    private static function canReplaceScan(array $member): bool
    {
        return in_array($member['status'], ['pending', 'rejected'], true);
    }

    public function privacy(): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null) {
            $this->redirect('/');
            return;
        }

        $settings = Member::privacySettings((int) $member['id']);

        if ($settings === null) {
            $this->redirect('/');
            return;
        }

        $this->view('profile.privacy', [
            'title'    => t('profile.privacy.title'),
            'settings' => $settings,
        ]);
    }

    /**
     * $memberId dérivé de la session, jamais d'un id de route/formulaire —
     * même principe IDOR-safe que update() ci-dessus : un membre ne peut
     * changer que ses propres réglages de confidentialité.
     */
    public function updatePrivacy(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $member = Member::findFullByUserId($userId);

        if ($member === null) {
            $this->redirect('/');
            return;
        }

        Member::updatePrivacySettings((int) $member['id'], [
            'is_public'  => ($_POST['is_public'] ?? '') === '1' ? 1 : 0,
            'show_email' => ($_POST['show_email'] ?? '') === '1' ? 1 : 0,
            'show_phone' => ($_POST['show_phone'] ?? '') === '1' ? 1 : 0,
        ]);

        ActivityLog::record($userId, 'privacy_updated', 'member', (int) $member['id']);

        $this->redirect('/profile/privacy?notice=updated');
    }

    /**
     * Réservé aux alumni (cahier : "un alumni peut se déclarer mentor") —
     * un student ou un honorary est redirigé vers son profil, jamais un
     * formulaire qu'il ne peut pas légitimement soumettre.
     */
    public function mentor(): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null || $member['status_type'] !== 'alumni') {
            $this->redirect('/profile');
            return;
        }

        $settings = Member::mentorSettings((int) $member['id']);

        if ($settings === null) {
            $this->redirect('/profile');
            return;
        }

        $this->view('profile.mentor', [
            'title'    => t('profile.mentor.title'),
            'settings' => $settings,
            'errors'   => [],
            'requests' => Mentorship::incomingForMentor((int) $member['id']),
            'notice'   => (string) ($_GET['notice'] ?? ''),
        ]);
    }

    /**
     * Mentorship::respond() revérifie mentor_id = ce membre ET status =
     * 'pending' dans son propre WHERE — ce contrôleur ne fait que
     * traduire un échec (mauvais id, déjà tranchée, pas la sienne) en un
     * message générique, jamais de détail qui confirmerait l'existence
     * d'une demande appartenant à quelqu'un d'autre.
     */
    public function respondToMentorship(array $params): void
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        if ($member === null || $member['status_type'] !== 'alumni') {
            $this->redirect('/profile');
            return;
        }

        $status = ($_POST['response'] ?? '') === 'accepted' ? 'accepted' : 'declined';
        $mentorshipId = (int) ($params['id'] ?? 0);

        $success = Mentorship::respond($mentorshipId, (int) $member['id'], $status);

        if ($success) {
            ActivityLog::record((int) $_SESSION['user_id'], 'mentorship_' . $status, 'mentorship', $mentorshipId);
        }

        $this->redirect('/profile/mentor?notice=' . ($success ? 'responded' : 'response_failed'));
    }

    public function updateMentor(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $member = Member::findFullByUserId($userId);

        if ($member === null || $member['status_type'] !== 'alumni') {
            $this->redirect('/profile');
            return;
        }

        $isMentor = ($_POST['is_mentor'] ?? '') === '1' ? 1 : 0;
        $mentorField = trim((string) ($_POST['mentor_field'] ?? ''));
        $mentorAvailability = trim((string) ($_POST['mentor_availability'] ?? ''));
        $mentorBio = trim((string) ($_POST['mentor_bio'] ?? ''));

        // Un mentor sans domaine ni disponibilité n'aide pas un étudiant à
        // choisir — obligatoires seulement si is_mentor est activé, jamais
        // pour simplement désactiver la déclaration.
        $errors = [];
        if ($isMentor === 1 && $mentorField === '') {
            $errors['mentor_field'] = t('validation.mentor_field_required');
        }
        if ($isMentor === 1 && $mentorAvailability === '') {
            $errors['mentor_availability'] = t('validation.mentor_availability_required');
        }

        if ($errors !== []) {
            $currentSettings = Member::mentorSettings((int) $member['id']);

            $this->view('profile.mentor', [
                'title'    => t('profile.mentor.title'),
                'settings' => [
                    'is_mentor'           => $isMentor,
                    'mentor_field'        => $mentorField,
                    'mentor_availability' => $mentorAvailability,
                    'mentor_bio'          => $mentorBio,
                    'show_email'          => (int) ($currentSettings['show_email'] ?? 0),
                    'show_phone'          => (int) ($currentSettings['show_phone'] ?? 0),
                ],
                'errors' => $errors,
            ]);
            return;
        }

        Member::updateMentorSettings((int) $member['id'], [
            'is_mentor'           => $isMentor,
            'mentor_field'        => $mentorField !== '' ? $mentorField : null,
            'mentor_availability' => $mentorAvailability !== '' ? $mentorAvailability : null,
            'mentor_bio'          => $mentorBio !== '' ? $mentorBio : null,
        ]);

        ActivityLog::record($userId, 'mentor_settings_updated', 'member', (int) $member['id']);

        $this->redirect('/profile/mentor?notice=updated');
    }

    /**
     * Changement du mot de passe (App\Services\Passwords::change()) —
     * ouvert à tout membre connecté, pending compris : c'est son propre
     * compte, rien sur d'autres membres.
     */
    public function password(): void
    {
        $this->view('profile.password', [
            'title'  => t('password.change.title'),
            'errors' => [],
            'notice' => (string) ($_GET['notice'] ?? ''),
        ]);
    }

    public function updatePassword(): void
    {
        $errors = Passwords::change(
            (int) $_SESSION['user_id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );

        if ($errors !== []) {
            $this->view('profile.password', [
                'title'  => t('password.change.title'),
                'errors' => $errors,
                'notice' => '',
            ]);
            return;
        }

        $this->redirect('/profile/password?notice=updated');
    }

    private function showForm(array $member, array $input, array $errors): void
    {
        $this->view('profile.edit', [
            'title'       => t('profile.edit_title'),
            'member'      => $member,
            'faculties'   => Config::get('registration.faculties', []),
            'studyLevels' => Config::get('registration.study_levels', []),
            'communityRoles' => Config::get('registration.community_roles', []),
            'canReplaceScan' => self::canReplaceScan($member),
            'passportScanMaxMb' => (int) Config::get('registration.passport_scan_max_size_mb', 8),
            'districts'   => Config::get('registration.districts', []),
            'religions'   => Config::get('registration.religions', []),
            'old'         => $input,
            'errors'      => $errors,
        ]);
    }

    private function toInput(array $member): array
    {
        $faculties = Config::get('registration.faculties', []);

        return CensusFields::fromMember($member) + [
            'date_of_birth'  => (string) ($member['date_of_birth'] ?? ''),
            'gender'         => (string) ($member['gender'] ?? ''),
            'city_of_origin' => (string) ($member['city_of_origin'] ?? ''),
            // Le recensement (FIUCC-32) stocke le libellé complet, pas la clé
            // de config/registration.php — on retrouve la clé pour présélectionner
            // le bon <option> ; vide si la faculté ne correspond plus à la liste.
            'faculty'        => (string) (array_search($member['faculty'], $faculties, true) ?: ''),
            'department'     => (string) ($member['department'] ?? ''),
            'year'           => (string) ($member['year'] ?? ''),
            'phone'          => (string) ($member['phone'] ?? ''),
            'whatsapp'       => (string) ($member['whatsapp'] ?? ''),
            'instagram'      => (string) ($member['instagram'] ?? ''),
        ];
    }

    private function collectInput(): array
    {
        return CensusFields::collect($_POST) + [
            'date_of_birth'  => trim((string) ($_POST['date_of_birth'] ?? '')),
            'gender'         => trim((string) ($_POST['gender'] ?? '')),
            'city_of_origin' => trim((string) ($_POST['city_of_origin'] ?? '')),
            'faculty'        => trim((string) ($_POST['faculty'] ?? '')),
            'department'     => trim((string) ($_POST['department'] ?? '')),
            'year'           => trim((string) ($_POST['year'] ?? '')),
            'phone'          => trim((string) ($_POST['phone'] ?? '')),
            'whatsapp'       => trim((string) ($_POST['whatsapp'] ?? '')),
            'instagram'      => trim((string) ($_POST['instagram'] ?? '')),
        ];
    }

    private function validate(array $input): array
    {
        $errors = CensusFields::validate($input);
        $faculties = Config::get('registration.faculties', []);
        $studyLevels = Config::get('registration.study_levels', []);

        if ($input['date_of_birth'] === '') {
            $errors['date_of_birth'] = t('validation.date_of_birth_required');
        } elseif (!$this->isValidDate($input['date_of_birth'])) {
            $errors['date_of_birth'] = t('validation.date_invalid');
        }

        if ($input['gender'] === '') {
            $errors['gender'] = t('validation.gender_required');
        } elseif (!in_array($input['gender'], ['male', 'female'], true)) {
            $errors['gender'] = t('validation.option_invalid');
        }

        if ($input['city_of_origin'] === '') {
            $errors['city_of_origin'] = t('validation.city_of_origin_required');
        } elseif (mb_strlen($input['city_of_origin']) > 100) {
            $errors['city_of_origin'] = t('validation.value_too_long');
        }

        if ($input['faculty'] === '' || !array_key_exists($input['faculty'], $faculties)) {
            $errors['faculty'] = t('validation.faculty_required');
        }

        if ($input['department'] === '') {
            $errors['department'] = t('validation.department_required');
        } elseif (mb_strlen($input['department']) > 120) {
            $errors['department'] = t('validation.department_too_long');
        }

        if ($input['year'] === '' || !array_key_exists((int) $input['year'], $studyLevels)) {
            $errors['year'] = t('validation.year_required');
        }

        if ($input['phone'] === '') {
            $errors['phone'] = t('validation.phone_required');
        } elseif (mb_strlen($input['phone']) > 30) {
            $errors['phone'] = t('validation.phone_too_long');
        }

        if ($input['whatsapp'] === '') {
            $errors['whatsapp'] = t('validation.whatsapp_required');
        } elseif (mb_strlen($input['whatsapp']) > 30) {
            $errors['whatsapp'] = t('validation.whatsapp_too_long');
        }

        if (mb_strlen($input['instagram']) > 60) {
            $errors['instagram'] = t('validation.instagram_too_long');
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
