<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\ActivityLog;
use App\Models\Member;
use DateTimeImmutable;

/**
 * Conservation limitée de la photo du passeport (migration 018) : supprimée
 * registration.passport_scan_retention_days jours (90 par défaut) après la
 * dernière décision admin — validation, refus ou suspension, toutes datées
 * par members.validated_at. Elle sert à vérifier l'identité au moment de
 * décider ; ensuite, le numéro et la date d'expiration saisis suffisent
 * (minimisation RGPD/KVKK, décision utilisateur 2026-10-09).
 *
 * Un dossier 'pending' n'est jamais purgé, même ancien : la photo y sert
 * encore. Un membre refusé qui renvoie son dossier après la purge doit
 * fournir une nouvelle photo (ProfileController::update()).
 *
 * Appelée par bin/purge_passport_scans.php (cron quotidien, voir README)
 * ET à chaque ouverture de la liste des membres côté admin : la promesse
 * des 90 jours tient même si le cron n'a jamais été configuré, tant que le
 * back office est utilisé. Requête légère, idempotente.
 */
final class PassportScanRetention
{
    public static function days(): int
    {
        return max(1, (int) Config::get('registration.passport_scan_retention_days', 90));
    }

    /**
     * @return int nombre de photos supprimées
     */
    public static function purgeExpired(): int
    {
        $purged = 0;

        foreach (Member::passportScansDecidedBefore(self::days()) as $row) {
            @unlink(Upload::storagePath((string) $row['passport_scan']));
            Member::updatePassportScan((int) $row['id'], null);
            ActivityLog::record(null, 'member.passport_scan_purged', 'member', (int) $row['id']);
            $purged++;
        }

        return $purged;
    }

    /**
     * Date de suppression prévue (Y-m-d) d'une photo encore présente, pour
     * l'afficher à l'admin — null si aucune décision n'a encore été prise
     * (dossier en attente : pas de compte à rebours).
     */
    public static function deletionDate(array $member): ?string
    {
        if (empty($member['passport_scan']) || $member['status'] === 'pending' || empty($member['validated_at'])) {
            return null;
        }

        return (new DateTimeImmutable((string) $member['validated_at']))
            ->modify('+' . self::days() . ' days')
            ->format('Y-m-d');
    }
}
