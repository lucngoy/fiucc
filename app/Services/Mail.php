<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Comportement piloté par config/mail.php (mail.driver) :
 * - 'log' (défaut) : aucun email réel, écrit dans storage/logs/mail.log.
 * - 'smtp' : envoi réel via PHPMailer.
 * Ne jamais positionner 'smtp' en local — voir .env.example.
 */
final class Mail
{
    public static function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        if ((string) Config::get('mail.driver', 'log') !== 'smtp') {
            self::logOnly($toEmail, $toName, $subject, $body);

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
            $mailer->Body = $body;

            $mailer->send();

            return true;
        } catch (PHPMailerException $exception) {
            Logger::error('mail', "Failed to send to {$toEmail}: {$exception->getMessage()}");

            return false;
        }
    }

    private static function logOnly(string $toEmail, string $toName, string $subject, string $body): void
    {
        $entry = "To: {$toName} <{$toEmail}>\nSubject: {$subject}\n\n{$body}\n" . str_repeat('-', 40);

        Logger::info('mail', $entry);
    }
}
