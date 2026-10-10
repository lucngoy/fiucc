<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Candidate;
use App\Models\Member;

/**
 * Cloche du layout admin : ce qui attend une action du bureau, calculé à
 * chaque requête depuis les données existantes — pas une table de
 * notifications. Pas de "lu/non lu" : une tâche disparaît quand elle est
 * traitée (membre validé/rejeté, candidature approuvée/rejetée), pas quand
 * l'admin l'a vue. Un vrai système de notifications (événements, historique,
 * lu/non lu) demanderait un ticket dédié.
 *
 * Injecté automatiquement dans layouts.admin par App\Core\Controller::view().
 */
final class AdminTasks
{
    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    public static function pending(): array
    {
        $tasks = [];

        // Dossiers renvoyés après un refus (migration 020) : tâche distincte,
        // et retirés du compte des nouvelles inscriptions pour ne pas les
        // compter deux fois dans le total de la cloche.
        $resubmitted = Member::countResubmitted();
        if ($resubmitted > 0) {
            $tasks[] = [
                'label' => t('admin.tasks.resubmitted_members', ['count' => $resubmitted]),
                'count' => $resubmitted,
                'url'   => '/admin/members?status=pending',
            ];
        }

        $pendingMembers = Member::countByStatus('pending') - $resubmitted;
        if ($pendingMembers > 0) {
            $tasks[] = [
                'label' => t('admin.tasks.pending_members', ['count' => $pendingMembers]),
                'count' => $pendingMembers,
                'url'   => '/admin/members?status=pending',
            ];
        }

        foreach (Candidate::pendingCountsByElection() as $row) {
            $tasks[] = [
                'label' => t('admin.tasks.pending_candidates', ['count' => $row['pending'], 'election' => $row['title']]),
                'count' => $row['pending'],
                'url'   => '/admin/elections/' . $row['election_id'] . '/candidates',
            ];
        }

        return $tasks;
    }
}
