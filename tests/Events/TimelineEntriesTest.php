<?php
declare(strict_types=1);

namespace Tests\Events;

use App\Models\Event;
use Tests\TestCase;

/**
 * Frise « La vie de la communauté » (/community) : les événements les plus
 * récents, rendus du plus ancien au plus récent, sans brouillon ni annulé.
 */
final class TimelineEntriesTest extends TestCase
{
    private function event(string $title, string $startDate, string $status = 'published'): void
    {
        Event::create([
            'title'       => $title,
            'description' => 'Description de ' . $title,
            'category'    => null,
            'location'    => 'Kyrenia',
            'start_date'  => $startDate,
            'end_date'    => null,
            'cover_image' => null,
            'capacity'    => null,
            'status'      => $status,
            'created_by'  => null,
        ]);
    }

    public function test_keeps_the_most_recent_events_in_chronological_order(): void
    {
        $this->event('Mars', '2026-03-01 18:00:00', 'archived');
        $this->event('Janvier', '2026-01-01 18:00:00');
        $this->event('Avril', '2026-04-01 18:00:00');
        $this->event('Février', '2026-02-01 18:00:00');

        self::assertSame(['Février', 'Mars', 'Avril'], array_column(Event::timelineEntries(3), 'title'));
    }

    public function test_drafts_and_cancelled_events_never_appear(): void
    {
        $this->event('Publié', '2026-01-01 18:00:00');
        $this->event('Brouillon', '2026-02-01 18:00:00', 'draft');
        $this->event('Annulé', '2026-03-01 18:00:00', 'cancelled');

        self::assertSame(['Publié'], array_column(Event::timelineEntries(), 'title'));
    }
}
