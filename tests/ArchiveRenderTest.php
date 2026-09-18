<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * Renders each archive view end to end, as the archive template does.
 *
 * Separate processes, because the front-end filter state is cached for the
 * whole request once it is first read.
 */
class ArchiveRenderTest extends Mindshare_Events_TestCase {
    public static function views(): array {
        return array(
            'month' => array('month', 'mindevents-calendar-event-card'),
            'week'  => array('week', 'mindevents-weekly-event'),
            'list'  => array('list', 'mindevents-list-card'),
        );
    }

    #[DataProvider('views')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_archive_view_renders_its_occurrences(string $view, string $marker): void {
        update_option('timezone_string', 'America/Denver');
        update_option('gmt_offset', '');

        $event_id = $this->createEvent(array('post_title' => 'Glaze Night'));
        $this->createOccurrence($event_id, '2030-07-02', '19:00', '21:00');

        $GLOBALS['wp_query']     = new WP_Query(array('post_type' => 'mind_events'));
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $_GET = array('event_view' => $view, 'calendar_date' => '2030-07-02');

        $this->assertTrue(is_post_type_archive('mind_events'));

        $filters  = mindevents_get_frontend_filters();
        $calendar = new mindEventCalendar('', mindevents_get_archive_initial_calendar_date($filters));
        $calendar->set_past_events_display(true);

        $html = ($view === 'list') ? $calendar->get_front_list() : $calendar->get_front_calendar();

        $this->assertStringContainsString($marker, $html);
        $this->assertStringContainsString('Glaze Night', $html);
    }
}
