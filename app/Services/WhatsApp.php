<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Copie WhatsApp du code de vote, déjà envoyé par email, via l'API
 * WhatsApp de MailerSend :
 * POST https://api.mailersend.com/v1/whatsapp/send, 202 en cas de succès.
 *
 * Appel HTTP direct (curl), pas le SDK MailerSend : une seule requête ne
 * justifie pas une dépendance Composer de plus (CLAUDE.md).
 *
 * Toujours un canal EN PLUS de l'email, jamais à sa place : un échec ici
 * est journalisé et n'empêche rien (send() ne lève jamais d'exception), et
 * l'email reste la référence (VotingToken::markSent() ne dépend que de lui).
 *
 * Jamais pour le code d'inscription (voir App\Services\Otp) : le numéro
 * vient de members.whatsapp, déclaratif et jamais vérifié, il ne peut pas
 * servir à prouver la possession d'une adresse. Le code de vote, lui, ne
 * sert qu'une fois connecté au compte du membre.
 */
final class WhatsApp
{
    private const ENDPOINT = 'https://api.mailersend.com/v1/whatsapp/send';

    /**
     * @param string[] $bodyParams Valeurs des variables {{1}}, {{2}}... du modèle
     * @param string[] $secrets    Valeurs à masquer dans les journaux (le code)
     */
    public static function sendTemplate(?string $rawPhone, ?string $templateId, array $bodyParams, array $secrets = []): bool
    {
        $templateId = trim((string) $templateId);

        if ($templateId === '') {
            return false;
        }

        $phone = self::normalizePhone($rawPhone);

        if ($phone === null) {
            if (trim((string) $rawPhone) !== '') {
                // Numéro sans indicatif international : impossible à
                // adresser sans deviner le pays — on n'envoie pas.
                Logger::warning('whatsapp', 'Numéro non international ignoré.');
            }

            return false;
        }

        if ((string) Config::get('whatsapp.driver', 'log') !== 'mailersend') {
            Logger::info('whatsapp', "To: {$phone}\nTemplate: {$templateId}\nBody: " . implode(' | ', $bodyParams), [], $secrets);

            return true;
        }

        $apiKey = (string) Config::get('whatsapp.api_key', '');
        $from = (string) Config::get('whatsapp.from', '');

        if ($apiKey === '' || $from === '') {
            Logger::error('whatsapp', 'MAILERSEND_API_KEY ou WHATSAPP_FROM manquant : message non envoyé.');

            return false;
        }

        $payload = json_encode([
            'from'            => $from,
            'to'              => [$phone],
            'template_id'     => $templateId,
            'personalization' => [
                ['to' => $phone, 'data' => ['body' => array_values($bodyParams)]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) Config::get('whatsapp.timeout_seconds', 10),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status !== 202) {
            $detail = $response === false ? $curlError : mb_substr((string) $response, 0, 500);
            Logger::error('whatsapp', "Échec d'envoi vers {$phone} (HTTP {$status}) : {$detail}", [], array_merge($secrets, [$apiKey]));

            return false;
        }

        return true;
    }

    /**
     * "+90 533 800 00 00", "0090-533..." → "+905338000000". Sans indicatif
     * international (ex. "0533 800 00 00") → null : on ne devine pas le pays.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $raw);

        if (str_starts_with($raw, '+')) {
            $international = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = substr($digits, 2);
        } else {
            return null;
        }

        return preg_match('/\A[1-9]\d{7,14}\z/', $international) === 1 ? '+' . $international : null;
    }
}
