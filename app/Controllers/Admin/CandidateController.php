<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Member;
use App\Models\User;
use App\Services\Upload;
use RuntimeException;

/**
 * Gestion des candidatures (FIUCC-61, sections 16-25 du cahier des
 * charges). Contrôleur séparé d'Admin\ElectionController (décision
 * explicite conversation FIUCC-61) : la gestion des candidats est un
 * cycle propre (ajout → approbation/rejet → éventuel retrait), assez
 * distinct de la configuration de l'élection pour ne pas surcharger
 * ElectionController.
 *
 * Même discipline que le reste de l'épopée élections : chaque écriture
 * porte un contrôle applicatif ET une garde SQL dans App\Models\Candidate
 * (défense en profondeur, une course entre deux requêtes ne doit jamais
 * pouvoir modifier une candidature verrouillée).
 *
 * Un candidat doit être un membre validated + student
 * (Member::isVoterEligible()) — vérifié à la création, ré-vérifié à
 * l'approbation (un membre peut devenir inéligible entre les deux).
 *
 * Le retrait pendant que l'élection est 'open' ne touche jamais la table
 * votes : voir le commentaire de classe d'App\Models\Candidate. Aucun
 * code de ce contrôleur ne doit jamais afficher un message différent
 * selon qu'un électeur a voté ou non pour un candidat retiré — le nombre
 * de voix (Candidate::voteCount()) n'apparaît que dans la confirmation de
 * retrait affichée à l'admin, jamais côté électeur.
 */
final class CandidateController extends Controller
{
    public function index(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];
        $candidates = Candidate::adminListForElection($electionId);

        foreach ($candidates as &$candidate) {
            $candidate['eligible'] = Member::isVoterEligible([
                'status'      => $candidate['member_status'],
                'status_type' => $candidate['member_status_type'],
            ]);
            $candidate['vote_count'] = $candidate['status'] === 'approved'
                ? Candidate::voteCount((int) $candidate['id'])
                : null;
        }
        unset($candidate);

        $this->view('admin.candidates.index', [
            'title'       => t('admin.candidates.title'),
            'activeNav'   => 'elections',
            'adminEmail'  => $this->currentAdminEmail(),
            'election'    => $election,
            'candidates'  => $candidates,
            'canManage'   => in_array($election['status'], ['draft', 'candidacies'], true),
            'canWithdraw' => $election['status'] !== 'published',
            'notice'      => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    public function create(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $this->showForm($election, null, $this->emptyInput(), []);
    }

    public function store(array $params): void
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        $electionId = (int) $election['id'];

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $positions = Election::positions($electionId);
        $input = $this->collectInput();
        $errors = $this->validate($input, $positions, null);

        $photo = null;

        if ($errors === [] && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $photo = $this->storePhoto();
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($election, null, $input, $errors);
            return;
        }

        $candidateId = Candidate::create([
            'election_position_id' => $input['election_position_id'],
            'member_id'            => $input['member_id'],
            'ballot_number'        => $input['ballot_number'],
            'bio'                  => $input['bio'] !== '' ? $input['bio'] : null,
            'manifesto'            => $input['manifesto'] !== '' ? $input['manifesto'] : null,
            'photo'                => $photo,
        ]);

        // Course : le statut de l'élection a changé, ou le membre est
        // devenu inéligible, entre le contrôle ci-dessus et cet INSERT
        // (garde SQL de Candidate::create()).
        if ($candidateId === null) {
            if ($photo !== null) {
                @unlink(BASE_PATH . '/public/uploads/' . $photo);
            }
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.created', 'candidate', $candidateId);

        $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=created');
    }

    public function edit(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $candidateId = (int) ($params['candidate_id'] ?? 0);
        $election = Election::find($electionId);
        $candidate = Candidate::find($candidateId);

        if ($election === null || $candidate === null || (int) $candidate['election_id'] !== $electionId) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $this->showForm($election, $candidate, $this->toInput($candidate), []);
    }

    public function update(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $candidateId = (int) ($params['candidate_id'] ?? 0);
        $election = Election::find($electionId);
        $candidate = Candidate::find($candidateId);

        if ($election === null || $candidate === null || (int) $candidate['election_id'] !== $electionId) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $positions = Election::positions($electionId);
        $input = $this->collectInput();
        // Le membre n'est jamais reproposé à la modification : identité du
        // candidat fixée à la création, seul son profil (poste, numéro,
        // bio, manifeste, photo) reste modifiable avant approbation.
        $errors = $this->validateProfile($input, $positions, $candidateId);

        $newPhoto = null;

        if ($errors === [] && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPhoto = $this->storePhoto();
            } catch (RuntimeException $exception) {
                $errors['photo'] = $exception->getMessage();
            }
        }

        if ($errors !== []) {
            $this->showForm($election, $candidate, $input, $errors);
            return;
        }

        $updated = Candidate::updateProfile($candidateId, [
            'election_position_id' => $input['election_position_id'],
            'ballot_number'        => $input['ballot_number'],
            'bio'                  => $input['bio'] !== '' ? $input['bio'] : null,
            'manifesto'            => $input['manifesto'] !== '' ? $input['manifesto'] : null,
        ]);

        if (!$updated) {
            if ($newPhoto !== null) {
                @unlink(BASE_PATH . '/public/uploads/' . $newPhoto);
            }
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        if ($newPhoto !== null) {
            $oldPhoto = $candidate['photo'];
            Candidate::updatePhoto($candidateId, $newPhoto);

            if ($oldPhoto !== null && $oldPhoto !== '') {
                @unlink(BASE_PATH . '/public/uploads/' . $oldPhoto);
            }
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.updated', 'candidate', $candidateId);

        $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=updated');
    }

    public function approve(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $candidateId = (int) ($params['candidate_id'] ?? 0);
        $election = Election::find($electionId);
        $candidate = Candidate::find($candidateId);

        if ($election === null || $candidate === null || (int) $candidate['election_id'] !== $electionId) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        if (!Member::isVoterEligible(['status' => $candidate['member_status'], 'status_type' => $candidate['member_status_type']])) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=member_ineligible');
            return;
        }

        $approved = Candidate::approve($candidateId);

        if (!$approved) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.approved', 'candidate', $candidateId);

        $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=approved');
    }

    public function reject(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $candidateId = (int) ($params['candidate_id'] ?? 0);
        $election = Election::find($electionId);
        $candidate = Candidate::find($candidateId);

        if ($election === null || $candidate === null || (int) $candidate['election_id'] !== $electionId) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if (!in_array($election['status'], ['draft', 'candidacies'], true)) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $rejected = Candidate::reject($candidateId);

        if (!$rejected) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.rejected', 'candidate', $candidateId);

        $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=rejected');
    }

    /**
     * Irréversible en pratique (décision explicite FIUCC-61) : raison
     * obligatoire, journalisée avec le nombre de voix déjà exprimées au
     * moment du retrait — l'admin qui retire un candidat doit assumer ce
     * qu'il fait, mais rien de tout cela n'est jamais montré à un électeur
     * ni rapproché d'un bulletin (votes n'a ni member_id ni référence au
     * token, voir App\Models\Candidate).
     */
    public function withdraw(array $params): void
    {
        $electionId = (int) ($params['id'] ?? 0);
        $candidateId = (int) ($params['candidate_id'] ?? 0);
        $election = Election::find($electionId);
        $candidate = Candidate::find($candidateId);

        if ($election === null || $candidate === null || (int) $candidate['election_id'] !== $electionId) {
            $this->redirect('/admin/elections?notice=not_found');
            return;
        }

        if ($election['status'] === 'published') {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=locked');
            return;
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=reason_required');
            return;
        }

        $voteCount = Candidate::voteCount($candidateId);
        $withdrawn = Candidate::withdraw($candidateId);

        if (!$withdrawn) {
            $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=cannot_withdraw');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'candidate.withdrawn',
            'candidate',
            $candidateId,
            'reason: ' . $reason . ' (votes at withdrawal: ' . $voteCount . ')'
        );

        $this->redirect('/admin/elections/' . $electionId . '/candidates?notice=withdrawn');
    }

    private function showForm(array $election, ?array $candidate, array $input, array $errors): void
    {
        $this->view('admin.candidates.form', [
            'title'      => $candidate === null ? t('admin.candidates.create_title') : t('admin.candidates.edit_title'),
            'activeNav'  => 'elections',
            'adminEmail' => $this->currentAdminEmail(),
            'election'   => $election,
            'candidate'  => $candidate,
            'positions'  => Election::positions((int) $election['id']),
            'members'    => $candidate === null ? Member::eligibleForCandidacy() : [],
            'old'        => $input,
            'errors'     => $errors,
        ], 'layouts.admin');
    }

    private function emptyInput(): array
    {
        return [
            'election_position_id' => '',
            'member_id'            => '',
            'ballot_number_raw'    => '',
            'bio'                  => '',
            'manifesto'            => '',
        ];
    }

    private function toInput(array $candidate): array
    {
        return [
            'election_position_id' => (string) $candidate['election_position_id'],
            'member_id'            => (string) $candidate['member_id'],
            'ballot_number_raw'    => $candidate['ballot_number'] !== null ? (string) $candidate['ballot_number'] : '',
            'bio'                  => (string) ($candidate['bio'] ?? ''),
            'manifesto'            => (string) ($candidate['manifesto'] ?? ''),
        ];
    }

    private function collectInput(): array
    {
        return [
            'election_position_id' => (string) ($_POST['election_position_id'] ?? ''),
            'member_id'            => (string) ($_POST['member_id'] ?? ''),
            'ballot_number_raw'    => trim((string) ($_POST['ballot_number'] ?? '')),
            'bio'                  => trim((string) ($_POST['bio'] ?? '')),
            'manifesto'            => trim((string) ($_POST['manifesto'] ?? '')),
        ];
    }

    private function validate(array &$input, array $positions, ?int $excludeCandidateId): array
    {
        $errors = $this->validateProfile($input, $positions, $excludeCandidateId);

        if (!ctype_digit($input['member_id'])) {
            $errors['member_id'] = t('validation.option_invalid');
        } else {
            $member = Member::findById((int) $input['member_id']);

            if ($member === null || !Member::isVoterEligible($member)) {
                $errors['member_id'] = t('admin.candidates.field.member_ineligible');
            } else {
                $input['member_id'] = (int) $input['member_id'];
            }
        }

        return $errors;
    }

    private function validateProfile(array &$input, array $positions, ?int $excludeCandidateId): array
    {
        $errors = [];
        $positionIds = array_column($positions, 'id');

        if (!ctype_digit($input['election_position_id']) || !in_array((int) $input['election_position_id'], $positionIds, true)) {
            $errors['election_position_id'] = t('validation.option_invalid');
        } else {
            $input['election_position_id'] = (int) $input['election_position_id'];
        }

        $input['ballot_number'] = $this->parseBallotNumber($input['ballot_number_raw'], $errors);

        if (!isset($errors['election_position_id']) && !isset($errors['ballot_number']) && $input['ballot_number'] !== null) {
            if (Candidate::ballotNumberTaken($input['election_position_id'], $input['ballot_number'], $excludeCandidateId)) {
                $errors['ballot_number'] = t('admin.candidates.field.ballot_number_taken');
            }
        }

        return $errors;
    }

    private function parseBallotNumber(string $raw, array &$errors): ?int
    {
        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 255) {
            $errors['ballot_number'] = t('admin.candidates.field.ballot_number_invalid');
            return null;
        }

        return (int) $raw;
    }

    private function storePhoto(): string
    {
        return Upload::storeImage(
            $_FILES['photo'],
            'candidates',
            (int) Config::get('security.upload_candidate_photo_max_dimension', 800),
            (int) Config::get('security.upload_max_size_mb', 15) * 1024 * 1024,
            (int) Config::get('security.upload_max_input_dimension', 5000)
        );
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
