<?php

/**
 * An event's own calendar opens where there is something to see.
 */
class EventCalendarStartTest extends Mindshare_Events_TestCase {
    private function openingMonth(int $event_id): string {
        return (new mindEventCalendar($event_id))->getDate()->format('Y-m');
    }

    public function test_opens_on_the_next_upcoming_occurrence(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2020-01-15');
        $upcoming = new DateTimeImmutable('+75 days', wp_timezone());
        $this->createOccurrence($event_id, $upcoming->format('Y-m-d'));

        $this->assertSame($upcoming->format('Y-m'), $this->openingMonth($event_id));
    }

    public function test_opens_on_the_most_recent_occurrence_when_all_are_past(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2020-01-15');
        $this->createOccurrence($event_id, '2021-06-10');

        $this->assertSame('2021-06', $this->openingMonth($event_id));
    }

    public function test_an_explicit_date_still_wins(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, (new DateTimeImmutable('+75 days', wp_timezone()))->format('Y-m-d'));

        $this->assertSame('2019-03', (new mindEventCalendar($event_id, '2019-03-01'))->getDate()->format('Y-m'));
    }
}
