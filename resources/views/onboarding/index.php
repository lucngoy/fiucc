<?php
/**
 * Page « Nouveau à Final ? » (FIUCC-89, cahier — "premières démarches,
 * contacts utiles, FAQ"). Publique, sans compte : c'est son public.
 *
 * Étapes dans l'ordre du cycle de vie du membre (CLAUDE.md, fil
 * conducteur) : rejoindre le groupe → se recenser → rencontrer les
 * anciens de sa faculté (annuaire, une fois validé) → premier événement.
 * L'étape annuaire pointe vers une route ['validated'] volontairement :
 * elle n'est pas encore atteignable par un nouvel arrivant, mais lui
 * montre où elle mène une fois les étapes précédentes faites — pas un
 * lien cassé, une étape légitimement plus tard dans la séquence.
 */
$whatsappUrl ??= null;

$steps = [
    [
        'number' => 1,
        'icon'   => 'people-group',
        'title'  => t('onboarding.step1.title'),
        'body'   => t('onboarding.step1.body'),
        'action' => $whatsappUrl !== null && $whatsappUrl !== ''
            ? ['label' => t('onboarding.step1.action_join'), 'href' => $whatsappUrl, 'external' => true]
            : ['label' => t('onboarding.step1.action_fallback'), 'href' => '/community/government', 'external' => false],
    ],
    [
        'number' => 2,
        'icon'   => 'clipboard-check',
        'title'  => t('onboarding.step2.title'),
        'body'   => t('onboarding.step2.body'),
        'action' => ['label' => t('onboarding.step2.action'), 'href' => '/register', 'external' => false],
    ],
    [
        'number' => 3,
        'icon'   => 'graduation-cap',
        'title'  => t('onboarding.step3.title'),
        'body'   => t('onboarding.step3.body'),
        'action' => ['label' => t('onboarding.step3.action'), 'href' => '/directory', 'external' => false],
    ],
    [
        'number' => 4,
        'icon'   => 'calendar-days',
        'title'  => t('onboarding.step4.title'),
        'body'   => t('onboarding.step4.body'),
        'action' => ['label' => t('onboarding.step4.action'), 'href' => '/events', 'external' => false],
    ],
];

$faqItems = [
    ['q' => t('onboarding.faq.q1'), 'a' => t('onboarding.faq.a1')],
    ['q' => t('onboarding.faq.q2'), 'a' => t('onboarding.faq.a2')],
    ['q' => t('onboarding.faq.q3'), 'a' => t('onboarding.faq.a3')],
    ['q' => t('onboarding.faq.q4'), 'a' => t('onboarding.faq.a4')],
    ['q' => t('onboarding.faq.q5'), 'a' => t('onboarding.faq.a5')],
];
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
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-14 md:pt-10 md:pb-20">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/90 mb-6 text-left max-w-layout mx-auto">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('onboarding.title')) ?></span>
        </nav>
        <h1 class="font-headline-lg text-headline-lg text-white mb-4 max-w-layout mx-auto"><?= e(t('onboarding.title')) ?></h1>
        <p class="text-body-lg text-white/90 max-w-layout mx-auto"><?= e(t('onboarding.subtitle')) ?></p>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-14 md:py-20">
    <div class="max-w-layout mx-auto mb-12">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('onboarding.steps.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink"><?= e(t('onboarding.steps.title')) ?></h2>
    </div>

    <div class="flex flex-col gap-5 max-w-layout mx-auto">
        <?php foreach ($steps as $step): ?>
            <?php $isExternalAction = $step['action']['external']; ?>
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-5 rounded-card bg-surface border border-border">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <span class="h-11 w-11 rounded-full bg-primary text-white flex items-center justify-center font-label-lg text-label-lg font-bold shrink-0">
                        <?= e((string) $step['number']) ?>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-headline-sm text-headline-sm text-ink flex items-center gap-2">
                            <?= icon($step['icon'], 'text-primary text-base shrink-0') ?>
                            <?= e($step['title']) ?>
                        </h3>
                        <p class="text-body-sm text-muted mt-0.5"><?= e($step['body']) ?></p>
                    </div>
                </div>
                <a href="<?= e($step['action']['href']) ?>"
                   <?= $isExternalAction ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                   class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors shrink-0 sm:ml-4">
                    <?= e($step['action']['label']) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<div class="w-full bg-surface">
<div class="max-w-layout mx-auto px-4 md:px-6 py-14 md:py-20">
    <div class="max-w-layout mx-auto mb-10">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('onboarding.contacts.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink mb-2"><?= e(t('onboarding.contacts.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('onboarding.contacts.body')) ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="/contact" class="inline-flex items-center gap-2 h-11 px-6 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
            <?= icon('envelope') ?>
            <?= e(t('onboarding.contacts.contact_link')) ?>
        </a>
        <a href="/community/government" class="inline-flex items-center gap-2 h-11 px-6 rounded-control bg-white border border-border text-ink text-label-lg font-label-lg hover:border-primary hover:text-primary transition-colors">
            <?= icon('sitemap') ?>
            <?= e(t('onboarding.contacts.government_link')) ?>
        </a>
        <?php if (!empty($whatsappUrl)): ?>
            <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 h-11 px-6 rounded-control bg-white border border-border text-ink text-label-lg font-label-lg hover:border-primary hover:text-primary transition-colors">
                <?= icon('people-group') ?>
                <?= e(t('onboarding.step1.action_join')) ?>
            </a>
        <?php endif; ?>
    </div>
</div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-14 md:py-20">
    <div class="max-w-layout mx-auto mb-10">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('onboarding.faq.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink"><?= e(t('onboarding.faq.title')) ?></h2>
    </div>
    <div class="max-w-layout mx-auto flex flex-col gap-3">
        <?php foreach ($faqItems as $item): ?>
            <details class="group rounded-card border border-border bg-surface p-4">
                <summary class="flex items-center justify-between gap-3 cursor-pointer font-label-lg text-label-lg text-ink list-none">
                    <?= e($item['q']) ?>
                    <?= icon('chevron-down', 'text-muted shrink-0 transition-transform group-open:rotate-180') ?>
                </summary>
                <p class="text-body-sm text-muted mt-3"><?= e($item['a']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</div>
</div>
