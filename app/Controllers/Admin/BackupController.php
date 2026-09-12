<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\View;
use App\Models\ActivityLog;
use App\Services\Backup;
use Throwable;

/**
 * Sauvegarde base + uploads + documents à la demande (FIUCC-80, section 41
 * du cahier des charges). Une archive ZIP par sauvegarde, listée ici par
 * lecture directe de storage/backups/ — pas de table dédiée, un fichier
 * porte déjà tout ce dont l'écran a besoin (nom horodaté, taille, date de
 * modification), même principe que pour ne pas modéliser ce qu'un fichier
 * suffit à représenter (voir CLAUDE.md, photos d'un événement).
 *
 * create()/delete() sont journalisés (ActivityLog) comme l'export CSV des
 * membres (Admin\MemberController::export()) : une archive contenant toute
 * la base et les documents/photos des membres est un événement à pouvoir
 * reconstituer après coup — qui l'a déclenchée, quand.
 */
final class BackupController extends Controller
{
    private const STREAM_CHUNK_BYTES = 1048576; // 1 Mo — même principe que DocumentController::streamFile().

    public function index(): void
    {
        $this->view('admin.backups.index', [
            'title'     => t('admin.backups.title'),
            'activeNav' => 'backups',
            'backups'   => Backup::list(),
            'notice'    => (string) ($_GET['notice'] ?? ''),
        ]);
    }

    public function create(): void
    {
        try {
            $result = Backup::create();
        } catch (Throwable) {
            $this->redirect('/admin/backups?notice=failed');
            return;
        }

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            'backup.created',
            'backup',
            null,
            $result['filename']
        );

        $this->redirect('/admin/backups?notice=created');
    }

    public function download(array $params): void
    {
        $filename = (string) ($params['filename'] ?? '');
        $path = Backup::pathFor($filename);

        if ($path === null) {
            http_response_code(404);
            echo View::render('errors.404', ['title' => t('error.404_title')], 'layouts.error');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'backup.downloaded', 'backup', null, $filename);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache');

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            http_response_code(500);
            return;
        }

        while (!feof($handle)) {
            echo fread($handle, self::STREAM_CHUNK_BYTES);
            flush();
        }

        fclose($handle);
        exit;
    }

    public function delete(array $params): void
    {
        $filename = (string) ($params['filename'] ?? '');

        if (Backup::delete($filename)) {
            ActivityLog::record((int) $_SESSION['user_id'], 'backup.deleted', 'backup', null, $filename);
            $this->redirect('/admin/backups?notice=deleted');
            return;
        }

        $this->redirect('/admin/backups?notice=not_found');
    }
}
