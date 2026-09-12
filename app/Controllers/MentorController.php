<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Mentorship;

/**
 * Réseau de mentorat, côté étudiant (FIUCC-88, cahier — "un étudiant peut
 * demander un conseil ou une mise en relation"). Route déjà sous
 * ['validated'] (routes/web.php) : accessible aux seuls membres connectés
 * ET validés. Member::mentorList() renvoie déjà rédigé (is_mentor/
 * is_public/validated/alumni filtrent l'inclusion elle-même) — ce
 * contrôleur affiche tel quel, aucun contrôle de confidentialité ici.
 *
 * Réponse aux demandes (accepter/décliner) : ProfileController, pas ici
 * — co-localisée avec l'écran où un mentor voit ses demandes reçues
 * (/profile/mentor, FIUCC-87), un seul endroit pour "mes affaires de
 * mentorat" côté mentor.
 */
final class MentorController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filters = ['search' => (string) ($_GET['search'] ?? '')];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Member::mentorList($filters, $page, self::PER_PAGE);
        $totalPages = (int) max(1, (int) ceil($result['total'] / self::PER_PAGE));

        $this->view('mentors.index', [
            'title'       => t('mentors.title'),
            'members'     => $result['rows'],
            'total'       => $result['total'],
            'currentPage' => min($page, $totalPages),
            'totalPages'  => $totalPages,
            'filters'     => $filters,
            'sentRequests' => Mentorship::sentByStudent($this->currentMemberId()),
            'notice'       => (string) ($_GET['notice'] ?? ''),
        ]);
    }

    /**
     * $studentId dérivé de la session, jamais du formulaire — même
     * discipline IDOR-safe que le reste du projet. Mentorship::create()
     * revérifie tout côté base (mentor toujours disponible, pas de
     * demande en double) : un refus silencieux ici (id inconnu, session
     * sans membre) redirige simplement sans détail, la vraie garde vit
     * dans le modèle.
     */
    public function request(array $params): void
    {
        $studentId = $this->currentMemberId();

        if ($studentId === null) {
            $this->redirect('/mentors');
            return;
        }

        $mentorId = (int) ($params['id'] ?? 0);
        $topic = trim((string) ($_POST['topic'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($message === '') {
            $this->redirect('/mentors?notice=message_required');
            return;
        }

        $mentorshipId = Mentorship::create($mentorId, $studentId, $topic !== '' ? $topic : null, $message);

        if ($mentorshipId === null) {
            $this->redirect('/mentors?notice=request_failed');
            return;
        }

        ActivityLog::record((int) $_SESSION['user_id'], 'mentorship_requested', 'mentorship', $mentorshipId);

        $this->redirect('/mentors?notice=request_sent');
    }

    private function currentMemberId(): ?int
    {
        $member = Member::findFullByUserId((int) $_SESSION['user_id']);

        return $member !== null ? (int) $member['id'] : null;
    }
}
