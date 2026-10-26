<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\HelpRequest;
use App\Models\User;
use App\Services\HelpNotifier;

/**
 * Demandes d'aide reçues (migration 028), derrière AdminMiddleware. Le
 * bureau change le statut et peut écrire une réponse : une réponse
 * nouvelle ou modifiée est envoyée au membre par email et s'affiche sur
 * sa page /help. Changer seulement le statut n'envoie rien.
 */
final class HelpController extends Controller
{
    private const RESPONSE_MAX_LENGTH = 3000;

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $status = in_array($status, HelpRequest::STATUSES, true) ? $status : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $list = HelpRequest::adminList($status, $page);
        $totalPages = max(1, (int) ceil($list['total'] / HelpRequest::ADMIN_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
            $list = HelpRequest::adminList($status, $page);
        }

        $this->view('admin.help.index', [
            'title'       => t('admin.help.title'),
            'activeNav'   => 'help',
            'adminEmail'  => $this->adminEmail(),
            'requests'    => $list['rows'],
            'total'       => $list['total'],
            'status'      => $status,
            'counts'      => array_combine(HelpRequest::STATUSES, array_map([HelpRequest::class, 'countByStatus'], HelpRequest::STATUSES)),
            'currentPage' => $page,
            'totalPages'  => $totalPages,
        ], 'layouts.admin');
    }

    public function show(array $params): void
    {
        $request = HelpRequest::find((int) ($params['id'] ?? 0));

        if ($request === null) {
            $this->redirect('/admin/help');
            return;
        }

        $this->showForm($request, null, (string) ($_GET['notice'] ?? ''));
    }

    public function update(array $params): void
    {
        $request = HelpRequest::find((int) ($params['id'] ?? 0));

        if ($request === null) {
            $this->redirect('/admin/help');
            return;
        }

        $status = (string) ($_POST['status'] ?? '');
        $response = trim(str_replace("\r\n", "\n", (string) ($_POST['response'] ?? '')));

        if (!in_array($status, HelpRequest::STATUSES, true)) {
            $this->showForm($request, t('admin.help.error.status'), '');
            return;
        }

        if (mb_strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $this->showForm(['status' => $status, 'response' => $response] + $request, t('admin.help.error.response_too_long', ['max' => self::RESPONSE_MAX_LENGTH]), '');
            return;
        }

        $adminId = (int) $_SESSION['user_id'];
        $responseChanged = $response !== '' && $response !== (string) ($request['response'] ?? '');

        HelpRequest::updateHandling((int) $request['id'], $status, $response !== '' ? $response : null, $adminId);
        ActivityLog::record($adminId, 'help_request.updated', 'help_request', (int) $request['id'], $status);

        if ($responseChanged) {
            HelpNotifier::notifyMember(['status' => $status] + $request, $response);
        }

        $this->redirect('/admin/help/' . (int) $request['id'] . '?notice=' . ($responseChanged ? 'replied' : 'saved'));
    }

    private function showForm(array $request, ?string $error, string $notice): void
    {
        $this->view('admin.help.show', [
            'title'            => t('admin.help.show_title', ['reference' => HelpRequest::reference($request)]),
            'activeNav'        => 'help',
            'adminEmail'       => $this->adminEmail(),
            'request'          => $request,
            'error'            => $error,
            'notice'           => $notice,
            'responseMaxLength' => self::RESPONSE_MAX_LENGTH,
        ], 'layouts.admin');
    }

    private function adminEmail(): string
    {
        return (string) (User::find((int) $_SESSION['user_id'])['email'] ?? '');
    }
}
