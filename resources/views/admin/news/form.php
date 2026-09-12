<?php
/**
 * Formulaire de création/édition d'article (FIUCC-56) — un seul gabarit
 * pour create() et edit()/update(), comme admin/events/form.php. Pas de
 * champ "slug" (déduit du titre) ni "auteur" (l'admin connecté à la
 * création, jamais modifiable — users n'a pas de nom d'affichage à
 * proposer dans une liste).
 */
$article ??= null;
$old ??= [];
$errors ??= [];
$categories ??= [];

$field = static fn (string $name) => $old[$name] ?? '';
$error = static fn (string $name) => $errors[$name] ?? null;
$isEdit = $article !== null;
$action = $isEdit ? '/admin/news/' . (int) $article['id'] : '/admin/news';

$inputClasses = static function (?string $errorMessage): string {
    return 'h-[42px] w-full rounded-control border px-3 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
?>
<div>
    <a href="/admin/news" class="inline-flex items-center gap-2 text-body-sm text-muted hover:text-ink transition-colors mb-6">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.news.title')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <div class="mb-6">
            <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" enctype="multipart/form-data" class="bg-white rounded-card border border-border p-6 md:p-8 flex flex-col gap-6">
        <?= csrf_field() ?>

        <?= component('form_field', [
            'name' => 'title', 'label' => t('admin.news.field.title'), 'required' => true,
            'value' => $field('title'), 'error' => $error('title'),
        ]) ?>

        <div class="flex flex-col gap-1.5">
            <label for="field-excerpt" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.news.field.excerpt')) ?></label>
            <textarea id="field-excerpt" name="excerpt" rows="2" maxlength="500"
                class="<?= e($inputClasses($error('excerpt'))) ?> !h-auto py-2"><?= e($field('excerpt')) ?></textarea>
            <?php if ($error('excerpt') !== null): ?>
                <p class="text-body-sm text-danger" role="alert"><?= e($error('excerpt')) ?></p>
            <?php else: ?>
                <p class="text-body-sm text-muted"><?= e(t('admin.news.field.excerpt_help')) ?></p>
            <?php endif; ?>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="field-content" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.news.field.content')) ?></label>
            <textarea id="field-content" name="content" rows="10"
                class="<?= e($inputClasses($error('content'))) ?> !h-auto py-2"><?= e($field('content')) ?></textarea>
            <p class="text-body-sm text-muted"><?= e(t('admin.news.field.content_help')) ?></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="flex flex-col gap-1.5">
                <label for="field-category" class="font-label-lg text-label-lg text-ink"><?= e(t('admin.news.field.category')) ?></label>
                <select id="field-category" name="category" class="<?= e($inputClasses($error('category'))) ?> bg-white">
                    <?php foreach ($categories as $categoryOption): ?>
                        <option value="<?= e($categoryOption) ?>" <?= $field('category') === $categoryOption ? 'selected' : '' ?>>
                            <?= e(t('news.category.' . $categoryOption)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($error('category') !== null): ?>
                    <p class="text-body-sm text-danger" role="alert"><?= e($error('category')) ?></p>
                <?php endif; ?>
            </div>

            <?= component('form_field', [
                'name' => 'published_at', 'label' => t('admin.news.field.published_at'), 'type' => 'datetime-local',
                'value' => $field('published_at'), 'error' => $error('published_at'), 'help' => t('admin.news.field.published_at_help'),
            ]) ?>
        </div>

        <div class="flex flex-col gap-5 pt-2 border-t border-border">
            <div class="flex items-center gap-5">
                <?php if ($isEdit && !empty($article['cover_image'])): ?>
                    <img src="<?= e('/uploads/' . $article['cover_image']) ?>" alt="" class="h-16 w-24 rounded-control object-cover border border-border">
                <?php else: ?>
                    <span class="h-16 w-24 rounded-control bg-primary/10 text-primary flex items-center justify-center">
                        <?= icon('newspaper') ?>
                    </span>
                <?php endif; ?>

                <div class="flex flex-col gap-1.5">
                    <label for="field-cover-image" class="font-label-lg text-label-lg text-ink">
                        <?= e(t($isEdit ? 'admin.news.field.cover_image_new' : 'admin.news.field.cover_image')) ?>
                    </label>
                    <input type="file" id="field-cover-image" name="cover_image" accept="image/jpeg,image/png,image/webp"
                        class="text-body-sm text-ink file:mr-3 file:h-9 file:px-4 file:rounded-control file:border-0 file:bg-primary file:text-white file:font-label-md file:text-label-md hover:file:bg-primary-dark">
                    <?php if ($error('cover_image') !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('cover_image')) ?></p>
                    <?php else: ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.news.field.cover_image_help')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/admin/news" class="h-10 px-4 flex items-center rounded-control text-ink text-label-lg font-label-lg hover:bg-surface transition-colors"><?= e(t('admin.news.action.cancel')) ?></a>
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors">
                <?= e(t($isEdit ? 'admin.news.submit.update' : 'admin.news.submit.create')) ?>
            </button>
        </div>
    </form>
</div>
