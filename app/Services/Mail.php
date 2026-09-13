<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\View;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Comportement piloté par config/mail.php (mail.driver), lui-même
 * MAIL_DRIVER de .env sans aucune déduction depuis APP_ENV — une valeur
 * explicite a toujours priorité, 'log' n'intervient que si la variable est
 * absente. Ça permet de positionner MAIL_DRIVER=smtp en local, contre un
 * vrai fournisseur (Mailersend...), pour tester un envoi réel sans changer
 * APP_ENV — un oubli de ce réglage (variable absente ou vide) retombe
 * toujours sur 'log', jamais un envoi réel par défaut :
 * - 'log' (défaut) : aucun email réel, écrit dans storage/logs/mail.log.
 * - 'smtp' : envoi réel via PHPMailer.
 *
 * Gabarit : resources/views/emails/layout.php, styles en ligne, charte
 * FIUCC (bleu principal, logo). Le texte de repli est dérivé automatiquement
 * du HTML si non fourni.
 */
final class Mail
{
    /**
     * @param string $heading      Titre affiché dans l'email
     * @param string $contentHtml  Corps HTML du message (fragment, injecté
     *                             dans le layout — pas un document complet)
     * @param array $secrets       Valeurs sensibles réellement présentes dans
     *                             ce message (code OTP, token...) — transmises
     *                             telles quelles à App\Core\Logger, qui décide
     *                             seul de les masquer ou non (mail.reveal_secrets).
     *                             N'affecte jamais l'email réellement envoyé
     *                             (driver smtp) : seul le journal est concerné.
     * @param string|null $plainText Texte de repli ; dérivé du HTML si omis.
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $heading,
        string $contentHtml,
        array $secrets = [],
        ?string $plainText = null
    ): bool {
        $html = View::partial('emails.layout', ['heading' => $heading, 'content' => $contentHtml]);
        $plainText ??= self::toPlainText($contentHtml);

        if ((string) Config::get('mail.driver', 'log') !== 'smtp') {
            self::logOnly($toEmail, $toName, $subject, $plainText, $secrets);

            return true;
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = (string) Config::get('mail.host');
            $mailer->Port = (int) Config::get('mail.port', 587);
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) Config::get('mail.username');
            $mailer->Password = (string) Config::get('mail.password');
            $mailer->SMTPSecure = (string) Config::get('mail.encryption', 'tls');
            $mailer->CharSet = 'UTF-8';

            $mailer->setFrom(
                (string) Config::get('mail.from_address'),
                (string) Config::get('mail.from_name')
            );
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $html;
            $mailer->AltBody = $plainText;

            $mailer->send();

            return true;
        } catch (PHPMailerException $exception) {
            // Le mot de passe SMTP n'apparaît dans aucun message PHPMailer
            // connu, mais on le masque par prudence si un jour ça changeait.
            // Masquage délégué à Logger (jamais fait ici) : lui seul connaît
            // l'échappatoire mail.reveal_secrets, un masquage fait en amont
            // la rendrait inopérante (bug déjà rencontré et corrigé).
            $secretsToMask = array_merge($secrets, [(string) Config::get('mail.password', '')]);
            Logger::error('mail', "Failed to send to {$toEmail}: {$exception->getMessage()}", [], $secretsToMask);

            return false;
        }
    }

    private static function logOnly(string $toEmail, string $toName, string $subject, string $plainText, array $secrets): void
    {
        $entry = "To: {$toName} <{$toEmail}>\nSubject: {$subject}\n\n{$plainText}\n" . str_repeat('-', 40);

        Logger::info('mail', $entry, [], $secrets);
    }

    /**
     * Dérive un texte de repli lisible à partir du fragment HTML du message.
     */
    private static function toPlainText(string $html): string
    {
        $withBreaks = preg_replace('#<(br|/p|/div|/h[1-6]|/li)\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $withBreaks), ENT_QUOTES, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
