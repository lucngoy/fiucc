<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\HelpRequest;
use App\Models\Member;
use App\Services\ContactSettings;
use App\Services\HelpNotifier;

/**
 * Page « Aide et urgences » (/help) : numéros d'urgence de Chypre du Nord,
 * ambassade de la RDC, communauté mère, et formulaire de demande d'aide au
 * gouvernement (migration 028, inspiré du service BOUSSOLE de la
 * communauté mère).
 *
 * La page est publique (les numéros d'urgence doivent l'être). Le
 * formulaire demande d'être connecté avec une fiche membre, validée ou
 * non : un nouvel arrivant dont le dossier attend la validation est
 * justement celui qui a le plus besoin d'aide, et une demande n'expose
 * aucun autre membre (CLAUDE.md, règle 12).
 */
final class HelpController extends Controller
{
    private const SUBJECT_MAX_LENGTH = 150;
    private const MESSAGE_MAX_LENGTH = 3000;

    public function index(): void
    {
        $this->show([], [], ($_GET['sent'] ?? '') === '1');
    }

    public function store(): void
    {
        $member = $this->currentMember();

        if ($member === null) {
            $this->redirect('/help');
            return;
        }

        $input = [
            'category' => (string) ($_POST['category'] ?? ''),
            'subject'  => trim((string) ($_POST['subject'] ?? '')),
            'message'  => trim(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? ''))),
        ];

        $errors = $this->validate($input);

        if ($errors === []) {
            $max = max(1, (int) Config::get('security.help_max_requests_per_day', 3));

            if (HelpRequest::countRecentForMember((int) $member['id'], 24) >= $max) {
                $errors['form'] = t('help.error.rate_limited', ['max' => $max]);
            }
        }

        if ($errors !== []) {
            $this->show($input, $errors, false);
            return;
        }

        $id = HelpRequest::create($input + ['member_id' => (int) $member['id']]);
        ActivityLog::record((int) $_SESSION['user_id'], 'help_request.created', 'help_request', $id);

        $request = HelpRequest::find($id);

        if ($request !== null) {
            HelpNotifier::notifyBureau($request);
        }

        $this->redirect('/help?sent=1#my-requests');
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if (!in_array($input['category'], HelpRequest::CATEGORIES, true)) {
            $errors['category'] = t('help.error.category_required');
        }

        if ($input['subject'] === '') {
            $errors['subject'] = t('help.error.subject_required');
        } elseif (mb_strlen($input['subject']) > self::SUBJECT_MAX_LENGTH) {
            $errors['subject'] = t('validation.value_too_long');
        }

        if (mb_strlen($input['message']) < 20) {
            $errors['message'] = t('help.error.message_too_short');
        } elseif (mb_strlen($input['message']) > self::MESSAGE_MAX_LENGTH) {
            $errors['message'] = t('help.error.message_too_long', ['max' => self::MESSAGE_MAX_LENGTH]);
        }

        return $errors;
    }

    /**
     * Fiche membre de la session, ou null (visiteur, admin sans fiche,
     * email pas encore vérifié).
     */
    private function currentMember(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $member = Member::findByUserId((int) $_SESSION['user_id']);

        return $member !== null && $member['email_verified_at'] !== null ? $member : null;
    }

    private function show(array $old, array $errors, bool $sent): void
    {
        $member = $this->currentMember();

        $this->view('help.index', [
            'title'            => t('help.title'),
            'description'      => t('help.meta_description'),
            'emergency'        => (array) Config::get('emergency.numbers', []),
            'emergencySource'  => (string) Config::get('emergency.source_url', ''),
            'contact'          => ContactSettings::values(),
            'member'           => $member,
            'isLoggedIn'       => isset($_SESSION['user_id']),
            'requests'         => $member !== null ? HelpRequest::forMember((int) $member['id']) : [],
            'old'              => $old,
            'errors'           => $errors,
            'sent'             => $sent,
            'messageMaxLength' => self::MESSAGE_MAX_LENGTH,
        ]);
    }
}
