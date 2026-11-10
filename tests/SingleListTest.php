<?php
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * An event shown as a list on its own page lists only its own dates.
 */
class SingleListTest extends Mindshare_Events_TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_single_event_list_shows_only_its_own_dates(): void {
        $mine  = $this->createEvent(array('post_title' => 'Masquerade Ball'));
        $other = $this->createEvent(array('post_title' => 'Some Other Show'));
        $this->createOccurrence($mine, '2030-02-14', '19:00', '23:00');
        $this->createOccurrence($other, '2030-03-01', '19:00', '21:00');
        $this->createOccurrence($other, '2030-03-02', '19:00', '21:00');

        $calendar = new mindEventCalendar($mine);
        $html     = $calendar->get_front_list();

        $this->assertSame(1, preg_match_all('/class="mindevents-list-card[ "]/', $html));
        $this->assertStringContainsString('Masquerade Ball', $html);
        $this->assertStringNotContainsString('Some Other Show', $html);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_single_event_list_hides_past_dates_when_asked(): void {
        $event = $this->createEvent(array('post_title' => 'Masquerade Ball'));
        $this->createOccurrence($event, '2001-02-14', '19:00', '23:00');
        $this->createOccurrence($event, '2030-02-14', '19:00', '23:00');

        $calendar = new mindEventCalendar($event);
        $calendar->set_past_events_display(false);

        $this->assertSame(1, preg_match_all('/class="mindevents-list-card[ "]/', $calendar->get_front_list()));
    }
}
