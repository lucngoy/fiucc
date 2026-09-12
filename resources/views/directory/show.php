<?php
/**
 * Profil public d'un membre — student (FIUCC-84, section 15 du cahier des
 * charges — "PROFIL MEMBRE") : photo, nom, faculté, département, année,
 * Instagram, exactement la liste "Profil public éventuel" du cahier.
 * alumni (FIUCC-86, section "Espace Alumni") : quatre champs de plus à la
 * place de l'année d'étude — promotion (graduation_year), ville actuelle
 * (city/country), domaine professionnel (profession/company). Jamais
 * student_id/email/téléphone dans un cas comme dans l'autre (voir
 * CLAUDE.md — show_email/show_phone n'ont un usage légitime qu'en
 * mentorat, FIUCC-87/88). $member vient déjà filtré/rédigé de Member::
 * directoryProfile() : cette vue n'a aucun contrôle de confidentialité à
 * faire, seulement à afficher ce qu'elle reçoit.
 */
$studyLevels ??= [];
$isAlumni = ($member['status_type'] ?? null) === 'alumni';
$currentPlace = trim(implode(', ', array_filter([$member['city'] ?? null, $member['country'] ?? null], static fn ($v) => !empty($v))));

$renderInstagram = static function (?string $instagram): string {
    if (empty($instagram)) {
        return '';
    }

    $handle = ltrim($instagram, '@');

    return '<a href="' . e('https://www.instagram.com/' . $handle . '/') . '" target="_blank" rel="noopener noreferrer" '
        . 'class="inline-flex items-center gap-2 h-10 px-4 rounded-control bg-primary/[4%] text-primary hover:bg-primary/25 transition-colors">'
        . icon('instagram') . '<span class="text-label-lg font-label-lg">@' . e($handle) . '</span></a>';
};
?>
<div class="max-w-2xl mx-auto px-4 py-12 md:py-16">
    <nav aria-label="Breadcrumb" class="text-body-sm text-muted mb-6">
        <a href="/" class="hover:text-primary transition-colors"><?= e(t('nav.home')) ?></a>
        <span class="mx-2" aria-hidden="true">/</span>
        <a href="/directory" class="hover:text-primary transition-colors"><?= e(t('directory.title')) ?></a>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="text-ink"><?= e($member['full_name']) ?></span>
    </nav>

    <div class="bg-white rounded-card border border-border shadow-card p-6 md:p-8 flex flex-col items-center text-center gap-4">
        <?php if (!empty($member['photo'])): ?>
            <img src="<?= e('/uploads/' . $member['photo']) ?>" alt="" class="h-28 w-28 rounded-full object-cover border border-border">
        <?php else: ?>
            <span class="h-28 w-28 rounded-full bg-primary/[4%] text-primary flex items-center justify-center font-headline-lg text-headline-lg">
                <?= e(initials($member['full_name'])) ?>
            </span>
        <?php endif; ?>

        <div>
            <h1 class="font-headline-lg text-headline-lg text-ink"><?= e($member['full_name']) ?></h1>
            <?php if (!empty($member['faculty'])): ?>
                <p class="text-body-md text-muted mt-1"><?= e($member['faculty']) ?></p>
            <?php endif; ?>
            <?php if (!empty($member['department'])): ?>
                <p class="text-body-sm text-muted"><?= e($member['department']) ?></p>
            <?php endif; ?>
            <?php if (!$isAlumni && !empty($member['year']) && isset($studyLevels[(int) $member['year']])): ?>
                <p class="text-body-sm text-muted"><?= e($studyLevels[(int) $member['year']]) ?></p>
            <?php endif; ?>
            <?php if ($isAlumni): ?>
                <?php if (!empty($member['graduation_year'])): ?>
                    <p class="text-body-sm text-muted"><?= e(t('alumni.card.promotion', ['year' => (string) $member['graduation_year']])) ?></p>
                <?php endif; ?>
                <?php if (!empty($member['profession'])): ?>
                    <p class="text-body-md text-ink mt-2">
                        <?= e($member['profession']) ?><?php if (!empty($member['company'])): ?> · <?= e($member['company']) ?><?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if ($currentPlace !== ''): ?>
                    <p class="text-body-sm text-muted"><?= e($currentPlace) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($member['instagram'])): ?>
            <?= $renderInstagram($member['instagram']) ?>
        <?php endif; ?>

        <a href="/directory" class="text-body-sm text-primary underline mt-2"><?= e(t('directory.back_to_directory')) ?></a>
    </div>
</div>
