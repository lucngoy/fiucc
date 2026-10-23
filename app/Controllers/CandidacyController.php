<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\View;
use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Document;
use App\Models\Election;
use App\Models\ElectionCommission;
use App\Models\Member;
use App\Services\CandidacyDossier;
use App\Services\CandidacyNotifier;
use App\Services\Upload;
use RuntimeException;

/**
 * "Ma candidature" (lot 3 de la refonte électorale ; règlement électoral
 * 2026, art. 9-14) : le membre dépose lui-même sa candidature et son
 * dossier, la modifie ou la retire tant que le dépôt est ouvert, puis suit
 * son statut. Route ['validated'] : seul un membre au recensement validé y
 * accède — l'éligibilité fine (étudiant, hors Commission, une candidature
 * par élection, dépôt ouvert) est revérifiée par les gardes SQL de
 * Candidate::submitByMember()/updateByMember()/cancelByMember().
 *
 * Aucune donnée de vote n'est lue ici (CLAUDE.md, règles 1 et 13) : une
 * candidature n'a aucun lien avec un bulletin.
 */
final class CandidacyController extends Controller
{
    private const BIO_MIN = 30;
    private const BIO_MAX = 3000;
    private const MANIFESTO_MIN = 50;
    private const MANIFESTO_MAX = 10000;

    public function show(array $params): void
    {
        $context = $this->context($params);

        if ($context === null) {
            return;
        }

        $this->render($context, $this->inputFrom($context['candidacy']), [], (string) ($_GET['notice'] ?? ''));
    }

    public function submit(array $params): void
    {
        $context = $this->context($params);

        if ($context === null) {
            return;
        }

        ['election' => $election, 'member' => $member, 'candidacy' => $candidacy] = $context;
        $electionId = (int) $election['id'];
        $memberId = (int) $member['id'];

        if ($context['state'] !== 'open' && $context['state'] !== 'editable') {
            $this->redirect('/elections/' . $electionId . '/candidacy?notice=locked');
            return;
        }

        CandidacyDossier::purgeStalePending();

        $input = [
            'position_id'  => trim((string) ($_POST['position_id'] ?? '')),
            'bio'          => trim(str_replace("\r\n", "\n", (string) ($_POST['bio'] ?? ''))),
            'manifesto'    => trim(str_replace("\r\n", "\n", (string) ($_POST['manifesto'] ?? ''))),
            'accept_rules' => (string) ($_POST['accept_rules'] ?? ''),
        ];
        $errors = $this->validate($input, $context['positions']);

        // Pièces reçues : stockées tout de suite, même si un autre champ
        // est en erreur (voir CandidacyDossier).
        $isNew = $candidacy === null;
        $present = $isNew ? CandidacyDossier::pending($electionId) : $this->presentDocuments((int) $candidacy['id']);

        foreach (CandidacyDossier::fields() as $field => $definition) {
            if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $piece = CandidacyDossier::store($_FILES[$field], $definition['kind'], $isNew);
            } catch (RuntimeException $exception) {
                $errors[$field] = $exception->getMessage();
                continue;
            }

            if ($isNew) {
                CandidacyDossier::rememberPending($electionId, $field, $piece);
            } else {
                $this->attach((int) $candidacy['id'], $definition, $piece);
            }

            $present[$field] = $piece;
        }

        foreach (CandidacyDossier::missing($present, (bool) $election['candidacy_fee_required']) as $field) {
            $errors[$field] ??= t('candidacy.error.piece_required');
        }

        if ($errors !== []) {
            $this->render($context, $input, $errors, '');
            return;
        }

        if ($isNew) {
            $candidateId = Candidate::submitByMember((int) $input['position_id'], $memberId, $input['bio'], $input['manifesto']);

            if ($candidateId === null) {
                // Dépôt clos entre-temps, candidature déjà active, membre
                // devenu inéligible ou désigné à la Commission : la garde
                // SQL a refusé, rien n'est enregistré.
                $this->render($context, $input, ['form' => t('candidacy.error.cannot_submit')], '');
                return;
            }

            foreach (CandidacyDossier::pending($electionId) as $field => $piece) {
                $piece['path'] = CandidacyDossier::promote($piece['path']);
                $this->attach($candidateId, CandidacyDossier::fields()[$field], $piece);
            }

            CandidacyDossier::forgetPending($electionId);
            ActivityLog::record((int) $_SESSION['user_id'], 'candidate.submitted', 'candidate', $candidateId);
            CandidacyNotifier::submitted($candidateId);

            $this->redirect('/elections/' . $electionId . '/candidacy?notice=submitted');
            return;
        }

        // Correction demandée par le bureau (lot 4) : l'envoi remet le
        // dossier en attente (Candidate::updateByMember()) et prévient les
        // admins qu'il est à réexaminer.
        $wasCorrection = $candidacy['status'] === 'correction';

        if (!Candidate::updateByMember((int) $candidacy['id'], $memberId, (int) $input['position_id'], $input['bio'], $input['manifesto'])) {
            $this->redirect('/elections/' . $electionId . '/candidacy?notice=locked');
            return;
        }

        if ($wasCorrection) {
            ActivityLog::record((int) $_SESSION['user_id'], 'candidate.corrected', 'candidate', (int) $candidacy['id']);
            CandidacyNotifier::corrected((int) $candidacy['id']);
            $this->redirect('/elections/' . $electionId . '/candidacy?notice=corrected');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.updated_by_member', 'candidate', (int) $candidacy['id']);

        $this->redirect('/elections/' . $electionId . '/candidacy?notice=updated');
    }

    public function cancel(array $params): void
    {
        $context = $this->context($params);

        if ($context === null) {
            return;
        }

        $electionId = (int) $context['election']['id'];
        $candidacy = $context['candidacy'];

        if ($candidacy === null || !Candidate::cancelByMember((int) $candidacy['id'], (int) $context['member']['id'])) {
            $this->redirect('/elections/' . $electionId . '/candidacy?notice=locked');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'candidate.cancelled_by_member', 'candidate', (int) $candidacy['id']);

        $this->redirect('/elections/' . $electionId . '/candidacy?notice=cancelled');
    }

    /**
     * Élection, membre, candidature active et état du dépôt — ou null si
     * la page doit répondre 404 (élection inexistante ou en brouillon).
     *
     * @return array{election: array, member: array, candidacy: ?array, positions: array, state: string}|null
     */
    private function context(array $params): ?array
    {
        $election = Election::find((int) ($params['id'] ?? 0));

        if ($election === null || $election['status'] === 'draft') {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');

            return null;
        }

        $member = Member::findByUserIdWithStatus((int) $_SESSION['user_id']);
        $electionId = (int) $election['id'];
        $candidacy = Candidate::activeForMember($electionId, (int) $member['id']);

        // État affiché, toujours dérivé des données, jamais stocké :
        // editable (sa candidature, modifiable) / submitted (déposée, plus
        // modifiable) / open (peut déposer) / closed / not_student /
        // commission.
        if ($candidacy !== null) {
            $state = Candidate::ownsEditable((int) $candidacy['id'], (int) $member['id']) ? 'editable' : 'submitted';
        } elseif (!Election::acceptsCandidacies($election)) {
            $state = 'closed';
        } elseif ($member['status_type'] !== 'student') {
            $state = 'not_student';
        } elseif (ElectionCommission::isCommissionMember($electionId, (int) $member['id'])) {
            $state = 'commission';
        } else {
            $state = 'open';
        }

        return [
            'election'  => $election,
            'member'    => $member,
            'candidacy' => $candidacy,
            'positions' => Election::positions($electionId),
            'state'     => $state,
        ];
    }

    private function validate(array $input, array $positions): array
    {
        $errors = [];

        if (!in_array((int) $input['position_id'], array_map(static fn (array $p): int => (int) $p['id'], $positions), true)) {
            $errors['position_id'] = t('validation.option_invalid');
        }

        $bioLength = mb_strlen($input['bio']);
        if ($bioLength < self::BIO_MIN || $bioLength > self::BIO_MAX) {
            $errors['bio'] = t('candidacy.error.bio_length', ['min' => self::BIO_MIN, 'max' => self::BIO_MAX]);
        }

        $manifestoLength = mb_strlen($input['manifesto']);
        if ($manifestoLength < self::MANIFESTO_MIN || $manifestoLength > self::MANIFESTO_MAX) {
            $errors['manifesto'] = t('candidacy.error.manifesto_length', ['min' => self::MANIFESTO_MIN, 'max' => self::MANIFESTO_MAX]);
        }

        if ($input['accept_rules'] !== '1') {
            $errors['accept_rules'] = t('candidacy.error.accept_rules');
        }

        return $errors;
    }

    /**
     * Range une pièce dans le dossier ; la première photo professionnelle
     * devient aussi la photo publique du candidat (page des candidats).
     */
    private function attach(int $candidateId, array $definition, array $piece): void
    {
        $old = CandidateDocument::put($candidateId, $definition['type'], $definition['slot'], $piece['path'], $piece['mime']);

        if ($old !== null && $old !== $piece['path']) {
            @unlink(Upload::storagePath($old));
        }

        if ($definition['type'] === 'pro_photo' && $definition['slot'] === 1) {
            try {
                $public = Upload::publishPrivateImage($piece['path'], 'candidates', (int) Config::get('security.upload_candidate_photo_max_dimension', 800));
                $oldPublic = Candidate::setPublicPhoto($candidateId, $public);

                if ($oldPublic !== null) {
                    @unlink(BASE_PATH . '/public/uploads/' . $oldPublic);
                }
            } catch (RuntimeException) {
                // La pièce privée est bien enregistrée ; seule la copie
                // publique manque, l'admin peut en charger une.
            }
        }
    }

    /**
     * @return array<string, array{path: string, mime: string, uploaded_at?: string}>
     */
    private function presentDocuments(int $candidateId): array
    {
        $present = [];

        foreach (CandidateDocument::forCandidate($candidateId) as $document) {
            $present[$document['type'] . '_' . $document['slot']] = [
                'path' => (string) $document['file_path'],
                'mime' => (string) $document['mime_type'],
                'uploaded_at' => (string) $document['uploaded_at'],
            ];
        }

        return $present;
    }

    private function inputFrom(?array $candidacy): array
    {
        return [
            'position_id'  => $candidacy !== null ? (string) $candidacy['election_position_id'] : '',
            'bio'          => (string) ($candidacy['bio'] ?? ''),
            'manifesto'    => (string) ($candidacy['manifesto'] ?? ''),
            'accept_rules' => $candidacy !== null ? '1' : '',
        ];
    }

    private function render(array $context, array $input, array $errors, string $notice): void
    {
        $election = $context['election'];
        $candidacy = $context['candidacy'];
        $rules = !empty($election['rules_document_id']) ? Document::find((int) $election['rules_document_id']) : null;

        $this->view('candidacy.show', [
            'title'     => t('candidacy.title', ['election' => $election['title']]),
            'election'  => $election,
            'candidacy' => $candidacy,
            'positions' => $context['positions'],
            'state'     => $context['state'],
            'present'   => $candidacy !== null ? $this->presentDocuments((int) $candidacy['id']) : CandidacyDossier::pending((int) $election['id']),
            'rules'     => $rules,
            'old'       => $input,
            'errors'    => $errors,
            'notice'    => $notice,
            'limits'    => ['bio_min' => self::BIO_MIN, 'manifesto_min' => self::MANIFESTO_MIN],
        ]);
    }
}
