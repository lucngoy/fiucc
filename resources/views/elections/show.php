<?php
/**
 * Page élection — compte à rebours et éligibilité (FIUCC-62, sections
 * 16-19 du cahier des charges). Référence visuelle : maquette
 * fiucc_portal_election_hub_candidates/code.html (section A), largement
 * simplifiée — la maquette invente un "Voter Key (VRN)" montré à
 * l'électeur, une timeline électorale à 4 phases, une grille des postes et
 * un accordéon de règles (quorum, contentieux électoral...) : rien de tout
 * ça n'a de donnée réelle derrière (pas de colonne, pas de logique). Gardé
 * uniquement : bannière titre/dates/statut, compte à rebours, panneau
 * d'éligibilité, lien vers la page des candidats (FIUCC-63).
 *
 * Une élection encore 'draft' n'atteint jamais cette vue —
 * App\Controllers\ElectionController::findElectionOr404() la traite comme
 * introuvable, donc $election['status'] ici vaut toujours candidacies/open/
 * closed/published.
 *
 * Panneau d'éligibilité : 8 états distincts (validés en conversation
 * FIUCC-62) — connecté ou non, profil membre absent, recensement pending/
 * rejected/suspended, alumni/honoraire, déjà voté, éligible. "Déjà voté" se
 * lit dans voting_tokens.used_at (App\Models\VotingToken::hasVoted()),
 * jamais dans votes — c'est justement la séparation qui permet de répondre
 * à cette question sans casser l'anonymat (CLAUDE.md, règles 1 et 13).
 * Aucun bouton "voter" ici même à l'état "eligible" : la page
 * d'authentification au vote n'existe pas avant FIUCC-64/65, et ce projet
 * ne simule jamais un lien vers une fonctionnalité qui n'est pas construite.
 */
$election ??= [];
$window ??= ['state' => 'closed', 'target' => null];
$eligibility ??= 'not_signed_in';
$receipt ??= null;

$statusBadgeLabel = in_array($election['status'], ['candidacies', 'open', 'closed', 'published'], true)
    ? t('elections.status.' . $election['status'])
    : '';

$countdownStaticStates = ['opening_imminent', 'closing_imminent', 'closed', 'published'];
$countdownIcon = [
    'opening_imminent' => 'fa-hourglass-start',
    'closing_imminent' => 'fa-hourglass-end',
    'closed'            => 'fa-lock',
    'published'         => 'fa-square-poll-vertical',
][$window['state']] ?? 'fa-clock';

$eligibilityStyle = [
    'not_signed_in'         => ['bg' => 'bg-primary/10',  'text' => 'text-primary',  'icon' => 'fa-right-to-bracket'],
    'no_member_profile'     => ['bg' => 'bg-muted/[1%]',    'text' => 'text-muted',    'icon' => 'fa-circle-info'],
    'pending'                => ['bg' => 'bg-gold/20',     'text' => 'text-ink',      'icon' => 'fa-hourglass-half'],
    'rejected'               => ['bg' => 'bg-danger/10',   'text' => 'text-danger',   'icon' => 'fa-circle-xmark'],
    'suspended'              => ['bg' => 'bg-danger/10',   'text' => 'text-danger',   'icon' => 'fa-ban'],
    'not_student_alumni'    => ['bg' => 'bg-primary/10',  'text' => 'text-primary',  'icon' => 'fa-graduation-cap'],
    'not_student_honorary'  => ['bg' => 'bg-primary/10',  'text' => 'text-primary',  'icon' => 'fa-graduation-cap'],
    'already_voted'          => ['bg' => 'bg-success/[4%]',  'text' => 'text-success',  'icon' => 'fa-circle-check'],
    'eligible'               => ['bg' => 'bg-success/[4%]',  'text' => 'text-success',  'icon' => 'fa-circle-check'],
][$eligibility] ?? ['bg' => 'bg-muted/[1%]', 'text' => 'text-muted', 'icon' => 'fa-circle-info'];
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
    <div class="relative z-10 max-w-layout mx-auto px-4 md:px-6 pt-8 pb-10 md:pt-10 md:pb-14 w-full">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-5">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <a href="/elections" class="hover:text-white transition-colors"><?= e(t('elections.detail.breadcrumb')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e($election['title']) ?></span>
        </nav>
        <?php if ($statusBadgeLabel !== ''): ?>
            <span class="inline-flex items-center h-7 px-3 rounded-full bg-ink/25 text-white text-label-md font-label-md mb-4">
                <?= e($statusBadgeLabel) ?>
            </span>
        <?php endif; ?>
        <h1 class="font-headline-lg text-headline-lg text-white max-w-3xl mb-4"><?= e($election['title']) ?></h1>
        <?php if (!empty($election['description'])): ?>
            <p class="font-body-lg text-body-lg text-white/90 max-w-3xl leading-relaxed mb-6 whitespace-pre-line"><?= e($election['description']) ?></p>
        <?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur rounded-control p-3">
                <?= icon('calendar-day', 'text-gold text-lg') ?>
                <div class="flex flex-col">
                    <span class="text-label-caps font-label-caps uppercase text-white"><?= e(t('elections.detail.starts')) ?></span>
                    <span class="text-label-md font-label-md text-white font-semibold"><?= e(local_date($election['start_at'])) ?></span>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 backdrop-blur rounded-control p-3">
                <?= icon('calendar-check', 'text-gold text-lg') ?>
                <div class="flex flex-col">
                    <span class="text-label-caps font-label-caps uppercase text-white"><?= e(t('elections.detail.ends')) ?></span>
                    <span class="text-label-md font-label-md text-white font-semibold"><?= e(local_date($election['end_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="w-full">
<div class="max-w-layout mx-auto px-4 md:px-6 py-12 md:py-16 flex flex-col gap-8">

    <?php if (in_array($window['state'], $countdownStaticStates, true)): ?>
        <div class="bg-white rounded-card border border-border p-6 md:p-8 flex items-center gap-4">
            <span class="h-12 w-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                <?= icon($countdownIcon, 'text-xl') ?>
            </span>
            <div>
                <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('elections.countdown.' . $window['state'] . '.title')) ?></h2>
                <p class="text-body-sm text-muted mt-1"><?= e(t('elections.countdown.' . $window['state'] . '.message')) ?></p>
            </div>
        </div>
    <?php else: ?>
        <?php
        $isOpenCountdown = $window['state'] === 'open_countdown';
        $targetTimestamp = strtotime((string) $window['target']);
        ?>
        <div class="bg-surface rounded-card border border-border p-6 md:p-8 flex flex-col gap-4" data-countdown-target="<?= e((string) $targetTimestamp) ?>">
            <p class="text-label-lg font-label-lg text-ink text-center">
                <?= e(t('elections.countdown.' . ($isOpenCountdown ? 'closes_in_label' : 'opens_in_label'))) ?>
            </p>
            <div class="grid grid-cols-4 gap-3">
                <div class="bg-white rounded-control p-4 border border-border flex flex-col items-center text-center">
                    <span class="font-headline-lg text-headline-lg text-primary" data-countdown-days>00</span>
                    <span class="text-label-caps font-label-caps uppercase text-muted mt-1"><?= e(t('elections.countdown.days')) ?></span>
                </div>
                <div class="bg-white rounded-control p-4 border border-border flex flex-col items-center text-center">
                    <span class="font-headline-lg text-headline-lg text-primary" data-countdown-hours>00</span>
                    <span class="text-label-caps font-label-caps uppercase text-muted mt-1"><?= e(t('elections.countdown.hours')) ?></span>
                </div>
                <div class="bg-white rounded-control p-4 border border-border flex flex-col items-center text-center">
                    <span class="font-headline-lg text-headline-lg text-primary" data-countdown-minutes>00</span>
                    <span class="text-label-caps font-label-caps uppercase text-muted mt-1"><?= e(t('elections.countdown.minutes')) ?></span>
                </div>
                <div class="bg-white rounded-control p-4 border border-border flex flex-col items-center text-center">
                    <span class="font-headline-lg text-headline-lg text-primary" data-countdown-seconds>00</span>
                    <span class="text-label-caps font-label-caps uppercase text-muted mt-1"><?= e(t('elections.countdown.seconds')) ?></span>
                </div>
            </div>
            <p class="text-body-sm text-muted text-center">
                <?= e(t('elections.countdown.' . ($isOpenCountdown ? 'closes_on' : 'opens_on'), ['date' => local_date($window['target'])])) ?>
            </p>
        </div>
        <script src="/assets/js/election-countdown.js" defer></script>
    <?php endif; ?>

    <div class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-4">
        <h2 class="font-headline-sm text-headline-sm text-ink"><?= e(t('elections.eligibility.title')) ?></h2>
        <div class="flex items-start gap-4">
            <span class="h-11 w-11 rounded-full <?= e($eligibilityStyle['bg']) ?> <?= e($eligibilityStyle['text']) ?> flex items-center justify-center shrink-0">
                <?= icon($eligibilityStyle['icon']) ?>
            </span>
            <div class="flex flex-col gap-3">
                <div>
                    <p class="font-label-lg text-label-lg text-ink"><?= e(t('elections.eligibility.' . $eligibility . '.title')) ?></p>
                    <p class="text-body-sm text-muted mt-1"><?= e(t('elections.eligibility.' . $eligibility . '.message')) ?></p>
                </div>
                <?php if ($eligibility === 'not_signed_in'): ?>
                    <div class="flex flex-wrap gap-3">
                        <a href="/login" class="inline-flex items-center justify-center h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors"><?= e(t('elections.eligibility.not_signed_in.login_cta')) ?></a>
                        <a href="/register" class="inline-flex items-center justify-center h-10 px-5 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors"><?= e(t('elections.eligibility.not_signed_in.register_cta')) ?></a>
                    </div>
                <?php elseif (in_array($eligibility, ['pending', 'rejected', 'suspended'], true)): ?>
                    <a href="/profile" class="inline-flex items-center justify-center self-start h-10 px-5 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors"><?= e(t('elections.eligibility.' . $eligibility . '.profile_cta')) ?></a>
                <?php elseif ($eligibility === 'already_voted' && $receipt !== null): ?>
                    <div class="bg-surface rounded-control border border-border p-3 w-fit">
                        <p class="text-label-caps font-label-caps uppercase text-muted mb-1"><?= e(t('elections.ballot.receipt_label')) ?></p>
                        <p class="font-mono text-label-lg font-label-lg text-ink tracking-wider"><?= e($receipt) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="<?= e('/elections/' . $election['id'] . '/candidates') ?>"
           class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-control bg-primary text-white text-label-lg font-label-lg font-semibold hover:bg-primary-dark transition-colors">
            <?= e(t('elections.detail.view_candidates')) ?>
            <?= icon('arrow-right', 'text-sm') ?>
        </a>
        <?php if (in_array($election['status'], ['closed', 'published'], true)): ?>
            <a href="<?= e('/elections/' . $election['id'] . '/results') ?>"
               class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-control border border-border text-ink text-label-lg font-label-lg font-semibold hover:bg-surface transition-colors">
                <?= e(t('elections.detail.view_results')) ?>
                <?= icon('chart-simple', 'text-sm') ?>
            </a>
        <?php endif; ?>
    </div>

</div>
</div>
