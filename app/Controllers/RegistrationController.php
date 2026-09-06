<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\Otp;
use DateTime;
use PDOException;
use Throwable;

final class RegistrationController extends Controller
{
    public function create(): void
    {
        $this->showForm([], []);
    }

    public function store(): void
    {
        $input = $this->collectInput();
        $errors = $this->validate($input);

        if ($errors !== []) {
            $this->showForm($input, $errors);
            return;
        }

        try {
            [$userId, $memberId] = $this->persist($input);
        } catch (PDOException $exception) {
            // Course entre la vérification d'unicité et l'écriture (deux
            // inscriptions concurrentes avec le même email/student_id) :
            // rare, mais ne doit jamais remonter en 500 nu. On ne tente pas
            // d'attribuer l'erreur à un champ précis à partir du message
            // SQL, driver-dépendant — un message générique suffit ici.
            if ($exception->getCode() === '23000') {
                $this->showForm($input, ['email' => t('validation.duplicate_registration')]);
                return;
            }

            throw $exception;
        }

        ActivityLog::record($userId, 'member_registered', 'member', $memberId);

        // Envoi immédiat du premier code (section 14 du cahier des charges) :
        // pas de garde-fou sur l'échec d'envoi ici, la page de vérification
        // propose un renvoi si le premier message se perd.
        Otp::send($userId, $input['email'], $input['full_name']);

        // Pas de session pleinement authentifiée à ce stade — juste assez
        // pour savoir qui vérifie, sans redemander l'email.
        $_SESSION['pending_verification_user_id'] = $userId;

        $this->redirect('/verify-email');
    }

    /**
     * @return array{0: int, 1: int} [user.id, member.id] du nouveau compte
     */
    private function persist(array $input): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $userId = User::create($input['email'], password_hash($input['password'], PASSWORD_DEFAULT));

            $faculties = Config::get('registration.faculties', []);

            $memberId = Member::create([
                'user_id'        => $userId,
                'full_name'      => $input['full_name'],
                'student_id'     => $input['student_id'],
                'date_of_birth'  => $input['date_of_birth'] !== '' ? $input['date_of_birth'] : null,
                'gender'         => $input['gender'] !== '' ? $input['gender'] : null,
                'city_of_origin' => $input['city_of_origin'] !== '' ? $input['city_of_origin'] : null,
                'faculty'        => $faculties[$input['faculty']] ?? $input['faculty'],
                'department'     => $input['department'],
                'year'           => (int) $input['year'],
                'phone'          => $input['phone'],
                'whatsapp'       => $input['whatsapp'] !== '' ? $input['whatsapp'] : null,
                'instagram'      => $input['instagram'] !== '' ? $input['instagram'] : null,
                'is_public'      => $input['is_public'] === '1' ? 1 : 0,
                'show_email'     => $input['show_email'] === '1' ? 1 : 0,
                'show_phone'     => $input['show_phone'] === '1' ? 1 : 0,
            ]);

            $pdo->commit();

            return [$userId, $memberId];
        } catch (Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }

    private function showForm(array $old, array $errors): void
    {
        $this->view('registration.create', [
            'title'             => t('census.title'),
            'faculties'         => Config::get('registration.faculties', []),
            'studyLevels'       => Config::get('registration.study_levels', []),
            'passwordMinLength' => Config::get('security.password_min_length', 10),
            'old'               => $old,
            'errors'            => $errors,
        ]);
    }

    private function collectInput(): array
    {
        return [
            'full_name'             => trim((string) ($_POST['full_name'] ?? '')),
            'date_of_birth'         => trim((string) ($_POST['date_of_birth'] ?? '')),
            'gender'                => trim((string) ($_POST['gender'] ?? '')),
            'city_of_origin'        => trim((string) ($_POST['city_of_origin'] ?? '')),
            'faculty'               => trim((string) ($_POST['faculty'] ?? '')),
            'department'            => trim((string) ($_POST['department'] ?? '')),
            'year'                  => trim((string) ($_POST['year'] ?? '')),
            'student_id'            => trim((string) ($_POST['student_id'] ?? '')),
            'email'                 => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'password'              => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
            'phone'                 => trim((string) ($_POST['phone'] ?? '')),
            'whatsapp'              => trim((string) ($_POST['whatsapp'] ?? '')),
            'instagram'             => trim((string) ($_POST['instagram'] ?? '')),
            'is_public'             => (string) ($_POST['is_public'] ?? ''),
            'show_email'            => (string) ($_POST['show_email'] ?? ''),
            'show_phone'            => (string) ($_POST['show_phone'] ?? ''),
            'consent'               => (string) ($_POST['consent'] ?? ''),
        ];
    }

    private function validate(array $input): array
    {
        $errors = [];

        $faculties = Config::get('registration.faculties', []);
        $studyLevels = Config::get('registration.study_levels', []);
        $allowedDomains = Config::get('registration.allowed_email_domains', []);
        $passwordMinLength = (int) Config::get('security.password_min_length', 10);

        if ($input['full_name'] === '') {
            $errors['full_name'] = t('validation.full_name_required');
        } elseif (mb_strlen($input['full_name']) > 150) {
            $errors['full_name'] = t('validation.full_name_too_long');
        }

        if ($input['date_of_birth'] !== '' && !$this->isValidDate($input['date_of_birth'])) {
            $errors['date_of_birth'] = t('validation.date_invalid');
        }

        if ($input['gender'] !== '' && !in_array($input['gender'], ['male', 'female', 'other'], true)) {
            $errors['gender'] = t('validation.option_invalid');
        }

        if (mb_strlen($input['city_of_origin']) > 100) {
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

        if ($input['student_id'] === '') {
            $errors['student_id'] = t('validation.student_id_required');
        } elseif (mb_strlen($input['student_id']) > 50) {
            $errors['student_id'] = t('validation.student_id_too_long');
        } elseif (Member::studentIdExists($input['student_id'])) {
            $errors['student_id'] = t('validation.student_id_taken');
        }

        if ($input['email'] === '' || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = t('validation.email_invalid');
        } elseif (!$this->hasAllowedDomain($input['email'], $allowedDomains)) {
            $errors['email'] = t('validation.email_domain_restricted', ['domains' => implode(' ' . t('common.or') . ' ', $allowedDomains)]);
        } elseif (User::emailExists($input['email'])) {
            $errors['email'] = t('validation.email_taken');
        }

        if ($input['password'] === '') {
            $errors['password'] = t('validation.password_required');
        } elseif (mb_strlen($input['password']) < $passwordMinLength) {
            $errors['password'] = t('validation.password_too_short', ['min' => $passwordMinLength]);
        }

        if ($input['password_confirmation'] === '') {
            $errors['password_confirmation'] = t('validation.password_confirmation_required');
        } elseif ($input['password'] !== $input['password_confirmation']) {
            $errors['password_confirmation'] = t('validation.password_mismatch');
        }

        if ($input['phone'] === '') {
            $errors['phone'] = t('validation.phone_required');
        } elseif (mb_strlen($input['phone']) > 30) {
            $errors['phone'] = t('validation.phone_too_long');
        }

        if (mb_strlen($input['whatsapp']) > 30) {
            $errors['whatsapp'] = t('validation.whatsapp_too_long');
        }

        if (mb_strlen($input['instagram']) > 60) {
            $errors['instagram'] = t('validation.instagram_too_long');
        }

        if ($input['consent'] !== '1') {
            $errors['consent'] = t('validation.consent_required');
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function hasAllowedDomain(string $email, array $allowedDomains): bool
    {
        $atPosition = strrchr($email, '@');

        if ($atPosition === false) {
            return false;
        }

        $domain = strtolower(substr($atPosition, 1));

        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === strtolower($allowedDomain)) {
                return true;
            }
        }

        return false;
    }
}
