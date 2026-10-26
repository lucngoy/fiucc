<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * Coordonnées de la communauté, modifiables depuis le back office
 * (/admin/contact) et affichées sur la page /contact, le pied de page et la
 * page « Nouveau à Final ? ». Une seule source pour toutes ces pages.
 *
 * Liste FERMÉE des réglages (FIELDS) : le formulaire admin ne peut écrire
 * aucune autre clé dans site_settings. Chaque type a sa validation — en
 * particulier les URL, imprimées dans des href : seuls http(s) sont
 * acceptés, jamais javascript: ou data: (e() échappe le texte, pas le
 * schéma d'un lien).
 *
 * WhatsApp et Instagram gardent un repli sur .env (app.whatsapp_url /
 * app.instagram_url, FIUCC-22) : une installation qui les avait configurés
 * ainsi continue de fonctionner tant qu'aucun admin ne les a saisis.
 */
final class ContactSettings
{
    /**
     * clé => [type, longueur maximale, publique ?]
     * "publique = false" : jamais affichée aux visiteurs (adresse qui reçoit
     * les messages du formulaire).
     */
    public const FIELDS = [
        'email'           => ['email', 190, true],
        'phone'           => ['phone', 30, true],
        'whatsapp_url'    => ['url', 255, true],
        'instagram_url'   => ['url', 255, true],
        'address'         => ['multiline', 300, true],
        'map_url'         => ['url', 500, true],
        'office_hours'    => ['multiline', 500, true],
        'emergency_phone' => ['phone', 30, true],
        'recipient_email' => ['email', 190, false],
        // Page /help : ambassade de la RDC (Ankara) et communauté mère.
        // Saisies par le bureau, jamais en dur : leurs coordonnées changent
        // et aucune source officielle en ligne ne les confirme.
        'embassy_address' => ['multiline', 300, true],
        'embassy_phone'   => ['phone', 30, true],
        'embassy_email'   => ['email', 190, true],
        'embassy_url'     => ['url', 255, true],
        'mother_community_url' => ['url', 255, true],
    ];

    private const KEY_PREFIX = 'contact.';

    /**
     * Valeurs courantes, chaînes vides pour les réglages non renseignés.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        $stored = Setting::all();
        $values = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = (string) ($stored[self::KEY_PREFIX . $key] ?? '');
        }

        if ($values['whatsapp_url'] === '') {
            $values['whatsapp_url'] = (string) Config::get('app.whatsapp_url', '');
        }

        if ($values['instagram_url'] === '') {
            $values['instagram_url'] = (string) Config::get('app.instagram_url', '');
        }

        return $values;
    }

    /**
     * Adresse qui reçoit les messages du formulaire : celle dédiée, sinon
     * l'email public, sinon l'expéditeur configuré pour l'application.
     */
    public static function recipientEmail(): string
    {
        $values = self::values();

        return $values['recipient_email'] !== ''
            ? $values['recipient_email']
            : ($values['email'] !== '' ? $values['email'] : (string) Config::get('mail.from_address', ''));
    }

    /**
     * @return array<string, string>
     */
    public static function collect(array $post): array
    {
        $input = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $value = str_replace("\r\n", "\n", trim((string) ($post[$key] ?? '')));
            $input[$key] = $value;
        }

        return $input;
    }

    /**
     * Tout est facultatif : un bureau peut ne pas avoir de téléphone ou
     * d'adresse. Seul le format est contrôlé quand une valeur est saisie.
     *
     * @return array<string, string> erreurs indexées par clé
     */
    public static function validate(array $input): array
    {
        $errors = [];

        foreach (self::FIELDS as $key => [$type, $maxLength]) {
            $value = $input[$key] ?? '';

            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > $maxLength) {
                $errors[$key] = t('validation.value_too_long');
                continue;
            }

            $valid = match ($type) {
                'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'url'       => self::isHttpUrl($value),
                'phone'     => preg_match('/^\+?[0-9 ().-]{6,30}$/', $value) === 1,
                default     => true,
            };

            if (!$valid) {
                $errors[$key] = t('admin.contact.invalid.' . $type);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string> clés préfixées, prêtes pour Setting::saveMany()
     */
    public static function toSettings(array $input): array
    {
        $settings = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $settings[self::KEY_PREFIX . $key] = $input[$key] ?? '';
        }

        return $settings;
    }

    /**
     * Numéro réduit à ce qu'accepte un lien tel: (chiffres et + initial).
     */
    public static function telHref(string $phone): string
    {
        return 'tel:' . preg_replace('/(?!^\+)[^0-9]/', '', $phone);
    }

    public static function isHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
