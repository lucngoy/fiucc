<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Élections (FIUCC-59/60, sections 16-25 du cahier des charges) — création
 * et configuration côté admin. Candidatures (FIUCC-61), vote (FIUCC-64+) et
 * résultats (FIUCC-69+) : pas construits ici.
 *
 * Verrouillage de la configuration (décision explicite, conversation
 * FIUCC-59) : le titre, les dates de début et les postes ne sont
 * modifiables que tant que status = 'draft' — chaque écriture qui y touche
 * porte sa propre garde SQL (WHERE ... status = 'draft') EN PLUS du
 * contrôle applicatif dans Admin\ElectionController, pour qu'un bug côté
 * contrôleur ne puisse pas à lui seul corrompre une élection déjà entrée
 * en phase de candidatures ou ouverte au vote. Seule exception : end_at
 * reste modifiable pendant que status = 'open' (extendEndAt() ci-dessous),
 * pour permettre au bureau de prolonger ou raccourcir un scrutin en cours
 * sans jamais toucher le reste de la configuration ni les postes.
 */
final class Election extends Model
{
    private const ADMIN_PER_PAGE = 20;
    public const STATUSES = ['draft', 'candidacies', 'candidacies_closed', 'open', 'closed', 'published'];

    /**
     * @param array{search?: string, status?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminList(array $filters, int $page, int $perPage = self::ADMIN_PER_PAGE): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = 'title LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, self::STATUSES, true)) {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM elections {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés — même raison qu'ailleurs dans le projet
        // (Event::adminList()...) : PDO::ATTR_EMULATE_PREPARES à false
        // n'accepte pas de valeur liée à cet endroit, sûr ici parce que
        // $page/$perPage sont typés int (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at, e.status,
                    (SELECT COUNT(*) FROM election_positions ep WHERE ep.election_id = e.id) AS position_count
             FROM elections e
             {$where}
             ORDER BY e.start_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM elections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Liste publique (FIUCC-62/63 déclarent la page, mais aucune ne liste
     * les élections — /elections pointait dans le vide, découvert après
     * coup une fois le reste du module construit). Jamais 'draft' — même
     * règle que App\Controllers\ElectionController::findElectionOr404() :
     * une élection en configuration n'est pas encore publique. Pas de
     * pagination : le nombre d'élections réelles pour une seule communauté
     * reste faible, une page suffit.
     */
    /**
     * "Active" au sens du dashboard admin (FIUCC-23) : le vote est
     * effectivement ouvert en ce moment — pas candidacies/closed/published,
     * des phases qui ne demandent aucune surveillance en temps réel.
     */
    /**
     * Élections en cours (tableau de bord admin) : lancées et pas encore
     * publiées — candidatures, liste arrêtée, vote, dépouillement. Pas
     * seulement 'open' : une élection en phase de candidatures est bien
     * en cours pour le bureau, même si le vote n'a pas commencé.
     */
    public static function countInProgress(): int
    {
        return (int) static::db()
            ->query("SELECT COUNT(*) FROM elections WHERE status IN ('candidacies', 'candidacies_closed', 'open', 'closed')")
            ->fetchColumn();
    }

    public static function publicList(): array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM elections WHERE status != 'draft' ORDER BY start_at DESC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Compte à rebours public (FIUCC-62) — dérivé de status/start_at/end_at,
     * jamais stocké. Borné pour ne jamais afficher un compte à rebours figé
     * à zéro : le bureau ouvre/ferme le scrutin manuellement (FIUCC-59 §25,
     * "ouvrir l'élection"/"fermer l'élection"), donc start_at ou end_at peut
     * être dépassé sans que le statut ait encore changé — dans ce cas
     * l'affichage doit dire "imminent", pas montrer 00:00:00:00.
     *
     * @param array{status: string, start_at: string, end_at: string} $election
     * @return array{state: string, target: ?string} target est un datetime
     *         MySQL (fuseau app.timezone) à compter, ou null si l'état n'a
     *         pas de compte à rebours à afficher.
     */
    public static function votingWindowState(array $election): array
    {
        $now = time();
        $start = strtotime((string) $election['start_at']);
        $end = strtotime((string) $election['end_at']);
        $status = $election['status'];

        if (in_array($status, ['draft', 'candidacies', 'candidacies_closed'], true)) {
            return $now >= $start
                ? ['state' => 'opening_imminent', 'target' => null]
                : ['state' => 'before_open', 'target' => (string) $election['start_at']];
        }

        if ($status === 'open') {
            return $now >= $end
                ? ['state' => 'closing_imminent', 'target' => null]
                : ['state' => 'open_countdown', 'target' => (string) $election['end_at']];
        }

        return ['state' => $status === 'closed' ? 'closed' : 'published', 'target' => null];
    }

    /**
     * @param array{title: string, description: ?string, start_at: string, end_at: string} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO elections (title, description, candidacy_deadline, candidate_list_at, candidacy_fee_required, candidacy_fee_details,
                                    rules_document_id, start_at, end_at, handover_date, status)
             VALUES (:title, :description, :candidacy_deadline, :candidate_list_at, :candidacy_fee_required, :candidacy_fee_details,
                     :rules_document_id, :start_at, :end_at, :handover_date, 'draft')"
        );
        $stmt->execute([
            'title'         => $data['title'],
            'description'   => $data['description'],
            'candidacy_deadline' => $data['candidacy_deadline'] ?? null,
            'candidate_list_at'  => $data['candidate_list_at'] ?? null,
            'candidacy_fee_required' => !empty($data['candidacy_fee_required']) ? 1 : 0,
            'candidacy_fee_details'  => $data['candidacy_fee_details'] ?? null,
            'rules_document_id'      => $data['rules_document_id'] ?? null,
            'start_at'      => $data['start_at'],
            'end_at'        => $data['end_at'],
            'handover_date' => $data['handover_date'] ?? null,
        ]);

        return (int) static::db()->lastInsertId();
    }

    /**
     * Garde SQL : n'affecte aucune ligne si l'élection n'est plus 'draft' au
     * moment de l'écriture (course possible entre l'affichage du formulaire
     * et la soumission — le contrôleur vérifie déjà le statut avant
     * d'arriver ici, ceci est la seconde ligne de défense, pas la première).
     *
     * @param array{title: string, description: ?string, start_at: string, end_at: string} $data
     * @return bool true si l'élection était bien 'draft' et a été modifiée
     */
    public static function update(int $id, array $data): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET title = :title, description = :description, candidacy_deadline = :candidacy_deadline,
                    candidate_list_at = :candidate_list_at,
                    candidacy_fee_required = :candidacy_fee_required, candidacy_fee_details = :candidacy_fee_details,
                    rules_document_id = :rules_document_id,
                    start_at = :start_at, end_at = :end_at, handover_date = :handover_date
             WHERE id = :id AND status = 'draft'"
        );
        $stmt->execute([
            'title'         => $data['title'],
            'description'   => $data['description'],
            'candidacy_deadline' => $data['candidacy_deadline'] ?? null,
            'candidate_list_at'  => $data['candidate_list_at'] ?? null,
            'candidacy_fee_required' => !empty($data['candidacy_fee_required']) ? 1 : 0,
            'candidacy_fee_details'  => $data['candidacy_fee_details'] ?? null,
            'rules_document_id'      => $data['rules_document_id'] ?? null,
            'start_at'      => $data['start_at'],
            'end_at'        => $data['end_at'],
            'handover_date' => $data['handover_date'] ?? null,
            'id'            => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Seule exception au verrouillage : prolonger ou raccourcir la clôture
     * d'une élection déjà ouverte (décision explicite, conversation
     * FIUCC-59) — jamais le titre, jamais start_at, jamais les postes.
     * Garde SQL sur status = 'open' ; la validation de la nouvelle date
     * (postérieure à maintenant) est du ressort de l'appelant
     * (Admin\ElectionController::extendDeadline()), pas de ce modèle.
     *
     * @return bool true si l'élection était bien 'open' et a été modifiée
     */
    public static function extendEndAt(int $id, string $newEndAt): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET end_at = :end_at WHERE id = :id AND status = 'open'"
        );
        $stmt->execute(['end_at' => $newEndAt, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'draft' -> 'candidacies' : verrouille définitivement titre/dates/
     * postes (Admin\ElectionController::update()/storePosition()/etc. ne
     * s'appliquent qu'à 'draft'). Exige au moins un poste — faire
     * progresser une élection sans aucun poste n'a pas de sens, et la
     * configuration devient justement figée à cet instant — ainsi qu'une
     * date limite de dépôt (migration 023) : on n'ouvre pas des
     * candidatures sans dire aux membres jusqu'à quand.
     */
    public static function startCandidacies(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections e
             SET e.status = 'candidacies'
             WHERE e.id = :id AND e.status = 'draft' AND e.candidacy_deadline IS NOT NULL
               AND EXISTS (SELECT 1 FROM election_positions WHERE election_id = e.id)"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Prolongation de la date limite de dépôt (décision du bureau
     * 2026-10-10) — seulement pendant que les candidatures sont ouvertes,
     * vers une date future et antérieure au début du vote : prolonger au-
     * delà du scrutin n'aurait pas de sens. Garde SQL en plus du contrôle
     * de Admin\ElectionController::extendCandidacyDeadline().
     */
    public static function extendCandidacyDeadline(int $id, string $newDeadline): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET candidacy_deadline = :deadline
             WHERE id = :id AND status = 'candidacies'
               AND :deadline_future > NOW() AND :deadline_before_vote < start_at
               AND (candidacy_deadline IS NULL OR :deadline_later > candidacy_deadline)
               AND (candidate_list_at IS NULL OR :deadline_before_list <= candidate_list_at)"
        );
        // Seulement repoussée, jamais avancée (décision du bureau
        // 2026-10-21) : une date annoncée publiquement ne doit priver
        // personne du dépôt sur lequel il comptait (art. 8).
        $stmt->execute([
            'deadline'             => $newDeadline,
            'deadline_future'      => $newDeadline,
            'deadline_before_vote' => $newDeadline,
            'deadline_later'       => $newDeadline,
            'deadline_before_list' => $newDeadline,
            'id'                   => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'candidacies' -> 'candidacies_closed' (migration 023) : fin du dépôt,
     * début de la vérification des dossiers et de la campagne. Seulement
     * une fois la date limite annoncée passée — clôturer plus tôt priverait
     * des membres d'un délai qui leur avait été promis (art. 16, égalité
     * entre candidats). Pour clôturer plus tard, le bureau prolonge.
     */
    public static function closeCandidacies(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'candidacies_closed'
             WHERE id = :id AND status = 'candidacies'
               AND candidacy_deadline IS NOT NULL AND candidacy_deadline <= NOW()"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'candidacies_closed' -> 'candidacies' avec une nouvelle date limite
     * (décision du bureau 2026-10-21) : si le dépôt a été clos trop tôt,
     * tant que le vote n'est pas ouvert. Jamais pour un second tour (pas
     * de dépôt : ses candidats sont les ex aequo du 1er tour). Les dossiers
     * déjà décidés gardent leur décision.
     */
    public static function reopenCandidacies(int $id, string $newDeadline): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'candidacies', candidacy_deadline = :deadline
             WHERE id = :id AND status = 'candidacies_closed' AND first_round_id IS NULL
               AND :deadline_future > NOW() AND :deadline_before_vote < start_at
               AND (candidate_list_at IS NULL OR :deadline_before_list <= candidate_list_at)"
        );
        $stmt->execute([
            'deadline'             => $newDeadline,
            'deadline_future'      => $newDeadline,
            'deadline_before_vote' => $newDeadline,
            'deadline_before_list' => $newDeadline,
            'id'                   => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Dates du vote et de la passation tant que le vote n'est pas ouvert
     * (décision du bureau 2026-10-21) : avant le premier bulletin, rien
     * n'est encore acquis. Mêmes règles qu'à la création : ouverture dans
     * le futur et après la date limite des candidatures, 24 h minimum, fin
     * au plus tard le jour de la passation. Une fois le vote ouvert, son
     * début ne bouge plus (extendEndAt() pour la fin, updateHandoverDate()
     * pour la passation).
     */
    public static function rescheduleVote(int $id, string $startAt, string $endAt, string $handoverDate, ?string $candidateListAt = null): bool
    {
        // Publication de la liste (migration 027) : après la date limite
        // des candidatures, au plus tard à l'ouverture du vote. null garde
        // la valeur en place (élection antérieure sans date, second tour).
        $stmt = static::db()->prepare(
            "UPDATE elections SET start_at = :start_at, end_at = :end_at, handover_date = :handover_date,
                    candidate_list_at = COALESCE(:list_at, candidate_list_at)
             WHERE id = :id AND status IN ('candidacies', 'candidacies_closed')
               AND :start_future > NOW()
               AND (COALESCE(:list_check, candidate_list_at) IS NULL OR (
                   (candidacy_deadline IS NULL OR COALESCE(:list_after_deadline, candidate_list_at) >= candidacy_deadline)
                   AND COALESCE(:list_before_vote, candidate_list_at) <= :start_for_list
               ))
               AND (candidacy_deadline IS NULL OR candidacy_deadline < :start_after_deadline)
               AND TIMESTAMPDIFF(SECOND, :start_duration, :end_duration) >= 86400
               AND DATE(:end_handover) <= :handover_check"
        );
        $stmt->execute([
            'start_at'             => $startAt,
            'end_at'               => $endAt,
            'handover_date'        => $handoverDate,
            'list_at'              => $candidateListAt,
            'list_check'           => $candidateListAt,
            'list_after_deadline'  => $candidateListAt,
            'list_before_vote'     => $candidateListAt,
            'start_for_list'       => $startAt,
            'id'                   => $id,
            'start_future'         => $startAt,
            'start_after_deadline' => $startAt,
            'start_duration'       => $startAt,
            'end_duration'         => $endAt,
            'end_handover'         => $endAt,
            'handover_check'       => $handoverDate,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Description (et le calendrier électoral qu'elle contient, art. 8)
     * jusqu'à la publication des résultats (décision du bureau
     * 2026-10-21) : un texte d'information, sans effet sur le scrutin —
     * contrairement au titre et aux postes, qui restent figés hors
     * brouillon. En brouillon, le formulaire complet s'en charge (update()).
     */
    public static function updateDescription(int $id, ?string $description): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET description = :description
             WHERE id = :id AND status IN ('candidacies', 'candidacies_closed', 'open', 'closed')"
        );
        $stmt->execute(['description' => $description, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Date de passation jusqu'à la publication des résultats : une fois
     * publiés, le gouvernement est installé avec ce mandat
     * (Government::syncFromElectionResults()), la date ne bouge plus.
     */
    public static function updateHandoverDate(int $id, string $handoverDate): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET handover_date = :handover_date
             WHERE id = :id AND status IN ('draft', 'candidacies', 'candidacies_closed', 'open', 'closed')
               AND DATE(end_at) <= :handover_check"
        );
        $stmt->execute(['handover_date' => $handoverDate, 'id' => $id, 'handover_check' => $handoverDate]);

        return $stmt->rowCount() > 0;
    }

    /**
     * La liste des candidats est-elle publique (migration 027) ? Oui dès
     * candidate_list_at atteinte ; une élection sans date (antérieure à la
     * migration) garde l'ancien comportement : liste visible.
     */
    public static function candidateListPublished(array $election): bool
    {
        $listAt = $election['candidate_list_at'] ?? null;

        return $listAt === null || $listAt === '' || strtotime((string) $listAt) <= time();
    }

    /**
     * Dépôt de candidature encore possible (lot 3 : candidature déposée
     * par le membre lui-même) — statut ET date limite, les deux.
     */
    public static function acceptsCandidacies(array $election): bool
    {
        return $election['status'] === 'candidacies'
            && !empty($election['candidacy_deadline'])
            && strtotime((string) $election['candidacy_deadline']) > time();
    }

    /**
     * 'candidacies_closed' -> 'open' : ouvre le vote — plus jamais
     * directement depuis 'candidacies' (migration 023) : la liste des
     * candidats doit être arrêtée avant le premier bulletin. Ne vérifie pas
     * qu'un candidat approuvé existe par poste — un poste sans candidat
     * approuvé reste votable en blanc (App\Models\Vote::castBallot()).
     */
    public static function open(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'open' WHERE id = :id AND status = 'candidacies_closed'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'open' -> 'closed'. Pas de garde temporelle (end_at atteint ou non) :
     * le bureau garde la main pour fermer plus tôt si besoin, symétrique à
     * extendEndAt() qui permet déjà de prolonger. §25 du cahier : "Une
     * élection fermée ne doit plus accepter de votes" — déjà garanti
     * ailleurs, chaque garde SQL de vote vérifie elections.status = 'open'
     * en direct, jamais déduit d'une horloge.
     */
    public static function close(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'closed' WHERE id = :id AND status = 'open'"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * 'closed' -> 'published' (FIUCC-68, §22 du cahier des charges) — étape
     * séparée et délibérée d'open()/close() : le bureau ferme, vérifie,
     * PUIS publie, jamais les deux à la fois. results_published_at
     * (colonne déjà prévue en migration 001) horodate la publication elle-
     * même, distincte de created_at/updated_at.
     */
    public static function publishResults(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections SET status = 'published', results_published_at = NOW()
             WHERE id = :id AND status = 'closed'"
        );
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return false;
        }

        Government::syncFromElectionResults($id);

        return true;
    }

    /**
     * Rappel manuel de vote (FIUCC-73, §31 du cahier) — la garde de 12h est
     * l'écriture elle-même, pas un pré-contrôle séparé : deux clics rapides
     * en course ne peuvent donc jamais faire passer les deux (rowCount() = 0
     * pour le second, quel que soit l'ordre d'arrivée). Décision explicite
     * conversation FIUCC-73 : un seul bouton "Envoyer un rappel", réutilisé
     * à chaque relance — pas de distinction mi-parcours/avant-clôture, le
     * bureau juge du moment, pas une formule.
     *
     * @return bool true si l'élection était 'open' et hors fenêtre des 12h
     *              (aucun rappel envoyé, ou le dernier remonte à plus de
     *              12h) — l'appelant ne doit envoyer les emails que si ceci
     *              renvoie true.
     */
    public static function claimReminderSlot(int $id): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE elections
             SET last_reminder_sent_at = NOW()
             WHERE id = :id AND status = 'open'
               AND (last_reminder_sent_at IS NULL OR last_reminder_sent_at <= NOW() - INTERVAL 12 HOUR)"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Pas de suppression au sens large (un scrutin est une archive
     * communautaire, décision explicite conversation FIUCC-59) — seulement
     * un brouillon qui n'a encore aucun poste, pour ne pas laisser un
     * brouillon créé par erreur polluer la liste indéfiniment. Garde SQL
     * double : status = 'draft' ET aucune ligne dans election_positions.
     *
     * @return bool true si l'élection remplissait les conditions et a été supprimée
     */
    public static function delete(int $id): bool
    {
        $stmt = static::db()->prepare(
            "DELETE FROM elections
             WHERE id = :id AND status = 'draft'
               AND NOT EXISTS (SELECT 1 FROM election_positions WHERE election_id = elections.id)"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public static function positions(int $electionId): array
    {
        $stmt = static::db()->prepare(
            'SELECT id, name, description, max_votes, sort_order
             FROM election_positions WHERE election_id = :election_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['election_id' => $electionId]);

        return $stmt->fetchAll();
    }

    /**
     * Garde SQL via INSERT ... SELECT ... WHERE plutôt qu'un simple INSERT :
     * n'insère rien si l'élection n'est plus 'draft', au lieu de faire
     * confiance au seul contrôle applicatif du contrôleur.
     *
     * @param array{name: string, description: ?string, max_votes: int, sort_order: int} $data
     * @return int|null null si l'élection n'était pas (ou plus) 'draft'
     */
    public static function addPosition(int $electionId, array $data): ?int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO election_positions (election_id, name, description, max_votes, sort_order)
             SELECT :election_id, :name, :description, :max_votes, :sort_order
             FROM elections WHERE id = :election_id_check AND status = 'draft'"
        );
        $stmt->execute([
            'election_id'       => $electionId,
            'election_id_check' => $electionId,
            'name'              => $data['name'],
            'description'       => $data['description'],
            'max_votes'         => $data['max_votes'],
            'sort_order'        => $data['sort_order'],
        ]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return (int) static::db()->lastInsertId();
    }

    /**
     * UPDATE avec jointure sur elections pour garder la garde de statut
     * dans la même requête atomique, plutôt qu'un SELECT de statut suivi
     * d'un UPDATE séparé (fenêtre de course entre les deux).
     *
     * @param array{name: string, description: ?string, max_votes: int, sort_order: int} $data
     * @return bool true si le poste appartenait bien à cette élection, 'draft', et a été modifié
     */
    public static function updatePosition(int $positionId, int $electionId, array $data): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             SET ep.name = :name, ep.description = :description, ep.max_votes = :max_votes, ep.sort_order = :sort_order
             WHERE ep.id = :position_id AND ep.election_id = :election_id AND e.status = 'draft'"
        );
        $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'],
            'max_votes'   => $data['max_votes'],
            'sort_order'  => $data['sort_order'],
            'position_id' => $positionId,
            'election_id' => $electionId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return bool true si le poste appartenait bien à cette élection, 'draft', et a été supprimé
     */
    public static function deletePosition(int $positionId, int $electionId): bool
    {
        $stmt = static::db()->prepare(
            "DELETE ep FROM election_positions ep
             INNER JOIN elections e ON e.id = ep.election_id
             WHERE ep.id = :position_id AND ep.election_id = :election_id AND e.status = 'draft'"
        );
        $stmt->execute(['position_id' => $positionId, 'election_id' => $electionId]);

        return $stmt->rowCount() > 0;
    }
}
