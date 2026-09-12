<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Member extends Model
{
    private const DIRECTORY_PER_PAGE = 20;

    public static function studentIdExists(string $studentId): bool
    {
        $stmt = static::db()->prepare('SELECT 1 FROM members WHERE student_id = :student_id LIMIT 1');
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Recensement (FIUCC-32) : status 'pending' (validation admin à venir,
     * section 24 du cahier des charges) et email_verified_at NULL (OTP à
     * venir, FIUCC-33/34) — deux étapes indépendantes du cycle de vie.
     *
     * membership_number est généré après l'insertion à partir de l'id auto-
     * incrémenté (FIUCC-2026-00042), pas avant : la colonne est NOT NULL et
     * UNIQUE, donc un placeholder temporaire garantit l'unicité sans risque
     * de collision entre deux inscriptions concurrentes.
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            "INSERT INTO members (
                user_id, membership_number, full_name, student_id, date_of_birth, gender,
                city_of_origin, faculty, department, year, phone, whatsapp, instagram,
                status_type, status, is_public, show_email, show_phone
            ) VALUES (
                :user_id, :membership_number, :full_name, :student_id, :date_of_birth, :gender,
                :city_of_origin, :faculty, :department, :year, :phone, :whatsapp, :instagram,
                'student', 'pending', :is_public, :show_email, :show_phone
            )"
        );

        $stmt->execute([
            'user_id'         => $data['user_id'],
            'membership_number' => 'TMP-' . bin2hex(random_bytes(8)),
            'full_name'       => $data['full_name'],
            'student_id'      => $data['student_id'],
            'date_of_birth'   => $data['date_of_birth'],
            'gender'          => $data['gender'],
            'city_of_origin'  => $data['city_of_origin'],
            'faculty'         => $data['faculty'],
            'department'      => $data['department'],
            'year'            => $data['year'],
            'phone'           => $data['phone'],
            'whatsapp'        => $data['whatsapp'],
            'instagram'       => $data['instagram'],
            'is_public'       => $data['is_public'],
            'show_email'      => $data['show_email'],
            'show_phone'      => $data['show_phone'],
        ]);

        $memberId = (int) static::db()->lastInsertId();

        $update = static::db()->prepare('UPDATE members SET membership_number = :number WHERE id = :id');
        $update->execute([
            'number' => sprintf('FIUCC-%s-%05d', date('Y'), $memberId),
            'id'     => $memberId,
        ]);

        return $memberId;
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, full_name, email_verified_at FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function markEmailVerified(int $userId): void
    {
        $stmt = static::db()->prepare('UPDATE members SET email_verified_at = NOW() WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Cycle de statut (section 24 du cahier des charges) : pending, validated,
     * rejected, suspended. Aucune contrainte de transition entre eux — le
     * cahier liste ces trois actions comme des capacités indépendantes de
     * l'admin, pas un enchaînement strict. validate()/reject()/suspend()
     * effacent chacune les raisons de l'état négatif qu'elles remplacent,
     * pour qu'une ancienne raison de rejet ne survive pas à une validation.
     *
     * $adminUserId n'est jamais l'auteur de sa propre validation — c'est
     * toujours un compte admin distinct du membre concerné (appliqué par le
     * futur contrôleur d'administration, FIUCC-44 ; pas revérifié ici, ce
     * modèle ne connaît pas la notion de session).
     */
    public static function validate(int $memberId, int $adminUserId): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'validated', rejection_reason = NULL, suspension_reason = NULL,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['admin_id' => $adminUserId, 'id' => $memberId]);
    }

    public static function reject(int $memberId, int $adminUserId, string $reason): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'rejected', rejection_reason = :reason, suspension_reason = NULL,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['reason' => $reason, 'admin_id' => $adminUserId, 'id' => $memberId]);
    }

    public static function suspend(int $memberId, int $adminUserId, ?string $reason = null): void
    {
        $stmt = static::db()->prepare(
            "UPDATE members
             SET status = 'suspended', suspension_reason = :reason,
                 validated_by = :admin_id, validated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['reason' => $reason, 'admin_id' => $adminUserId, 'id' => $memberId]);
    }

    /**
     * Statistique "Registered Students" de la page d'accueil (section 10 du
     * cahier des charges, FIUCC-47) : membres validés par un admin, jamais
     * les pending/rejected/suspended — une inscription non confirmée ou
     * refusée ne fait pas partie de la communauté affichée publiquement.
     * status_type='student' précisément parce que le libellé dit
     * "Students", pas "Members" — les alumni ont leur propre décompte
     * ailleurs (phase 6, pas construit ici).
     */
    public static function registeredStudentsCount(): int
    {
        return (int) static::db()
            ->query("SELECT COUNT(*) FROM members WHERE status = 'validated' AND status_type = 'student'")
            ->fetchColumn();
    }

    /**
     * Droit de vote (CLAUDE.md, règle non négociable) : status='validated'
     * ET status_type='student' — un alumni reste membre sans être électeur.
     * Point d'entrée unique pour ne pas réimplémenter cette règle ailleurs
     * (élections, phase 4).
     */
    public static function isVoterEligible(array $member): bool
    {
        return ($member['status'] ?? null) === 'validated'
            && ($member['status_type'] ?? null) === 'student';
    }

    /**
     * Sélecteur de membre du formulaire de candidature (FIUCC-61) — même
     * règle qu'isVoterEligible() : un candidat doit pouvoir voter, un
     * alumni ne se présente pas. Recherche facultative par nom/numéro
     * étudiant ; liste complète (plafonnée) si $search est vide, une
     * communauté étudiante reste d'une taille où un <select> simple
     * suffit, pas besoin d'un pickeur JS.
     */
    public static function eligibleForCandidacy(string $search = '', int $limit = 200): array
    {
        $conditions = ["status = 'validated'", "status_type = 'student'"];
        $params = [];

        $search = trim($search);
        if ($search !== '') {
            $conditions[] = '(full_name LIKE :search1 OR student_id LIKE :search2)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        // LIMIT interpolé — même raison qu'ailleurs dans le projet
        // (Event::adminList()...) : PDO::ATTR_EMULATE_PREPARES à false
        // n'accepte pas de valeur liée à cet endroit, sûr ici parce que
        // $limit est typé int (strict_types=1).
        $stmt = static::db()->prepare(
            "SELECT id, full_name, student_id FROM members {$where} ORDER BY full_name ASC LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findByUserIdWithStatus(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, full_name, status, status_type FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Vue complète d'un membre pour sa propre page profil (FIUCC-37) : la
     * restriction "ne jamais afficher publiquement" (section 15 du cahier —
     * student_id, email, téléphone) ne s'applique qu'au profil PUBLIC
     * (FIUCC-84, annuaire), pas ici — un membre voit toujours ses propres
     * données. is_public/show_email/show_phone/mentor_* et photo (upload)
     * appartiennent à l'édition du profil (FIUCC-38), pas à cet affichage.
     */
    public static function findFullByUserId(int $userId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, membership_number, full_name, student_id, date_of_birth, gender,
                    city_of_origin, faculty, department, year, arrival_year, graduation_year,
                    phone, whatsapp, instagram, photo, status_type, profession, company, city, country,
                    status, rejection_reason, suspension_reason, email_verified_at, created_at
             FROM members WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Édition du profil (FIUCC-38) : uniquement les champs de contact et
     * d'affiliation académique. student_id, email et le cycle de statut
     * restent hors de portée — les changer soi-même contournerait la
     * validation admin (section 24) ou l'ancrage d'identité du recensement.
     * is_public/show_email/show_phone (confidentialité) : voir
     * privacySettings()/updatePrivacySettings() ci-dessous (FIUCC-83), un
     * formulaire séparé, jamais mélangé à cette édition générale. mentor_*
     * reste hors de portée pour FIUCC-87.
     */
    public static function updateProfile(int $memberId, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE members SET
                full_name = :full_name, date_of_birth = :date_of_birth, gender = :gender,
                city_of_origin = :city_of_origin, faculty = :faculty, department = :department,
                year = :year, phone = :phone, whatsapp = :whatsapp, instagram = :instagram
             WHERE id = :id'
        );
        $stmt->execute([
            'full_name'      => $data['full_name'],
            'date_of_birth'  => $data['date_of_birth'],
            'gender'         => $data['gender'],
            'city_of_origin' => $data['city_of_origin'],
            'faculty'        => $data['faculty'],
            'department'     => $data['department'],
            'year'           => $data['year'],
            'phone'          => $data['phone'],
            'whatsapp'       => $data['whatsapp'],
            'instagram'      => $data['instagram'],
            'id'             => $memberId,
        ]);
    }

    public static function updatePhoto(int $memberId, string $photoPath): void
    {
        $stmt = static::db()->prepare('UPDATE members SET photo = :photo WHERE id = :id');
        $stmt->execute(['photo' => $photoPath, 'id' => $memberId]);
    }

    /**
     * Réglages de confidentialité (FIUCC-83) : lecture/écriture séparées de
     * updateProfile() ci-dessus à dessein (voir son commentaire) — un
     * formulaire distinct, une seule responsabilité, jamais mélangé aux
     * champs d'identité/contact.
     *
     * @return array{is_public: int, show_email: int, show_phone: int}|null
     */
    public static function privacySettings(int $memberId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT is_public, show_email, show_phone FROM members WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{is_public: int, show_email: int, show_phone: int} $data
     */
    public static function updatePrivacySettings(int $memberId, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE members SET is_public = :is_public, show_email = :show_email, show_phone = :show_phone
             WHERE id = :id'
        );
        $stmt->execute([
            'is_public'  => $data['is_public'],
            'show_email' => $data['show_email'],
            'show_phone' => $data['show_phone'],
            'id'         => $memberId,
        ]);
    }

    /**
     * Colonnes de tri autorisées pour l'admin (section 24 du cahier des
     * charges) — whitelist plutôt qu'une colonne prise telle quelle dans la
     * query string, qui permettrait d'injecter n'importe quoi dans l'ORDER BY.
     */
    private const ADMIN_SORT_COLUMNS = [
        'name'      => 'm.full_name',
        'faculty'   => 'm.faculty',
        'year'      => 'm.year',
        'status'    => 'm.status',
        'submitted' => 'm.created_at',
    ];

    /**
     * Conditions WHERE partagées entre adminSearch() (FIUCC-43) et
     * adminExportRows() (FIUCC-45) — un export doit filtrer exactement comme
     * la liste à l'écran, jamais une logique dupliquée qui pourrait diverger.
     *
     * @param array{search?: string, status?: string, faculty?: string, year?: string} $filters
     * @return array{0: string, 1: array<string, mixed>} [clause WHERE (ou ''), params liés]
     */
    private static function adminFilterClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(m.full_name LIKE :search1 OR m.student_id LIKE :search2 OR m.membership_number LIKE :search3 OR u.email LIKE :search4)';
            $like = '%' . $search . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
            $params['search4'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = 'm.status = :status';
            $params['status'] = $status;
        }

        $faculty = trim((string) ($filters['faculty'] ?? ''));
        if ($faculty !== '') {
            $conditions[] = 'm.faculty = :faculty';
            $params['faculty'] = $faculty;
        }

        $year = trim((string) ($filters['year'] ?? ''));
        if ($year !== '') {
            $conditions[] = 'm.year = :year';
            $params['year'] = (int) $year;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * Liste admin (FIUCC-43, section 24 du cahier des charges) : recherche,
     * filtres (status/faculty/year), tri, pagination. Jointure users pour
     * l'email — pas dupliqué dans members.
     *
     * @param array{search?: string, status?: string, faculty?: string, year?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function adminSearch(array $filters, string $sort, string $dir, int $page, int $perPage): array
    {
        [$where, $params] = self::adminFilterClause($filters);
        $orderColumn = self::ADMIN_SORT_COLUMNS[$sort] ?? self::ADMIN_SORT_COLUMNS['submitted'];
        $orderDir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM members m JOIN users u ON u.id = m.user_id {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés, pas liés : avec PDO::ATTR_EMULATE_PREPARES
        // à false (préparation native, voir App\Core\Database), MySQL rejette
        // des valeurs liées comme chaînes à cet endroit. Sûr ici parce que
        // $page/$perPage sont typés int dans la signature (strict_types=1) —
        // jamais une valeur brute de la query string.
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT m.id, m.membership_number, m.full_name, m.student_id, m.faculty, m.year, m.photo,
                    m.status_type, m.status, m.created_at, u.email
             FROM members m
             JOIN users u ON u.id = m.user_id
             {$where}
             ORDER BY {$orderColumn} {$orderDir}
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Export CSV admin (FIUCC-45) : mêmes filtres que adminSearch() (recherche,
     * statut, faculté, année), sans pagination — l'export porte sur tout ce
     * qui correspond, pas seulement la page affichée à l'écran. Ordre stable
     * (date d'inscription) plutôt que le tri courant de l'écran, qui n'a pas
     * de sens pour un fichier consommé hors de la page.
     *
     * @param array{search?: string, status?: string, faculty?: string, year?: string} $filters
     */
    public static function adminExportRows(array $filters): array
    {
        [$where, $params] = self::adminFilterClause($filters);

        $stmt = static::db()->prepare(
            "SELECT m.membership_number, m.full_name, m.student_id, m.faculty, m.department, m.year,
                    m.status, m.created_at, u.email
             FROM members m
             JOIN users u ON u.id = m.user_id
             {$where}
             ORDER BY m.created_at DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Facultés distinctes réellement présentes dans les données (comme le
     * filtre de /community/government) — pas une liste figée qui pourrait ne
     * correspondre à aucun membre.
     */
    public static function distinctFaculties(): array
    {
        $stmt = static::db()->query(
            "SELECT DISTINCT faculty FROM members WHERE faculty IS NOT NULL AND faculty != '' ORDER BY faculty ASC"
        );

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Options des filtres de l'annuaire (FIUCC-82), scopées à is_public = 1
     * — jamais les valeurs de distinctFaculties() ci-dessus (non scopée,
     * réservée à l'admin) : une faculté ou une ville qui n'existe que chez
     * des membres restés privés ne doit pas apparaître dans un menu, ça
     * révélerait indirectement qu'au moins un membre privé la partage.
     *
     * @return array{faculties: array<int, string>, cities: array<int, string>, arrival_years: array<int, int>}
     */
    public static function directoryFilterOptions(): array
    {
        $base = "FROM members WHERE status = 'validated' AND is_public = 1";

        $faculties = static::db()
            ->query("SELECT DISTINCT faculty {$base} AND faculty IS NOT NULL AND faculty != '' ORDER BY faculty ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $cities = static::db()
            ->query("SELECT DISTINCT city_of_origin {$base} AND city_of_origin IS NOT NULL AND city_of_origin != '' ORDER BY city_of_origin ASC")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $arrivalYears = static::db()
            ->query("SELECT DISTINCT arrival_year {$base} AND arrival_year IS NOT NULL ORDER BY arrival_year DESC")
            ->fetchAll(\PDO::FETCH_COLUMN);

        return [
            'faculties'     => $faculties,
            'cities'        => $cities,
            'arrival_years' => array_map('intval', $arrivalYears),
        ];
    }

    /**
     * Annuaire public (FIUCC-82, section Annuaire des membres du cahier) :
     * "profil public simplifié : prénom, nom, faculté, année, photo,
     * réseaux sociaux" — exactement ces champs, rien de plus. email/phone
     * n'y figurent délibérément pas : show_email/show_phone existent pour
     * une page de profil individuel (FIUCC-84, pas encore construite), pas
     * pour cette liste — ne pas anticiper dessus maintenant (CLAUDE.md).
     *
     * is_public = 1 filtre l'INCLUSION même dans la liste : un membre qui
     * n'a pas opté n'apparaît pas du tout, pas même son nom — c'est la
     * seule redaction nécessaire ici, portée par le WHERE plutôt que par
     * un filtre après coup côté PHP ou vue (même discipline que CASE WHEN
     * dans Candidate::publicListForElection(), FIUCC-63, pour la même
     * raison : qu'aucun futur appelant ne puisse fuiter en oubliant un
     * contrôle). instagram/whatsapp ne sont pas rédigés séparément : une
     * fois is_public = 1, ils font partie du "profil public simplifié" que
     * le membre a accepté de montrer, sans bascule propre dans le schéma.
     *
     * student/alumni/honorary tous inclus : le cahier dit que les alumni
     * "conservent... l'accès à l'annuaire" sans les exclure d'y apparaître,
     * et l'esprit réseau de la phase 6 est justement de garder le contact
     * au-delà de la scolarité.
     *
     * @param array{search?: string, faculty?: string, city?: string, arrival_year?: string} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public static function directoryList(array $filters, int $page, int $perPage = self::DIRECTORY_PER_PAGE): array
    {
        $conditions = ["m.status = 'validated'", 'm.is_public = 1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = 'm.full_name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $faculty = trim((string) ($filters['faculty'] ?? ''));
        if ($faculty !== '') {
            $conditions[] = 'm.faculty = :faculty';
            $params['faculty'] = $faculty;
        }

        $city = trim((string) ($filters['city'] ?? ''));
        if ($city !== '') {
            $conditions[] = 'm.city_of_origin = :city';
            $params['city'] = $city;
        }

        $arrivalYear = trim((string) ($filters['arrival_year'] ?? ''));
        if ($arrivalYear !== '') {
            $conditions[] = 'm.arrival_year = :arrival_year';
            $params['arrival_year'] = (int) $arrivalYear;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = static::db()->prepare("SELECT COUNT(*) FROM members m {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET interpolés, pas liés — mêmes raisons qu'Event::
        // publicList() : PDO::ATTR_EMULATE_PREPARES à false, $page/$perPage
        // typés int (strict_types=1).
        $offset = ($page - 1) * $perPage;
        $stmt = static::db()->prepare(
            "SELECT m.id, m.full_name, m.faculty, m.year, m.arrival_year, m.city_of_origin, m.photo,
                    m.instagram, m.whatsapp
             FROM members m
             {$where}
             ORDER BY m.full_name ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Fiche minimale pour une action admin unitaire (validate/reject/suspend,
     * FIUCC-44) — juste de quoi vérifier le statut courant, empêcher
     * l'auto-action et notifier le bon compte.
     */
    public static function findById(int $id): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT id, user_id, full_name, status, status_type FROM members WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Sous-ensemble des ids demandés réellement dans le statut source attendu
     * (FIUCC-44, action groupée) — une validation groupée mal ciblée ne doit
     * jamais faire entrer un membre rejeté/suspendu dans le corps électoral
     * en silence : on ne touche qu'aux lignes qui sont vraiment dans l'état
     * de départ attendu, le reste est ignoré et compté séparément par
     * l'appelant (nombre demandé vs. nombre réellement affecté).
     *
     * @param int[] $ids
     * @return array<int, array{id: int, user_id: int, full_name: string}>
     */
    public static function findManyByIdsAndStatus(array $ids, string $status): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = static::db()->prepare(
            "SELECT id, user_id, full_name FROM members WHERE status = ? AND id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$status], $ids));

        return $stmt->fetchAll();
    }
}
