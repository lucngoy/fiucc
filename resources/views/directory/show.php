<?php
/**
 * Profil public d'un membre (FIUCC-84, section 15 du cahier des charges —
 * "PROFIL MEMBRE"). $member vient déjà filtré/rédigé de Member::
 * directoryProfile() : photo, nom, faculté, département, année, Instagram
 * — exactement la liste "Profil public éventuel" du cahier, jamais
 * student_id/email/téléphone (voir CLAUDE.md, point ouvert sur
 * show_email/show_phone). Cette vue n'a aucun contrôle de confidentialité
 * à faire, seulement à afficher ce qu'elle reçoit.
 */
$studyLevels ??= [];

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
            <?php if (!empty($member['year']) && isset($studyLevels[(int) $member['year']])): ?>
                <p class="text-body-sm text-muted"><?= e($studyLevels[(int) $member['year']]) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($member['instagram'])): ?>
            <?= $renderInstagram($member['instagram']) ?>
        <?php endif; ?>

        <a href="/directory" class="text-body-sm text-primary underline mt-2"><?= e(t('directory.back_to_directory')) ?></a>
    </div>
</div>
