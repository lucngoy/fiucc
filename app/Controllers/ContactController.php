<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ContactMessage;
use App\Models\Member;
use App\Models\User;
use App\Services\ContactSettings;
use App\Services\Mail;

/**
 * Page contact (§39 du cahier des charges) : formulaire nom / email / sujet
 * / message, CSRF (Router), validation, limitation de débit par IP, envoi
 * par PHPMailer (App\Services\Mail). Coordonnées affichées à côté du
 * formulaire : modifiables depuis /admin/contact (ContactSettings).
 *
 * Chaque message est aussi enregistré (contact_messages) : c'est ce qui
 * permet de compter les envois par IP, et l'admin le retrouve dans le back
 * office même si l'envoi SMTP a échoué.
 */
final class ContactController extends Controller
{
    private const MESSAGE_MAX_LENGTH = 3000;

    public function index(): void
    {
        $this->showForm($this->prefill(), [], ($_GET['sent'] ?? '') === '1');
    }

    public function send(): void
    {
        $input = [
            'name'    => trim((string) ($_POST['name'] ?? '')),
            'email'   => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'message' => trim(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? ''))),
        ];

        // Champ piège invisible pour un humain (masqué en CSS, hors
        // tabulation) : un robot qui remplit tout le formulaire le remplit
        // aussi. On fait comme si l'envoi avait réussi, sans rien enregistrer
        // ni envoyer — ne rien lui apprendre sur ce qui l'a trahi.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $this->redirect('/contact?sent=1');
            return;
        }

        $errors = $this->validate($input);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($errors === []) {
            $max = max(1, (int) Config::get('security.contact_max_messages', 3));
            $window = max(1, (int) Config::get('security.contact_window_minutes', 15));

            if (ContactMessage::countRecentFromIp($ip, $window) >= $max) {
                $errors['form'] = t('contact.error.rate_limited', ['minutes' => $window]);
            }
        }

        if ($errors !== []) {
            $this->showForm($input, $errors, false);
            return;
        }

        $messageId = ContactMessage::create($input + ['ip_address' => $ip]);

        $recipient = ContactSettings::recipientEmail();

        if ($recipient !== '') {
            // Contenu saisi par un visiteur : tout passe par e() avant
            // d'entrer dans le corps HTML (CLAUDE.md, règle 5).
            $sent = Mail::send(
                $recipient,
                (string) Config::get('mail.from_name', 'FIUCC'),
                t('mail.contact.subject', ['subject' => $input['subject']]),
                t('mail.contact.heading'),
                t('mail.contact.body', [
                    'name'    => e($input['name']),
                    'email'   => e($input['email']),
                    'subject' => e($input['subject']),
                    'message' => nl2br(e($input['message'])),
                ])
            );

            if ($sent) {
                ContactMessage::markSent($messageId);
            }
        }

        // Post/Redirect/Get : un rechargement de la page de confirmation ne
        // renvoie pas le message.
        $this->redirect('/contact?sent=1');
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if ($input['name'] === '') {
            $errors['name'] = t('contact.error.name_required');
        } elseif (mb_strlen($input['name']) > 120) {
            $errors['name'] = t('validation.value_too_long');
        }

        if ($input['email'] === '' || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($input['email']) > 190) {
            $errors['email'] = t('validation.email_invalid');
        }

        if ($input['subject'] === '') {
            $errors['subject'] = t('contact.error.subject_required');
        } elseif (mb_strlen($input['subject']) > 150) {
            $errors['subject'] = t('validation.value_too_long');
        }

        if (mb_strlen($input['message']) < 10) {
            $errors['message'] = t('contact.error.message_too_short');
        } elseif (mb_strlen($input['message']) > self::MESSAGE_MAX_LENGTH) {
            $errors['message'] = t('contact.error.message_too_long', ['max' => self::MESSAGE_MAX_LENGTH]);
        }

        return $errors;
    }

    /**
     * Membre connecté : nom et email pré-remplis (modifiables).
     *
     * @return array<string, string>
     */
    private function prefill(): array
    {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        $user = User::find((int) $_SESSION['user_id']);
        $member = Member::findByUserId((int) $_SESSION['user_id']);

        return [
            'name'  => (string) ($member['full_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
        ];
    }

    private function showForm(array $old, array $errors, bool $sent): void
    {
        $this->view('contact.index', [
            'title'            => t('contact.title'),
            'description'      => t('contact.meta_description'),
            'contact'          => ContactSettings::values(),
            'old'              => $old,
            'errors'           => $errors,
            'sent'             => $sent,
            'messageMaxLength' => self::MESSAGE_MAX_LENGTH,
        ]);
    }
}
