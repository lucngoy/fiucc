<?php
/**
 * Page des candidats (FIUCC-63, section 18 du cahier des charges : PHOTO,
 * NAME, "Running for: POSITION", biographie courte, manifeste, [View
 * Profile]). Référence visuelle : maquette fiucc_portal_election_hub_
 * candidates/code.html (section B), largement simplifiée — pas de matrice
 * de comparaison, pas de téléchargement PDF, pas de citation de slogan, pas
 * d'onglets JS (une simple navigation par ancre remplace les onglets), rien
 * de tout ça n'a de donnée réelle derrière (ni colonne, ni logique).
 *
 * Confidentialité (décision explicite conversation FIUCC-63) : un candidat
 * accepte d'être visible EN TANT QUE candidat, pas de voir tout son profil
 * membre exposé dans ce nouveau contexte.
 * - Photo : jamais de repli sur la photo de profil du membre — seule une
 *   photo choisie pour CETTE candidature (candidates.photo) peut
 *   apparaître ; App\Models\Candidate::publicListForElection() ne
 *   sélectionne même pas members.photo, impossible de l'afficher par
 *   erreur.
 * - Faculté et année : toujours affichées (cahier §17), le reste
 *   (département, Instagram) déjà mis à NULL par le modèle si
 *   members.is_public = 0 — cette vue n'a pas de vérification à refaire,
 *   elle affiche ce qu'on lui donne.
 * - "View Profile" (ci-dessous, <details>) ne révèle que la biographie et
 *   le manifeste déjà présents dans la carte, jamais un contact ni un
 *   numéro étudiant — rien de plus que la carte elle-même.
 */
$election ??= [];
$groups ??= [];
$studyLevels ??= [];
$listPending ??= false;
$listAtLabel = !empty($election['candidate_list_at'])
    ? t('elections.candidates.list_pending', [
        'date' => local_date((string) $election['candidate_list_at']),
        'time' => date('H:i', strtotime((string) $election['candidate_list_at'])),
    ])
    : '';

$electionId = (int) $election['id'];
$totalCandidates = array_sum(array_map(static fn (array $group): int => count($group['candidates']), $groups));

// members.faculty stocke déjà un libellé résolu (écrit tel quel à
// l'inscription, RegistrationController::store()), pas une clé de
// config/registration.php à relire — affiché tel quel, comme
// resources/views/profile/index.php le fait pour le même champ.
$candidateMeta = static function (array $candidate) use ($studyLevels): string {
    $parts = [];

    if (!empty($candidate['faculty'])) {
        $parts[] = $candidate['faculty'];
    }

    if (!empty($candidate['department'])) {
        $parts[] = $candidate['department'];
    }

    if ($candidate['year'] !== null && isset($studyLevels[(int) $candidate['year']])) {
        $parts[] = $studyLevels[(int) $candidate['year']];
    }

    return implode(' · ', $parts);
};
?>
<div class="relative w-full overflow-hidden bg-primary">
    <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-40" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none">
        <style>
            /* simple SVG animations: floating lines and pulsing dots */
            @keyframes floatY { 0% { transform: translateY(0); } 50% { transform: translateY(-6px); } 100% { transform: translateY(0); } }
            @keyframes pulse { 0% { r: 2; opacity: 0.9 } 50% { r: 4; opacity: 0.5 } 100% { r: 2; opacity: 0.9 } }
            .float { transform-origin: 400px 450px; animation: floatY 6s ease-in-out infinite; }
            .pulse circle { animation: pulse 2.5s ease-in-out infinite; }
        </style>
        <g class="float" stroke="#3B82F6" stroke-width="1">
            <path d="M120 60 L340 20 L520 140 L460 320 L620 380 L560 560 L700 620 L640 800" />
            <path d="M340 20 L420 220 L520 140" />
            <path d="M420 220 L460 320 L280 360 L340 20" />
            <path d="M280 360 L120 60" />
            <path d="M460 320 L620 380 L520 500 L280 360" />
            <path d="M520 500 L560 560 L400 620 L280 360" />
            <path d="M560 560 L700 620 L620 760 L400 620" />
            <path d="M620 760 L640 800 L480 860 L400 620" />
        </g>
        <g class="pulse" fill="#60A5FA">
            <circle cx="120" cy="60" r="3" /><circle cx="340" cy="20" r="2.5" />
            <circle cx="520" cy="140" r="3" /><circle cx="460" cy="320" r="2.5" />
            <circle cx="620" cy="380" r="3" /><circle cx="560" cy="560" r="2.5" />
            <circle cx="700" cy="620" r="3" /><circle cx="640" cy="800" r="2.5" />
            <circle cx="420" cy="220" r="2" /><circle cx="280" cy="360" r="2.5" />
            <circle cx="520" cy="500" r="2" /><circle cx="400" cy="620" r="2.5" />
            <circle cx="480" cy="860" r="2" /><circle cx="620" cy="760" r="2" />
        </g>
    </svg>
    <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px); background-size: 16px 16px;"></div>
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-8 md:pt-10">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/elections" class="hover:text-white transition-colors"><?= e(t('elections.detail.breadcrumb')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="<?= e('/elections/' . $electionId) ?>" class="hover:text-white transition-colors"><?= e($election['title']) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('elections.candidates.breadcrumb')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-3"><?= e($election['title']) ?></h1>
        <a href="<?= e('/elections/' . $electionId) ?>" class="inline-flex items-center gap-2 text-body-sm text-white/90 hover:text-white transition-colors">
            <?= icon('arrow-left', 'text-xs') ?>
            <?= e(t('elections.candidates.back_to_election')) ?>
        </a>
    </div>
</div>

<div class="w-full bg-surface">
<div class="max-w-layout mx-auto px-4 md:px-6 py-10 md:py-12 flex flex-col gap-10">

    <?php if ($listPending): ?>
        <?= component('alert', ['type' => 'info', 'message' => $listAtLabel]) ?>
    <?php elseif ($totalCandidates === 0): ?>
        <?= component('alert', ['type' => 'info', 'message' => t('elections.candidates.empty')]) ?>
    <?php else: ?>

        <?php if (count($groups) > 1): ?>
            <nav aria-label="<?= e(t('elections.candidates.positions_nav')) ?>" class="flex flex-wrap gap-2">
                <?php foreach ($groups as $positionId => $group): ?>
                    <a href="#position-<?= e((string) $positionId) ?>"
                       class="inline-flex items-center gap-2 px-4 h-9 rounded-full bg-white text-ink text-label-md font-label-md hover:bg-primary/10 hover:text-primary transition-colors">
                        <?= e($group['position']['name']) ?>
                        <span class="px-2 py-0.5 rounded-full bg-surface text-muted text-label-caps font-label-caps"><?= e((string) count($group['candidates'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php foreach ($groups as $positionId => $group): ?>
            <section id="position-<?= e((string) $positionId) ?>" class="flex flex-col gap-5 scroll-mt-24">
                <div>
                    <h2 class="font-headline-md text-headline-md text-ink"><?= e($group['position']['name']) ?></h2>
                    <?php if (!empty($group['position']['description'])): ?>
                        <p class="text-body-sm text-muted mt-1"><?= e($group['position']['description']) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($group['candidates'] === []): ?>
                    <?= component('alert', ['type' => 'info', 'message' => t('elections.candidates.position_empty')]) ?>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($group['candidates'] as $candidate): ?>
                            <?php $meta = $candidateMeta($candidate); ?>
                            <article class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="relative shrink-0">
                                        <?php if (!empty($candidate['photo'])): ?>
                                            <img src="<?= e('/uploads/' . $candidate['photo']) ?>" alt="" class="h-16 w-16 rounded-full object-cover border border-border">
                                        <?php else: ?>
                                            <span class="h-16 w-16 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                                <?= icon('user', 'text-xl') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($candidate['ballot_number'] !== null): ?>
                                            <span class="absolute -bottom-1 -right-1 px-2 py-0.5 bg-gold text-ink rounded-full text-label-caps font-label-caps shadow-sm">
                                                <?= e(t('elections.candidates.ballot_number', ['number' => (string) $candidate['ballot_number']])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <h3 class="font-headline-sm text-headline-sm text-ink truncate"><?= e($candidate['full_name']) ?></h3>
                                        <p class="text-label-md font-label-md text-primary mt-1"><?= e(t('elections.candidates.running_for', ['position' => $group['position']['name']])) ?></p>
                                        <?php if ($meta !== ''): ?>
                                            <p class="text-body-sm text-muted mt-1"><?= e($meta) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($candidate['instagram'])): ?>
                                            <a href="<?= e('https://instagram.com/' . ltrim($candidate['instagram'], '@')) ?>" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1.5 text-body-sm text-muted hover:text-primary transition-colors mt-1 w-fit">
                                                <?= icon('instagram') ?>
                                                @<?= e(ltrim($candidate['instagram'], '@')) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($candidate['bio'])): ?>
                                    <p class="text-body-sm text-ink whitespace-pre-line"><?= e($candidate['bio']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($candidate['manifesto'])): ?>
                                    <details class="group">
                                        <summary class="cursor-pointer list-none flex items-center justify-between gap-2 text-label-md font-label-md text-primary hover:text-primary-dark transition-colors">
                                            <span class="group-open:hidden"><?= e(t('elections.candidates.view_profile')) ?></span>
                                            <span class="hidden group-open:inline"><?= e(t('elections.candidates.hide_profile')) ?></span>
                                            <?= icon('chevron-down', 'text-xs transition-transform group-open:rotate-180') ?>
                                        </summary>
                                        <p class="text-body-sm text-ink whitespace-pre-line mt-3"><?= e($candidate['manifesto']) ?></p>
                                    </details>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
</div>
