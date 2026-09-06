<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Services\Upload;
use DateTime;
use RuntimeException;

/**
 * Page profil du membre (FIUCC-37) : affichage seul, sa propre fiche et
 * l'état de son dossier (pending/validated/rejected/suspended).
 * Édition + upload de la photo (FIUCC-38) : edit()/update() ci-dessous.
 * updatePrivacy() reste hors de ce ticket — FIUCC-83, phase 6.
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

        $notice = ($_GET['notice'] ?? null) === 'validation_required'
            ? 'That page requires a validated membership. Here is the status of your own registration in the meantime.'
            : null;

        $this->view('profile.index', [
            'title'       => 'My Profile',
            'member'      => $member,
            'studyLevels' => Config::get('registration.study_levels', []),
            'notice'      => $notice,
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
                    (int) Config::get('security.upload_max_size_mb', 5) * 1024 * 1024
                );
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($member, $input, $errors);
            return;
        }

        $faculties = Config::get('registration.faculties', []);

        Member::updateProfile((int) $member['id'], [
            'full_name'      => $input['full_name'],
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

        ActivityLog::record($userId, 'profile_updated', 'member', (int) $member['id']);

        $this->redirect('/profile');
    }

    private function showForm(array $member, array $input, array $errors): void
    {
        $this->view('profile.edit', [
            'title'       => 'Edit profile',
            'member'      => $member,
            'faculties'   => Config::get('registration.faculties', []),
            'studyLevels' => Config::get('registration.study_levels', []),
            'old'         => $input,
            'errors'      => $errors,
        ]);
    }

    private function toInput(array $member): array
    {
        $faculties = Config::get('registration.faculties', []);

        return [
            'full_name'      => $member['full_name'],
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
        return [
            'full_name'      => trim((string) ($_POST['full_name'] ?? '')),
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
        $errors = [];
        $faculties = Config::get('registration.faculties', []);
        $studyLevels = Config::get('registration.study_levels', []);

        if ($input['full_name'] === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($input['full_name']) > 150) {
            $errors['full_name'] = 'Full name is too long.';
        }

        if ($input['date_of_birth'] !== '' && !$this->isValidDate($input['date_of_birth'])) {
            $errors['date_of_birth'] = 'Please enter a valid date.';
        }

        if ($input['gender'] !== '' && !in_array($input['gender'], ['male', 'female', 'other'], true)) {
            $errors['gender'] = 'Please select a valid option.';
        }

        if (mb_strlen($input['city_of_origin']) > 100) {
            $errors['city_of_origin'] = 'This value is too long.';
        }

        if ($input['faculty'] === '' || !array_key_exists($input['faculty'], $faculties)) {
            $errors['faculty'] = 'Please select your faculty.';
        }

        if ($input['department'] === '') {
            $errors['department'] = 'Department is required.';
        } elseif (mb_strlen($input['department']) > 120) {
            $errors['department'] = 'Department is too long.';
        }

        if ($input['year'] === '' || !array_key_exists((int) $input['year'], $studyLevels)) {
            $errors['year'] = 'Please select your current year of study.';
        }

        if ($input['phone'] === '') {
            $errors['phone'] = 'Phone number is required.';
        } elseif (mb_strlen($input['phone']) > 30) {
            $errors['phone'] = 'Phone number is too long.';
        }

        if (mb_strlen($input['whatsapp']) > 30) {
            $errors['whatsapp'] = 'WhatsApp number is too long.';
        }

        if (mb_strlen($input['instagram']) > 60) {
            $errors['instagram'] = 'Instagram handle is too long.';
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
