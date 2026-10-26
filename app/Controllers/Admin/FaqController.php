<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\FaqItem;
use App\Models\User;

/**
 * Questions fréquentes de « Nouveau à Final ? » (migration 029), derrière
 * AdminMiddleware. Les réponses sont du texte brut (retours à la ligne
 * conservés), jamais du HTML : rien à assainir, tout passe par e().
 */
final class FaqController extends Controller
{
    private const QUESTION_MAX_LENGTH = 255;
    private const ANSWER_MAX_LENGTH = 3000;

    public function index(): void
    {
        $this->view('admin.faq.index', [
            'title'      => t('admin.faq.title'),
            'activeNav'  => 'faq',
            'adminEmail' => $this->adminEmail(),
            'items'      => FaqItem::all(),
            'notice'     => (string) ($_GET['notice'] ?? ''),
        ], 'layouts.admin');
    }

    public function create(): void
    {
        $this->showForm(null, ['is_published' => 1], []);
    }

    public function store(): void
    {
        $input = $this->collect();
        $errors = $this->validate($input);

        if ($errors !== []) {
            $this->showForm(null, $input, $errors);
            return;
        }

        $id = FaqItem::create($input);
        ActivityLog::record((int) $_SESSION['user_id'], 'faq.created', 'faq_item', $id);

        $this->redirect('/admin/faq?notice=created');
    }

    public function edit(array $params): void
    {
        $item = FaqItem::find((int) ($params['id'] ?? 0));

        if ($item === null) {
            $this->redirect('/admin/faq');
            return;
        }

        $this->showForm($item, $item, []);
    }

    public function update(array $params): void
    {
        $item = FaqItem::find((int) ($params['id'] ?? 0));

        if ($item === null) {
            $this->redirect('/admin/faq');
            return;
        }

        $input = $this->collect();
        $errors = $this->validate($input);

        if ($errors !== []) {
            $this->showForm($item, $input, $errors);
            return;
        }

        FaqItem::update((int) $item['id'], $input);
        ActivityLog::record((int) $_SESSION['user_id'], 'faq.updated', 'faq_item', (int) $item['id']);

        $this->redirect('/admin/faq?notice=updated');
    }

    public function delete(array $params): void
    {
        $item = FaqItem::find((int) ($params['id'] ?? 0));

        if ($item !== null) {
            FaqItem::delete((int) $item['id']);
            ActivityLog::record((int) $_SESSION['user_id'], 'faq.deleted', 'faq_item', (int) $item['id']);
        }

        $this->redirect('/admin/faq?notice=deleted');
    }

    public function move(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        FaqItem::move($id, (string) ($_POST['direction'] ?? ''));

        $this->redirect('/admin/faq#faq-' . $id);
    }

    /**
     * @return array{question: string, answer: string, question_en: ?string, answer_en: ?string, is_published: int}
     */
    private function collect(): array
    {
        $text = static fn (string $key): string => trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? '')));
        $questionEn = $text('question_en');
        $answerEn = $text('answer_en');

        return [
            'question'     => $text('question'),
            'answer'       => $text('answer'),
            'question_en'  => $questionEn !== '' ? $questionEn : null,
            'answer_en'    => $answerEn !== '' ? $answerEn : null,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        foreach (['question', 'question_en'] as $key) {
            if (mb_strlen((string) $input[$key]) > self::QUESTION_MAX_LENGTH) {
                $errors[$key] = t('validation.value_too_long');
            }
        }

        foreach (['answer', 'answer_en'] as $key) {
            if (mb_strlen((string) $input[$key]) > self::ANSWER_MAX_LENGTH) {
                $errors[$key] = t('admin.faq.error.answer_too_long', ['max' => self::ANSWER_MAX_LENGTH]);
            }
        }

        if ($input['question'] === '') {
            $errors['question'] = t('admin.faq.error.question_required');
        }

        if ($input['answer'] === '') {
            $errors['answer'] = t('admin.faq.error.answer_required');
        }

        // Traduction : les deux ou aucune — une question anglaise sans
        // réponse ne s'afficherait jamais (FaqItem::publishedFor()).
        if (($input['question_en'] === null) !== ($input['answer_en'] === null)) {
            $errors[$input['question_en'] === null ? 'question_en' : 'answer_en'] = t('admin.faq.error.translation_incomplete');
        }

        return $errors;
    }

    private function showForm(?array $item, array $values, array $errors): void
    {
        $this->view('admin.faq.form', [
            'title'      => $item === null ? t('admin.faq.create_title') : t('admin.faq.edit_title'),
            'activeNav'  => 'faq',
            'adminEmail' => $this->adminEmail(),
            'item'       => $item,
            'values'     => $values,
            'errors'     => $errors,
            'answerMaxLength' => self::ANSWER_MAX_LENGTH,
        ], 'layouts.admin');
    }

    private function adminEmail(): string
    {
        return (string) (User::find((int) $_SESSION['user_id'])['email'] ?? '');
    }
}
