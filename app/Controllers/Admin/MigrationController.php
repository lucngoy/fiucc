<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Migrator;
use App\Services\SchemaKeys;

/**
 * Mises à jour de la base — back office. Sur un hébergement sans accès
 * SSH (InfinityFree), `php database/migrate.php` est impossible : ce bouton
 * applique les mêmes migrations, par le même code (App\Services\Migrator).
 * Admin seulement (AdminMiddleware), POST + CSRF, rien de saisi n'entre
 * dans le SQL : seuls les fichiers versionnés de database/migrations/ sont
 * exécutés. Chaque passage est journalisé, succès comme échec.
 */
final class MigrationController extends Controller
{
    public function index(): void
    {
        $this->view('admin.migrations.index', [
            'title'      => t('admin.migrations.title'),
            'activeNav'  => 'migrations',
            'adminEmail' => $this->currentAdminEmail(),
            'migrations' => Migrator::status(),
            'keysPlan'   => SchemaKeys::missing(),
            'notice'     => (string) ($_GET['notice'] ?? ''),
            'result'     => $_SESSION['migration_result'] ?? null,
            'repair'     => $_SESSION['keys_repair_result'] ?? null,
        ], 'layouts.admin');

        unset($_SESSION['migration_result'], $_SESSION['keys_repair_result']);
    }

    /**
     * Réparation des clés (App\Services\SchemaKeys) : n'ajoute que ce qui
     * manque à cette base précise — jamais un script figé écrit pour une
     * autre (2026-10-12 : "#1068 Multiple primary key defined").
     */
    public function repairKeys(): void
    {
        @set_time_limit(300);

        $result = SchemaKeys::repair();
        $_SESSION['keys_repair_result'] = $result;

        ActivityLog::record(
            (int) $_SESSION['user_id'],
            $result['failed'] === [] ? 'database.keys_repaired' : 'database.keys_repair_incomplete',
            'migrations',
            null,
            count($result['done']) . ' ajout(s), ' . count($result['failed']) . ' échec(s)'
        );

        $this->redirect('/admin/migrations?notice=' . ($result['failed'] === [] ? 'keys_repaired' : 'keys_repair_incomplete') . '#structure');
    }

    public function apply(): void
    {
        // Plusieurs migrations d'affilée peuvent dépasser la limite par
        // défaut d'un hébergeur mutualisé ; sans effet si elle est imposée.
        @set_time_limit(300);

        $result = Migrator::applyPending();
        $adminId = (int) $_SESSION['user_id'];

        if ($result['applied'] !== []) {
            ActivityLog::record($adminId, 'database.migrations_applied', 'migrations', null, implode(', ', $result['applied']));
        }

        if ($result['failed'] !== null) {
            ActivityLog::record($adminId, 'database.migration_failed', 'migrations', null, $result['failed'] . ' — ' . $result['error']);
        }

        // Le détail (fichiers, message d'erreur) passe par la session, pas
        // par l'URL : un message d'erreur SQL n'a rien à faire dans un lien.
        $_SESSION['migration_result'] = $result;

        $notice = match (true) {
            $result['failed'] !== null => 'failed',
            $result['applied'] !== []  => 'applied',
            default                    => 'nothing',
        };

        $this->redirect('/admin/migrations?notice=' . $notice);
    }

    private function currentAdminEmail(): string
    {
        $user = User::find((int) $_SESSION['user_id']);

        return $user['email'] ?? '';
    }
}
