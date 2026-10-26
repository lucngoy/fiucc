<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Lang;
use App\Models\FaqItem;
use App\Services\ContactSettings;

/**
 * Page « Nouveau à Final ? » (FIUCC-89, section "Onboarding du nouvel
 * arrivant" du cahier des charges) — premières démarches, contacts
 * utiles, FAQ. Route publique, sans middleware (routes/web.php) : c'est
 * délibérément son public, un étudiant qui vient d'arriver n'a pas
 * encore de compte.
 */
final class OnboardingController extends Controller
{
    public function index(): void
    {
        $this->view('onboarding.index', [
            'title'       => t('onboarding.title'),
            'description' => t('onboarding.meta_description'),
            // Coordonnées modifiables depuis /admin/contact (repli sur .env).
            'whatsappUrl' => ContactSettings::values()['whatsapp_url'],
            'customFaq'   => $this->customFaq(),
        ]);
    }

    /**
     * Table de la migration 029 absente (juste après un déploiement) : la
     * page reste affichée avec les questions du site seules.
     *
     * @return list<array{q: string, a: string}>
     */
    private function customFaq(): array
    {
        try {
            return FaqItem::publishedFor(Lang::locale());
        } catch (\PDOException) {
            return [];
        }
    }
}
