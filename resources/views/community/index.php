<?php
/**
 * Page Community (FIUCC-40) — section 11 du cahier des charges : mission,
 * vision, valeurs. Les 6 valeurs viennent du cahier des charges lui-même
 * (Unity, Leadership, Excellence, Culture, Community, Service), en dur ici
 * comme le reste du texte de la page — pas dans config/, ce n'est pas une
 * liste de référence appelée à changer, contrairement aux facultés du
 * recensement.
 *
 * "Notre histoire" : récit réel des fondateurs (Luc Banze, Josué Chabo),
 * pas un contenu inventé — fourni par l'utilisateur. La photo n'existe pas
 * encore : espace réservé tant que le fichier n'est pas déposé dans
 * public/assets/images/community/founding-story.jpg (bascule automatique
 * dès qu'il l'est, sans retoucher le code).
 *
 * "Our Journey" : seule section réellement connectée à la base — chronologie
 * tirée de la table events existante (App\Models\Event::timelineEntries()),
 * pas une liste inventée. $journey vient de CommunityController::index().
 */
$journey ??= [];

$storyPhotoPath = 'assets/images/community/founding-story.jpg';
$hasStoryPhoto = is_file(BASE_PATH . '/public/' . $storyPhotoPath);

$values = [
    ['title' => t('community.value.unity'), 'tag' => t('community.value.unity_tag'), 'body' => t('community.value.unity_body')],
    ['title' => t('community.value.leadership'), 'tag' => t('community.value.leadership_tag'), 'body' => t('community.value.leadership_body')],
    ['title' => t('community.value.excellence'), 'tag' => t('community.value.excellence_tag'), 'body' => t('community.value.excellence_body')],
    ['title' => t('community.value.culture'), 'tag' => t('community.value.culture_tag'), 'body' => t('community.value.culture_body')],
    ['title' => t('community.value.community'), 'tag' => t('community.value.community_tag'), 'body' => t('community.value.community_body')],
    ['title' => t('community.value.service'), 'tag' => t('community.value.service_tag'), 'body' => t('community.value.service_body')],
];
?>
<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 pt-8 pb-16 md:pt-10 md:pb-20">
        <nav aria-label="Breadcrumb" class="text-body-sm text-white/70 mb-8">
            <a href="/" class="hover:text-white transition-colors"><?= e(t('nav.home')) ?></a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-white"><?= e(t('community.title')) ?></span>
        </nav>
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
            <div class="max-w-2xl">
                <h1 class="font-headline-lg text-headline-lg text-white mb-4"><?= e(t('community.title')) ?></h1>
                <p class="text-body-lg text-white/80"><?= e(t('community.intro')) ?></p>
            </div>
            <span class="inline-flex items-center h-9 px-4 rounded-full bg-white/15 text-white text-label-md font-label-md shrink-0">
                <?= e(t('community.founded_badge')) ?>
            </span>
        </div>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
        <?php if ($hasStoryPhoto): ?>
            <img src="<?= e('/' . $storyPhotoPath) ?>" alt="<?= e(t('community.story.founders_caption')) ?>"
                 class="w-full h-72 md:h-96 object-cover rounded-card border border-border shadow-card">
        <?php else: ?>
            <div class="w-full h-72 md:h-96 rounded-card border border-dashed border-border bg-surface flex items-center justify-center">
                <span class="text-body-sm text-muted"><?= e(t('community.story.photo_pending')) ?></span>
            </div>
        <?php endif; ?>
        <div>
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-3"><?= e(t('community.story.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-4"><?= e(t('community.story.title')) ?></h2>
            <p class="text-body-md text-muted mb-4"><?= e(t('community.story.body')) ?></p>
            <p class="text-label-md font-label-md text-muted"><?= e(t('community.story.founders_caption')) ?></p>
        </div>
    </div>
</div>
</div>

<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="bg-white rounded-card border border-border border-l-4 border-l-primary shadow-card p-8">
            <span class="inline-flex h-11 w-11 rounded-full bg-primary/15 text-primary items-center justify-center text-headline-sm mb-4" aria-hidden="true">&#9733;</span>
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('community.mission.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('community.mission.title')) ?></h2>
            <p class="text-body-md text-muted"><?= e(t('community.mission.body')) ?></p>
        </div>
        <div class="bg-white rounded-card border border-border border-l-4 border-l-gold shadow-card p-8">
            <span class="inline-flex h-11 w-11 rounded-full bg-gold/20 text-ink items-center justify-center text-headline-sm mb-4" aria-hidden="true">&#8594;</span>
            <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('community.vision.eyebrow')) ?></p>
            <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('community.vision.title')) ?></h2>
            <p class="text-body-md text-muted"><?= e(t('community.vision.body')) ?></p>
        </div>
    </div>
</div>

<div class="w-full bg-white">
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('community.pillars.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('community.pillars.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('community.pillars.subtitle')) ?></p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($values as $index => $value): ?>
            <div class="bg-surface rounded-card border border-border p-6">
                <span class="inline-flex h-10 w-10 rounded-control bg-primary/15 text-primary items-center justify-center font-headline-sm text-headline-sm mb-4">
                    <?= e((string) ($index + 1)) ?>
                </span>
                <p class="font-label-caps text-label-caps uppercase text-muted tracking-wider mb-1"><?= e($value['tag']) ?></p>
                <h3 class="font-headline-sm text-headline-sm text-ink mb-2"><?= e($value['title']) ?></h3>
                <p class="text-body-sm text-muted"><?= e($value['body']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<?php if ($journey !== []): ?>
<div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <p class="font-label-caps text-label-caps uppercase text-primary tracking-wider mb-2"><?= e(t('community.journey.eyebrow')) ?></p>
        <h2 class="font-headline-md text-headline-md text-ink mb-3"><?= e(t('community.journey.title')) ?></h2>
        <p class="text-body-md text-muted"><?= e(t('community.journey.subtitle')) ?></p>
    </div>
    <div class="max-w-2xl mx-auto flex flex-col gap-8 relative before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-0.5 before:bg-border">
        <?php foreach ($journey as $entry): ?>
            <div class="flex gap-5 relative">
                <span class="h-4 w-4 rounded-full bg-primary border-4 border-primary-light shrink-0 mt-1 relative z-10" aria-hidden="true"></span>
                <div>
                    <p class="text-label-md font-label-md text-primary uppercase tracking-wider mb-1"><?= e(local_date($entry['start_date'])) ?></p>
                    <h3 class="font-headline-sm text-headline-sm text-ink mb-1"><?= e($entry['title']) ?></h3>
                    <?php if (!empty($entry['location'])): ?>
                        <p class="text-body-sm text-muted"><?= e($entry['location']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="w-full bg-primary">
    <div class="max-w-layout mx-auto px-4 md:px-6 py-16 md:py-20 text-center">
        <h2 class="font-headline-md text-headline-md text-white mb-3"><?= e(t('community.cta.title')) ?></h2>
        <p class="text-body-md text-white/80 mb-8 max-w-xl mx-auto"><?= e(t('community.cta.body')) ?></p>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <?= component('button', ['label' => t('community.cta.register'), 'variant' => 'accent', 'href' => '/register']) ?>
            <?= component('button', ['label' => t('community.cta.meet_government'), 'variant' => 'inverse', 'href' => '/community/government']) ?>
        </div>
    </div>
</div>
