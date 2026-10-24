<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\ActivityLog;
use App\Models\Election;
use Throwable;

/**
 * Passages de statut d'une élection à l'heure prévue (décision du bureau
 * 2026-10-23) : clôture du dépôt à la date limite, ouverture du vote à
 * start_at, fermeture à end_at. La publication des résultats reste
 * manuelle — la Commission vérifie, puis publie.
 *
 * Sans cron (l'hébergement n'en garantit pas) : appelée au début de chaque
 * requête (public/index.php), comme la publication programmée des
 * actualités (News). Le premier visiteur après l'heure fait le passage —
 * un électeur qui envoie son bulletin à 00:00:05 ouvre le vote dans la
 * même requête, avant que les gardes de vote ne le lisent.
 *
 * Journalisé sans user_id : personne n'a cliqué. Les boutons manuels
 * restent (ouvrir/fermer plus tôt, prolonger, rouvrir le dépôt).
 */
final class ElectionSchedule
{
    public static function tick(): void
    {
        // Jamais bloquant : une erreur ici ne doit pas rendre tout le site
        // inaccessible — la requête suivante réessaiera.
        try {
            foreach (Election::closeCandidaciesOnSchedule() as $id) {
                ActivityLog::record(null, 'election.candidacies_closed_on_schedule', 'election', $id);
            }

            foreach (Election::openOnSchedule() as $id) {
                ActivityLog::record(null, 'election.opened_on_schedule', 'election', $id);
            }

            foreach (Election::closeOnSchedule() as $id) {
                ActivityLog::record(null, 'election.closed_on_schedule', 'election', $id);
            }
        } catch (Throwable $e) {
            Logger::error('election', 'Passage de statut automatique échoué', ['error' => $e->getMessage()]);
        }
    }
}
