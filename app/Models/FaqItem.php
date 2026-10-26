<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Questions fréquentes écrites par le bureau (migration 029), affichées sur
 * « Nouveau à Final ? » après les questions intégrées au site.
 */
final class FaqItem extends Model
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return static::db()->query(
            'SELECT * FROM faq_items ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    /**
     * Questions publiées dans la langue demandée : la traduction anglaise
     * si elle existe, sinon le français.
     *
     * @return list<array{q: string, a: string}>
     */
    public static function publishedFor(string $locale): array
    {
        $rows = static::db()->query(
            'SELECT question, answer, question_en, answer_en FROM faq_items
             WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        return array_map(static function (array $row) use ($locale): array {
            $translated = $locale === 'en' && ($row['question_en'] ?? '') !== '' && ($row['answer_en'] ?? '') !== '';

            return [
                'q' => (string) ($translated ? $row['question_en'] : $row['question']),
                'a' => (string) ($translated ? $row['answer_en'] : $row['answer']),
            ];
        }, $rows);
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM faq_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{question: string, answer: string, question_en: ?string, answer_en: ?string, is_published: int} $data
     */
    public static function create(array $data): int
    {
        $stmt = static::db()->prepare(
            'INSERT INTO faq_items (question, answer, question_en, answer_en, is_published, sort_order)
             SELECT :question, :answer, :question_en, :answer_en, :is_published, COALESCE(MAX(sort_order), 0) + 1 FROM faq_items'
        );
        $stmt->execute([
            'question'     => $data['question'],
            'answer'       => $data['answer'],
            'question_en'  => $data['question_en'],
            'answer_en'    => $data['answer_en'],
            'is_published' => $data['is_published'],
        ]);

        return (int) static::db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = static::db()->prepare(
            'UPDATE faq_items SET question = :question, answer = :answer, question_en = :question_en,
                answer_en = :answer_en, is_published = :is_published
             WHERE id = :id'
        );
        $stmt->execute([
            'question'     => $data['question'],
            'answer'       => $data['answer'],
            'question_en'  => $data['question_en'],
            'answer_en'    => $data['answer_en'],
            'is_published' => $data['is_published'],
            'id'           => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = static::db()->prepare('DELETE FROM faq_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Monte ou descend une question d'un cran, après renumérotation 1..n
     * (même principe que GovernmentMember::move()).
     */
    public static function move(int $id, string $direction): bool
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $ids = array_map('intval', array_column(self::all(), 'id'));
        $index = array_search($id, $ids, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || !isset($ids[$target])) {
            return false;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $db = static::db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('UPDATE faq_items SET sort_order = :sort_order WHERE id = :id');

            foreach ($ids as $position => $faqId) {
                $stmt->execute(['sort_order' => $position + 1, 'id' => $faqId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }
}
