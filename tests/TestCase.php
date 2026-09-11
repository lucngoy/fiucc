<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Member;
use App\Models\User;
use App\Models\VotingToken;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base commune des tests du module électoral (FIUCC-79).
 *
 * Réinitialisation entre chaque test : un TRUNCATE complet des tables
 * concernées en setUp(), pas une transaction englobante annulée en
 * tearDown(). Ce n'est pas un choix par défaut — App\Models\Vote::
 * castBallot() ouvre et referme lui-même une transaction
 * (beginTransaction()/commit()/rollBack()) sur la connexion PDO partagée ;
 * PDO ne supporte pas les transactions imbriquées (un second
 * beginTransaction() alors qu'une est déjà ouverte lève une exception). Un
 * test qui ouvrirait sa propre transaction englobante casserait donc tout
 * scénario appelant du code de vote réel. Le TRUNCATE est plus lent, mais
 * correct quel que soit le nombre de transactions ouvertes par le code
 * testé — et la base de test reste petite.
 *
 * Les fixtures passent par les vraies méthodes de modèle (Election::create(),
 * Candidate::create(), VotingToken::issue()...), jamais par un INSERT direct
 * qui court-circuiterait les gardes qu'on est justement en train de tester —
 * sauf mention explicite dans le nom de la méthode (ex. plantToken(), qui
 * doit délibérément contourner VotingToken::issue() pour tester la garde
 * indépendante d'App\Models\Vote::castBallot()).
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Tables à vider avant chaque test, dans un ordre qui n'a pas besoin
     * d'être topologique puisque les clés étrangères sont désactivées le
     * temps de l'opération.
     *
     * email_verifications/login_throttle ajoutées au tour Registration/
     * Security de FIUCC-79 — leur absence a réellement cassé un test
     * (OtpTest) : `users` étant TRUNCATE (donc son AUTO_INCREMENT remis à
     * zéro) à chaque test, un user_id est réutilisé d'un test à l'autre ;
     * une ligne email_verifications laissée par un test précédent pour ce
     * même id faussait le calcul du cooldown de renvoi (Otp::
     * secondsUntilResendAllowed()) dans le test suivant. À étendre à chaque
     * nouvelle fixture qui référence `users`/`members` par id.
     */
    private const TABLES_TO_RESET = [
        'votes', 'voting_tokens', 'candidates', 'election_positions',
        'elections', 'members', 'users', 'email_verifications', 'login_throttle',
    ];

    protected PDO $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connection();
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES_TO_RESET as $table) {
            $this->db->exec("TRUNCATE TABLE {$table}");
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function makeUser(?string $email = null): int
    {
        $email ??= 'test.' . bin2hex(random_bytes(6)) . '@final.edu.tr';

        return User::create($email, password_hash('Test1234!', PASSWORD_DEFAULT));
    }

    /**
     * @param array{status?: string, status_type?: string, user_id?: int, full_name?: string} $overrides
     *        status par défaut 'validated', status_type par défaut 'student'
     *        — les deux conditions d'App\Models\Member::isVoterEligible().
     *        Member::create() ne permet pas de choisir ces deux colonnes
     *        (verrouillées à 'pending'/'student' à l'inscription réelle) ;
     *        ici on crée d'abord un membre normal puis on ajuste directement
     *        ces deux colonnes, seule façon d'obtenir un fixture alumni ou
     *        déjà validé sans repasser par tout le circuit de validation
     *        admin à chaque test.
     */
    protected function makeMember(array $overrides = []): int
    {
        $userId = $overrides['user_id'] ?? $this->makeUser();

        $memberId = Member::create([
            'user_id'        => $userId,
            'full_name'      => $overrides['full_name'] ?? 'Membre de test',
            'student_id'     => $overrides['student_id'] ?? ('T' . bin2hex(random_bytes(5))),
            'date_of_birth'  => null,
            'gender'         => null,
            'city_of_origin' => null,
            'faculty'        => null,
            'department'     => null,
            'year'           => null,
            'phone'          => null,
            'whatsapp'       => null,
            'instagram'      => null,
            'is_public'      => 1,
            'show_email'     => 0,
            'show_phone'     => 0,
        ]);

        $stmt = $this->db->prepare(
            'UPDATE members SET status = :status, status_type = :status_type WHERE id = :id'
        );
        $stmt->execute([
            'status'      => $overrides['status'] ?? 'validated',
            'status_type' => $overrides['status_type'] ?? 'student',
            'id'          => $memberId,
        ]);

        return $memberId;
    }

    /**
     * @param array{title?: string, start_at?: string, end_at?: string} $overrides
     *        Fenêtre par défaut : déjà ouverte (start_at dans le passé),
     *        encore loin de sa fin (end_at dans un jour) — le cas courant
     *        pour un test qui n'a pas besoin de tester la fermeture elle-même.
     */
    protected function makeElection(array $overrides = []): int
    {
        return Election::create([
            'title'       => $overrides['title'] ?? ('Élection de test ' . bin2hex(random_bytes(4))),
            'description' => null,
            'start_at'    => $overrides['start_at'] ?? date('Y-m-d H:i:s', strtotime('-1 hour')),
            'end_at'      => $overrides['end_at'] ?? date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);
    }

    protected function makePosition(int $electionId, array $overrides = []): int
    {
        $positionId = Election::addPosition($electionId, [
            'name'        => $overrides['name'] ?? 'Poste de test',
            'description' => null,
            'max_votes'   => $overrides['max_votes'] ?? 1,
            'sort_order'  => $overrides['sort_order'] ?? 0,
        ]);

        if ($positionId === null) {
            throw new RuntimeException('Impossible de créer le poste de test (élection pas en draft ?).');
        }

        return $positionId;
    }

    protected function makeApprovedCandidate(int $positionId, ?int $memberId = null): int
    {
        $memberId ??= $this->makeMember();

        $candidateId = Candidate::create([
            'election_position_id' => $positionId,
            'member_id'            => $memberId,
            'ballot_number'        => null,
            'bio'                  => null,
            'manifesto'            => null,
            'photo'                => null,
        ]);

        if ($candidateId === null || !Candidate::approve($candidateId)) {
            throw new RuntimeException('Impossible de créer/approuver le candidat de test.');
        }

        return $candidateId;
    }

    /**
     * Construit une élection ouverte, un poste, un candidat approuvé — le
     * point de départ de la plupart des scénarios de vote. Fait progresser
     * l'élection par les vraies transitions de statut (Election::
     * startCandidacies()/open()), jamais par un UPDATE direct du statut :
     * ces transitions portent elles-mêmes des gardes qu'on veut exercer, pas
     * contourner.
     *
     * @return array{election_id: int, position_id: int, candidate_id: int, member_id: int}
     */
    protected function makeOpenElectionWithCandidate(array $overrides = []): array
    {
        $electionId = $this->makeElection($overrides);
        $positionId = $this->makePosition($electionId);
        $candidateMemberId = $this->makeMember(['full_name' => 'Candidat de test']);
        $candidateId = $this->makeApprovedCandidate($positionId, $candidateMemberId);

        if (!Election::startCandidacies($electionId) || !Election::open($electionId)) {
            throw new RuntimeException('Impossible de faire progresser l\'élection de test jusqu\'à "open".');
        }

        return [
            'election_id'  => $electionId,
            'position_id'  => $positionId,
            'candidate_id' => $candidateId,
            'member_id'    => $candidateMemberId,
        ];
    }

    /**
     * Émet un jeton via le vrai chemin applicatif (App\Models\VotingToken::
     * issue()) — échoue (assertion) si le membre n'était pas éligible ou
     * l'élection pas ouverte, pour qu'un test dont la fixture est mal
     * construite échoue tôt et clairement plutôt que de planter plus loin.
     */
    protected function issueToken(int $electionId, int $memberId): string
    {
        $code = VotingToken::issue($electionId, $memberId);

        if ($code === null) {
            throw new RuntimeException('Émission du jeton de test refusée par VotingToken::issue().');
        }

        return $code;
    }

    /**
     * Plante directement une ligne voting_tokens SANS passer par
     * VotingToken::issue() — contourne délibérément sa garde d'éligibilité,
     * pour tester la garde INDÉPENDANTE portée par App\Models\Vote::
     * castBallot() lui-même (défense en profondeur : castBallot() ne doit
     * jamais compter sur le fait qu'un jeton "n'aurait pas dû" exister).
     * Utilisé uniquement pour les scénarios "élection pas encore ouverte" /
     * "élection fermée" au niveau de l'écriture du bulletin, où un jeton
     * existant est le point de départ du scénario, pas ce qu'on teste.
     */
    protected function plantToken(int $electionId, int $memberId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO voting_tokens (election_id, member_id, token_hash, expires_at)
             VALUES (:election_id, :member_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'election_id' => $electionId,
            'member_id'   => $memberId,
            'token_hash'  => hash('sha256', random_bytes(16)),
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);
    }

    protected function hasVoted(int $electionId, int $memberId): bool
    {
        return VotingToken::hasVoted($electionId, $memberId);
    }

    protected function voteCountForCandidate(int $candidateId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM votes WHERE candidate_id = :candidate_id');
        $stmt->execute(['candidate_id' => $candidateId]);

        return (int) $stmt->fetchColumn();
    }
}
