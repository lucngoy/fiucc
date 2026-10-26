<?php
/**
 * Création / modification d'une question fréquente. Français obligatoire,
 * anglais facultatif (les deux champs ensemble, voir Admin\FaqController).
 */
$item ??= null;
$values ??= [];
$errors ??= [];
$answerMaxLength ??= 3000;

$value = static fn (string $name) => (string) ($values[$name] ?? '');
$error = static fn (string $name) => $errors[$name] ?? null;
$textareaClasses = static function (?string $errorMessage): string {
    return 'w-full rounded-control border px-3 py-2 text-body-md text-ink focus:outline-none focus:ring-2 '
        . ($errorMessage !== null
            ? 'border-danger focus:border-danger focus:ring-danger/30'
            : 'border-border focus:border-primary focus:ring-primary-light/50');
};
$action = $item === null ? '/admin/faq' : '/admin/faq/' . (int) $item['id'];
?>
<div class="flex flex-col gap-6 max-w-3xl">
    <a href="/admin/faq" class="inline-flex items-center gap-2 text-label-md font-label-md text-primary hover:underline self-start">
        <?= icon('arrow-left') ?>
        <?= e(t('admin.faq.back')) ?>
    </a>

    <?php if ($errors !== []): ?>
        <?= component('alert', ['type' => 'error', 'message' => t('form.error_banner')]) ?>
    <?php endif; ?>

    <form method="POST" action="<?= e($action) ?>" class="flex flex-col gap-6">
        <?= csrf_field() ?>

        <?php foreach (['' => 'fr', '_en' => 'en'] as $suffix => $lang): ?>
            <section class="bg-white rounded-card border border-border p-6 flex flex-col gap-5">
                <div>
                    <h2 class="font-label-lg text-label-lg text-ink"><?= e(t('admin.faq.section.' . $lang)) ?></h2>
                    <?php if ($lang === 'en'): ?>
                        <p class="text-body-sm text-muted"><?= e(t('admin.faq.section.en_help')) ?></p>
                    <?php endif; ?>
                </div>
                <?= component('form_field', [
                    'name' => 'question' . $suffix, 'label' => t('admin.faq.field.question'), 'required' => $lang === 'fr',
                    'value' => $value('question' . $suffix), 'error' => $error('question' . $suffix),
                ]) ?>
                <div class="flex flex-col gap-1.5">
                    <label for="field-answer<?= e($suffix) ?>" class="font-label-lg text-label-lg text-ink">
                        <?= e(t('admin.faq.field.answer')) ?><?= $lang === 'fr' ? ' <span class="text-danger">*</span>' : '' ?>
                    </label>
                    <textarea id="field-answer<?= e($suffix) ?>" name="answer<?= e($suffix) ?>" rows="5" maxlength="<?= e((string) $answerMaxLength) ?>"
                        class="<?= e($textareaClasses($error('answer' . $suffix))) ?>"><?= e($value('answer' . $suffix)) ?></textarea>
                    <?php if ($error('answer' . $suffix) !== null): ?>
                        <p class="text-body-sm text-danger" role="alert"><?= e($error('answer' . $suffix)) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="bg-white rounded-card border border-border p-6">
            <?= component('checkbox_field', [
                'name' => 'is_published', 'label' => t('admin.faq.field.published'),
                'description' => t('admin.faq.field.published_help'),
                'checked' => (int) ($values['is_published'] ?? 0) === 1,
            ]) ?>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-control bg-primary text-white text-label-lg font-label-lg hover:bg-primary-dark transition-colors"><?= e(t('admin.faq.save')) ?></button>
        </div>
    </form>
</div>
