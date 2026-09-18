<?php

/**
 * Occurrence times are stored as UTC instants and shown in the site timezone.
 *
 * The site is set to America/Denver for these tests: UTC-6 in summer,
 * UTC-7 in winter, with daylight saving starting 10 March 2030. Against a
 * UTC site every one of these bugs would be invisible.
 */
class TimeModelTest extends Mindshare_Events_TestCase {
    protected function setUp(): void {
        parent::setUp();
        update_option('timezone_string', 'America/Denver');
        update_option('gmt_offset', '');
    }

    private function stored(int $occurrence_id): array {
        return array(
            get_post_meta($occurrence_id, 'mindevents_start_utc', true),
            get_post_meta($occurrence_id, 'mindevents_end_utc', true),
        );
    }

    public function test_times_are_stored_as_utc(): void {
        $event_id = $this->createEvent();

        $summer = $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');
        $winter = $this->createOccurrence($event_id, '2030-01-15', '19:00', '21:00');

        $this->assertSame(array('2030-07-02 01:00:00', '2030-07-02 03:00:00'), $this->stored($summer));
        $this->assertSame(array('2030-01-16 02:00:00', '2030-01-16 04:00:00'), $this->stored($winter));
    }

    public function test_no_other_time_representations_are_stored(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-07-01');

        foreach (array('event_date', 'starttime', 'endtime', 'event_start_time_stamp', 'event_end_time_stamp', 'unique_event_key') as $key) {
            $this->assertFalse(metadata_exists('post', $occurrence_id, $key), "$key should not be stored");
        }
    }

    public function test_occurrence_times_are_read_back_in_the_site_timezone(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-07-01', '19:00', '21:00');

        $times = mindevents_get_occurrence_times($occurrence_id);

        $this->assertSame('2030-07-01T19:00:00-06:00', $times['start']->format('c'));
        $this->assertSame('2030-07-01T21:00:00-06:00', $times['end']->format('c'));
    }

    public function test_an_end_before_the_start_runs_into_the_next_day(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-07-01', '22:00', '01:00');

        $times = mindevents_get_occurrence_times($occurrence_id);

        $this->assertSame('2030-07-02 01:00', $times['end']->format('Y-m-d H:i'));
    }

    public function test_the_event_tracks_its_first_start_and_last_end(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-10', '10:00', '11:00');
        $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');
        $this->createOccurrence($event_id, '2030-07-20', '08:00', '09:00');

        $this->assertSame('2030-07-02 01:00:00', get_post_meta($event_id, 'mindevents_first_start_utc', true));
        $this->assertSame('2030-07-20 15:00:00', get_post_meta($event_id, 'mindevents_last_end_utc', true));
    }

    public function test_a_duplicate_occurrence_is_refused(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');

        $calendar = new mindEventCalendar($event_id);

        $this->assertFalse($calendar->add_sub_event('2030-07-01', array('starttime' => '19:00', 'endtime' => '21:00'), $event_id));
    }

    public function test_the_same_time_on_another_event_is_not_a_duplicate(): void {
        $this->createOccurrence($this->createEvent(), '2030-07-01', '19:00', '21:00');
        $other_event = $this->createEvent();

        $this->assertIsInt((new mindEventCalendar($other_event))->add_sub_event('2030-07-01', array('starttime' => '19:00', 'endtime' => '21:00'), $other_event));
    }

    public function test_moving_keeps_the_local_time_across_daylight_saving(): void {
        $this->actAs('administrator');
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-03-08', '19:00', '21:00');

        $this->ajax('mindevents_moveevent', array(
            'nonce'    => wp_create_nonce('mindevents_ajax'),
            'eventid'  => $occurrence_id,
            'new_date' => '2030-03-12',
        ));

        wp_cache_flush();
        $times = mindevents_get_occurrence_times($occurrence_id);
        $this->assertSame('2030-03-12 19:00', $times['start']->format('Y-m-d H:i'));
        $this->assertSame('2030-03-12 21:00', $times['end']->format('Y-m-d H:i'));
    }

    public function test_period_queries_include_occurrences_that_start_before_the_period(): void {
        $event_id = $this->createEvent();
        $spanning = $this->createOccurrence($event_id, '2030-06-30', '22:00', '02:00');
        $inside   = $this->createOccurrence($event_id, '2030-07-02', '10:00', '11:00');
        $before   = $this->createOccurrence($event_id, '2030-06-29', '10:00', '11:00');

        $found = get_posts(array(
            'post_type'  => 'sub_event',
            'fields'     => 'ids',
            'meta_query' => array(mindevents_overlapping_meta_query(
                new DateTimeImmutable('2030-07-01 00:00:00', wp_timezone()),
                new DateTimeImmutable('2030-07-07 23:59:59', wp_timezone())
            )),
        ));

        $this->assertContains($spanning, $found);
        $this->assertContains($inside, $found);
        $this->assertNotContains($before, $found);
    }

    public function test_month_view_shows_a_multi_day_occurrence_on_every_day_it_covers(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-01', '20:00', '10:00');
        $this->createOccurrence($event_id, '2030-07-10', '22:00', '00:00');

        $html = (new mindEventCalendar($event_id, '2030-07-01'))->get_calendar();

        $this->assertSame(1, $this->occurrencesOnDay($html, '2030-07-01'));
        $this->assertSame(1, $this->occurrencesOnDay($html, '2030-07-02'));
        $this->assertSame(0, $this->occurrencesOnDay($html, '2030-07-03'));

        // Ending exactly at midnight does not spill onto the next day.
        $this->assertSame(1, $this->occurrencesOnDay($html, '2030-07-10'));
        $this->assertSame(0, $this->occurrencesOnDay($html, '2030-07-11'));
    }

    private function occurrencesOnDay(string $html, string $date): int {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new DOMXPath($dom);

        return $xpath->query("//*[@data-date='$date']//*[@class='mindevents-admin-occurrence']")->length;
    }

    public function test_labels_are_shown_in_the_site_timezone(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-07-01', '19:00', '21:00');
        update_option('time_format', 'g:i a');

        $html = (new mindEventCalendar())->get_list_item_html($occurrence_id);

        $this->assertStringContainsString('7:00 pm - 9:00 pm', $html);
    }

    public function test_machine_outputs_carry_explicit_offsets(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');

        $rest   = mindevents_rest_event_payload($occurrence_id);
        $schema = json_decode((new mindEventCalendar($event_id))->generate_schema(), true);
        $ics    = mindevents_generate_single_event_ics($occurrence_id);

        $this->assertSame('2030-07-01T19:00:00-06:00', $rest['start']);
        $this->assertSame('2030-07-01T21:00:00-06:00', $rest['end']);
        $this->assertSame('2030-07-01T19:00:00-06:00', $schema['subEvent'][0]['startDate']);
        $this->assertSame('2030-07-01T19:00:00-06:00', $schema['startDate']);
        $this->assertStringContainsString("DTSTART:20300702T010000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20300702T030000Z\r\n", $ics);
    }

    public function test_calendar_links_use_utc(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-07-01', '19:00', '21:00');

        $html = html_entity_decode(mindevents_get_event_add_to_calendar_links($occurrence_id));

        $this->assertStringContainsString('dates=20300702T010000Z%2F20300702T030000Z', $html);
        $this->assertStringContainsString('st=20300702T0100Z', $html);
        $this->assertStringContainsString('et=20300702T0300Z', $html);
    }

    public function test_rest_date_filters_are_read_in_the_site_timezone(): void {
        $event_id = $this->createEvent();
        $evening  = $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');
        $next_day = $this->createOccurrence($event_id, '2030-07-02', '19:00', '21:00');

        // 7pm Denver on 1 July is already 2 July in UTC.
        $request = new WP_REST_Request('GET', '/simple-events/v1/events');
        $request->set_param('after', '2030-07-01 00:00:00');
        $request->set_param('before', '2030-07-01 23:59:59');
        $ids = wp_list_pluck(rest_do_request($request)->get_data()['events'], 'id');

        $this->assertContains($evening, $ids);
        $this->assertNotContains($next_day, $ids);
    }

    public function test_week_grid_places_occurrences_at_their_local_time(): void {
        $event_id  = $this->createEvent();
        $evening   = $this->createOccurrence($event_id, '2030-07-01', '19:00', '21:00');
        $overnight = $this->createOccurrence($event_id, '2030-07-02', '22:00', '02:00');

        $method = new ReflectionMethod('mindEventCalendar', 'build_week_events_by_day');
        $method->setAccessible(true);
        $days = $method->invoke(
            new mindEventCalendar(),
            array(get_post($evening), get_post($overnight)),
            new DateTimeImmutable('2030-07-01 00:00:00', wp_timezone()),
            new DateTimeImmutable('2030-07-07 23:59:59', wp_timezone())
        );

        $this->assertSame(19 * 60, $days['2030-07-01'][0]['start_minutes']);
        $this->assertSame(21 * 60, $days['2030-07-01'][0]['end_minutes']);

        // The overnight occurrence is split at midnight across both days.
        $this->assertSame(22 * 60, $days['2030-07-02'][0]['start_minutes']);
        $this->assertSame(0, $days['2030-07-03'][0]['start_minutes']);
        $this->assertSame(2 * 60, $days['2030-07-03'][0]['end_minutes']);
    }
}
