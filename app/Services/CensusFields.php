<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use DateTime;

/**
 * Champs de la fiche de recensement étendue (migration 018) : nom découpé,
 * statut communautaire, résidence, passeport, culte. Partagés entre le
 * recensement (RegistrationController) et l'édition du profil
 * (ProfileController) — une seule définition de ce qui est requis et de ce
 * qui est valide, pas deux copies qui divergent.
 *
 * Le scan du passeport n'est pas ici : c'est un fichier, géré par le
 * contrôleur via Upload::storePrivateImage().
 */
final class CensusFields
{
    /**
     * @return array<string, string>
     */
    public static function collect(array $post): array
    {
        $value = static fn (string $name): string => trim((string) ($post[$name] ?? ''));

        return [
            'last_name'         => $value('last_name'),
            'middle_name'       => $value('middle_name'),
            'first_name'        => $value('first_name'),
            'community_role'    => $value('community_role'),
            'address_street'    => $value('address_street'),
            'address_building'  => $value('address_building'),
            'address_apartment' => $value('address_apartment'),
            'district'          => $value('district'),
            // Espaces retirés et majuscules : un numéro de passeport n'en a
            // jamais, mais l'OCR ou une saisie à la main peuvent en ajouter.
            'passport_number'   => strtoupper((string) preg_replace('/\s+/', '', $value('passport_number'))),
            'passport_expiry'   => $value('passport_expiry'),
            'religion'          => $value('religion'),
        ];
    }

    /**
     * @return array<string, string> Erreurs indexées par nom de champ
     */
    public static function validate(array $input): array
    {
        $errors = [];

        if ($input['last_name'] === '') {
            $errors['last_name'] = t('validation.last_name_required');
        } elseif (mb_strlen($input['last_name']) > 80) {
            $errors['last_name'] = t('validation.value_too_long');
        }

        // Post-nom obligatoire (décision utilisateur, 2026-10-09 : tous les
        // champs du recensement le sont, sauf le pseudo Instagram).
        if ($input['middle_name'] === '') {
            $errors['middle_name'] = t('validation.middle_name_required');
        } elseif (mb_strlen($input['middle_name']) > 80) {
            $errors['middle_name'] = t('validation.value_too_long');
        }

        if ($input['first_name'] === '') {
            $errors['first_name'] = t('validation.first_name_required');
        } elseif (mb_strlen($input['first_name']) > 80) {
            $errors['first_name'] = t('validation.value_too_long');
        }

        if (!in_array($input['community_role'], Config::get('registration.community_roles', []), true)) {
            $errors['community_role'] = t('validation.community_role_required');
        }

        if ($input['address_street'] === '') {
            $errors['address_street'] = t('validation.address_street_required');
        } elseif (mb_strlen($input['address_street']) > 150) {
            $errors['address_street'] = t('validation.value_too_long');
        }

        if ($input['address_building'] === '') {
            $errors['address_building'] = t('validation.address_building_required');
        } elseif (mb_strlen($input['address_building']) > 100) {
            $errors['address_building'] = t('validation.value_too_long');
        }

        if ($input['address_apartment'] === '') {
            $errors['address_apartment'] = t('validation.address_apartment_required');
        } elseif (mb_strlen($input['address_apartment']) > 20) {
            $errors['address_apartment'] = t('validation.value_too_long');
        }

        if (!in_array($input['district'], Config::get('registration.districts', []), true)) {
            $errors['district'] = t('validation.district_required');
        }

        if ($input['passport_number'] === '') {
            $errors['passport_number'] = t('validation.passport_number_required');
        } elseif (preg_match('/^[A-Z0-9]{5,20}$/', $input['passport_number']) !== 1) {
            $errors['passport_number'] = t('validation.passport_number_invalid');
        }

        // Un passeport expiré reste accepté : la fiche recense une identité,
        // elle ne vérifie pas la validité d'un titre de voyage.
        if ($input['passport_expiry'] === '') {
            $errors['passport_expiry'] = t('validation.passport_expiry_required');
        } elseif (!self::isValidDate($input['passport_expiry'])) {
            $errors['passport_expiry'] = t('validation.date_invalid');
        }

        // Culte obligatoire mais jamais forcé : "prefer_not_to_say" est une
        // réponse valide — donnée sensible (RGPD/KVKK), le membre doit
        // toujours pouvoir ne rien en dire.
        if ($input['religion'] === '') {
            $errors['religion'] = t('validation.religion_required');
        } elseif (!in_array($input['religion'], Config::get('registration.religions', []), true)) {
            $errors['religion'] = t('validation.option_invalid');
        }

        return $errors;
    }

    /**
     * Valeurs prêtes pour members (chaînes vides des champs facultatifs →
     * NULL), full_name recalculé à partir des trois parties du nom.
     *
     * @return array<string, string|null>
     */
    public static function toColumns(array $input): array
    {
        $nullable = static fn (string $value): ?string => $value !== '' ? $value : null;

        return [
            'full_name'         => self::fullName($input),
            'last_name'         => $input['last_name'],
            // Encore nullable par prudence (profils antérieurs à la règle).
            'middle_name'       => $nullable($input['middle_name']),
            'first_name'        => $input['first_name'],
            'community_role'    => $input['community_role'],
            'address_street'    => $input['address_street'],
            'address_building'  => $nullable($input['address_building']),
            'address_apartment' => $nullable($input['address_apartment']),
            'district'          => $input['district'],
            'passport_number'   => $input['passport_number'],
            'passport_expiry'   => $input['passport_expiry'],
            'religion'          => $nullable($input['religion']),
        ];
    }

    /**
     * Inverse de toColumns() pour pré-remplir un formulaire depuis une ligne
     * members. Les parties du nom restent vides pour un membre inscrit
     * avant la migration 018 : full_name n'est pas découpé (l'ordre des
     * mots n'y est pas fiable), le membre les complète lui-même.
     *
     * @return array<string, string>
     */
    public static function fromMember(array $member): array
    {
        $keys = array_keys(self::collect([]));

        return array_combine($keys, array_map(
            static fn (string $key): string => (string) ($member[$key] ?? ''),
            $keys
        ));
    }

    /**
     * Ordre d'usage courant à l'oral (Prénom Nom Post-nom), plafonné à la
     * taille de members.full_name (VARCHAR 150 — 3 × 80 peut dépasser).
     */
    public static function fullName(array $input): string
    {
        $parts = array_filter([$input['first_name'], $input['last_name'], $input['middle_name']], static fn (string $part): bool => $part !== '');

        return mb_substr(implode(' ', $parts), 0, 150);
    }

    private static function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
