<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\ActivityLog;
use App\Models\PasswordReset;
use App\Models\User;

/**
 * Changement et réinitialisation du mot de passe — un seul endroit pour
 * les trois chemins (profil membre, compte admin, lien "mot de passe
 * oublié"), pour qu'aucun n'applique une règle différente des autres.
 *
 * Après chaque changement réussi : tous les liens de réinitialisation
 * encore en circulation pour ce compte sont invalidés, et l'action est
 * journalisée dans activity_logs (jamais le mot de passe lui-même).
 */
final class Passwords
{
    /**
     * Même règle que le recensement (RegistrationController) :
     * security.password_min_length, confirmation identique.
     *
     * @return array<string, string> erreurs par champ, vide si valide
     */
    public static function validateNew(string $password, string $confirmation): array
    {
        $errors = [];
        $minLength = (int) Config::get('security.password_min_length', 8);

        if ($password === '') {
            $errors['password'] = t('validation.password_required');
        } elseif (mb_strlen($password) < $minLength) {
            $errors['password'] = t('validation.password_too_short', ['min' => $minLength]);
        }

        if ($confirmation === '') {
            $errors['password_confirmation'] = t('validation.password_confirmation_required');
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'] = t('validation.password_mismatch');
        }

        return $errors;
    }

    /**
     * Changement depuis une session déjà ouverte : le mot de passe actuel
     * est exigé, pour qu'une session laissée ouverte (ordinateur partagé
     * de la bibliothèque) ne suffise pas à s'approprier le compte.
     *
     * @return array<string, string> erreurs par champ, vide si changé
     */
    public static function change(int $userId, string $current, string $password, string $confirmation): array
    {
        $errors = self::validateNew($password, $confirmation);
        $hash = User::passwordHash($userId);

        if ($current === '' || $hash === null || !password_verify($current, $hash)) {
            $errors['current_password'] = t('password.error_current_invalid');
            ActivityLog::record($userId, 'password_change_failed', 'user', $userId);
        }

        if ($errors !== []) {
            return $errors;
        }

        self::apply($userId, $password, 'password_changed');

        // Même principe qu'à la connexion : nouvel identifiant de session
        // après un changement d'identifiants.
        session_regenerate_id(true);

        return [];
    }

    /**
     * Demande de lien. Ne renvoie rien : l'appelant affiche toujours le
     * même message, que l'adresse existe ou non, que le plafond horaire
     * soit atteint ou non — sinon le formulaire servirait à tester quelles
     * adresses sont inscrites.
     */
    public static function requestReset(string $email): void
    {
        $user = User::findByEmail($email);

        if ($user === null) {
            return;
        }

        $userId = (int) $user['id'];
        $maxPerHour = (int) Config::get('security.password_reset_max_per_hour', 3);

        if (PasswordReset::countRecentFor($userId, 60) >= $maxPerHour) {
            ActivityLog::record($userId, 'password_reset_rate_limited', 'user', $userId);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $ttlMinutes = (int) Config::get('security.password_reset_ttl_minutes', 60);

        PasswordReset::create($userId, hash('sha256', $token), $ttlMinutes);
        ActivityLog::record($userId, 'password_reset_requested', 'user', $userId);

        $url = rtrim((string) Config::get('app.url', ''), '/') . '/password/reset?token=' . $token;

        Mail::send(
            (string) $user['email'],
            (string) $user['email'],
            t('mail.password_reset.subject'),
            t('mail.password_reset.heading'),
            t('mail.password_reset.body', ['url' => e($url), 'minutes' => $ttlMinutes]),
            [$token]
        );
    }

    public static function resetTokenIsUsable(string $token): bool
    {
        return self::isWellFormed($token) && PasswordReset::findUsable(hash('sha256', $token)) !== null;
    }

    /**
     * @return array{ok: bool, errors: array<string, string>}
     *               ok=false avec errors vide : lien invalide/expiré/déjà
     *               utilisé — l'appelant renvoie vers une nouvelle demande.
     */
    public static function resetWithToken(string $token, string $password, string $confirmation): array
    {
        $record = self::isWellFormed($token) ? PasswordReset::findUsable(hash('sha256', $token)) : null;

        if ($record === null) {
            return ['ok' => false, 'errors' => []];
        }

        $errors = self::validateNew($password, $confirmation);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        if (!PasswordReset::consume((int) $record['id'])) {
            return ['ok' => false, 'errors' => []];
        }

        self::apply((int) $record['user_id'], $password, 'password_reset_completed');

        return ['ok' => true, 'errors' => []];
    }

    private static function apply(int $userId, string $password, string $logAction): void
    {
        User::updatePassword($userId, password_hash($password, PASSWORD_DEFAULT));
        PasswordReset::invalidateAllFor($userId);
        ActivityLog::record($userId, $logAction, 'user', $userId);
    }

    private static function isWellFormed(string $token): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/', $token) === 1;
    }
}
