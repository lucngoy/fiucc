<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContactSettings;

/**
 * Coordonnées de la communauté (page /contact, pied de page, page
 * « Nouveau à Final ? ») et messages reçus par le formulaire de contact.
 * Derrière AdminMiddleware (routes/admin.php). Chaque modification est
 * journalisée : ces coordonnées sont publiques, savoir qui les a changées
 * et quand compte autant que pour une validation de membre.
 */
final class ContactController extends Controller
{
    private const MESSAGES_SHOWN = 50;

    public function index(): void
    {
        $this->showForm(ContactSettings::values(), [], ($_GET['notice'] ?? '') === 'saved');
    }

    public function update(): void
    {
        $input = ContactSettings::collect($_POST);
        $errors = ContactSettings::validate($input);

        if ($errors !== []) {
            $this->showForm($input, $errors, false);
            return;
        }

        $adminId = (int) $_SESSION['user_id'];

        Setting::saveMany(ContactSettings::toSettings($input), $adminId);
        ActivityLog::record($adminId, 'settings.contact_updated', 'site_settings', null);

        $this->redirect('/admin/contact?notice=saved');
    }

    private function showForm(array $values, array $errors, bool $saved): void
    {
        $user = User::find((int) $_SESSION['user_id']);

        $this->view('admin.contact.index', [
            'title'      => t('admin.contact.title'),
            'activeNav'  => 'contact',
            'adminEmail' => $user['email'] ?? '',
            'values'     => $values,
            'errors'     => $errors,
            'saved'      => $saved,
            'messages'   => ContactMessage::latest(self::MESSAGES_SHOWN),
            'recipient'  => ContactSettings::recipientEmail(),
        ], 'layouts.admin');
    }
}
