<?php
declare(strict_types=1);

namespace Tests\Content;

use App\Models\FaqItem;
use Tests\TestCase;

/**
 * Questions fréquentes du bureau (migration 029) : ordre, masquage, et
 * repli sur le français quand la traduction anglaise manque.
 */
final class FaqItemTest extends TestCase
{
    private function item(string $question, ?string $questionEn = null, int $published = 1): int
    {
        return FaqItem::create([
            'question'     => $question,
            'answer'       => 'Réponse à « ' . $question . ' »',
            'question_en'  => $questionEn,
            'answer_en'    => $questionEn !== null ? 'Answer to "' . $questionEn . '"' : null,
            'is_published' => $published,
        ]);
    }

    public function test_new_questions_go_last_and_hidden_ones_are_not_published(): void
    {
        $this->item('Bourses');
        $this->item('Brouillon', null, 0);
        $this->item('Logement');

        self::assertSame(['Bourses', 'Brouillon', 'Logement'], array_column(FaqItem::all(), 'question'));
        self::assertSame(['Bourses', 'Logement'], array_column(FaqItem::publishedFor('fr'), 'q'));
    }

    public function test_english_uses_the_translation_and_falls_back_to_french_without_one(): void
    {
        $this->item('Bourses', 'Scholarships');
        $this->item('Logement');

        self::assertSame(['Scholarships', 'Logement'], array_column(FaqItem::publishedFor('en'), 'q'));
        self::assertSame(['Bourses', 'Logement'], array_column(FaqItem::publishedFor('fr'), 'q'));
    }

    public function test_moving_swaps_neighbours_and_stops_at_the_ends(): void
    {
        $a = $this->item('A');
        $b = $this->item('B');
        $c = $this->item('C');

        self::assertTrue(FaqItem::move($c, 'up'));
        self::assertSame(['A', 'C', 'B'], array_column(FaqItem::all(), 'question'));
        self::assertFalse(FaqItem::move($a, 'up'));
        self::assertFalse(FaqItem::move($b, 'down'));
        self::assertFalse(FaqItem::move($b, 'sideways'));
    }
}
