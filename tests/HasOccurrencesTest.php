<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * An event's page shows its schedule only when the event has dates to
 * show, so it never says there are no upcoming events.
 */
class HasOccurrencesTest extends Mindshare_Events_TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_event_with_no_dates_has_none(): void {
        $event = $this->createEvent(array('post_title' => 'Masquerade Ball'));
        $other = $this->createEvent(array('post_title' => 'Some Other Show'));
        $this->createOccurrence($other, '2030-03-01', '19:00', '21:00');

        $this->assertFalse((new mindEventCalendar($event))->has_occurrences());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_an_event_with_upcoming_dates_has_them(): void {
        $event = $this->createEvent(array('post_title' => 'Masquerade Ball'));
        $this->createOccurrence($event, '2030-02-14', '19:00', '23:00');

        $calendar = new mindEventCalendar($event);
        $calendar->set_past_events_display(false);

        $this->assertTrue($calendar->has_occurrences());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_past_dates_count_only_when_past_dates_are_shown(): void {
        $event = $this->createEvent(array('post_title' => 'Masquerade Ball'));
        $this->createOccurrence($event, '2001-02-14', '19:00', '23:00');

        $calendar = new mindEventCalendar($event);
        $this->assertTrue($calendar->has_occurrences());

        $calendar->set_past_events_display(false);
        $this->assertFalse($calendar->has_occurrences());
    }
}
