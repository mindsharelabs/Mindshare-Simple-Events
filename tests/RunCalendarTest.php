<?php

/**
 * The run calendar: every date of an event in one grid, with no month
 * pages, and the month buttons that say how many dates each month has.
 */
class RunCalendarTest extends Mindshare_Events_TestCase {
    const WORDING_FILTERS = array('mindevents_occurrence_count_label', 'mindevents_run_gap_label');

    /** A theme's wording, set aside so the plugin's own is tested. */
    private $saved_filters = array();

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter;
        foreach (self::WORDING_FILTERS as $hook) {
            $this->saved_filters[$hook] = $wp_filter[$hook] ?? null;
            unset($wp_filter[$hook]);
        }
    }

    protected function tearDown(): void {
        global $wp_filter;
        foreach ($this->saved_filters as $hook => $saved) {
            if ($saved === null) {
                unset($wp_filter[$hook]);
            } else {
                $wp_filter[$hook] = $saved;
            }
        }
        parent::tearDown();
    }

    private function render(int $event_id, string $today = '2030-07-01'): DOMXPath {
        $html = (new mindEventCalendar($event_id, null, $today))->get_run_calendar();

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new DOMXPath($dom);
    }

    private function day(DOMXPath $xpath, string $date): DOMElement {
        $node = $xpath->query("//*[@data-date='{$date}']")->item(0);
        $this->assertNotNull($node, "No cell for {$date}.");

        return $node;
    }

    private function hasClass(DOMElement $node, string $class): bool {
        return in_array($class, explode(' ', $node->getAttribute('class')), true);
    }

    public function test_dates_in_two_months_share_one_grid_from_the_first_week_to_the_last(): void {
        $event_id = $this->createEvent();
        foreach (array('2030-07-25', '2030-08-01', '2030-08-08') as $date) {
            $this->createOccurrence($event_id, $date);
        }

        $xpath = $this->render($event_id);
        $days  = $xpath->query("//*[contains(concat(' ', @class, ' '), ' mindevents-run-day ')]");

        $this->assertSame(3, $xpath->query("//*[@class='mindevents-run__week']")->length);
        $this->assertSame('2030-07-22', $days->item(0)->getAttribute('data-date'));
        $this->assertSame('2030-08-11', $days->item($days->length - 1)->getAttribute('data-date'));
        $this->assertSame(0, $xpath->query("//*[contains(@class, 'mindevents-calendar-nav')]")->length, 'A run has no month buttons.');
        $this->assertStringContainsString('3 dates', $xpath->query("//*[@class='mindevents-run__count']")->item(0)->textContent);
    }

    public function test_the_month_is_named_where_the_grid_starts_and_on_the_first(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-25');
        $this->createOccurrence($event_id, '2030-08-02');

        $xpath = $this->render($event_id);
        $named = array();
        foreach ($xpath->query("//*[@class='mindevents-run-day__month']") as $node) {
            $named[] = $node->parentNode->getAttribute('datetime');
        }

        $this->assertSame(array('2030-07-22', '2030-08-01'), $named);
    }

    public function test_a_stepped_line_marks_where_the_next_month_begins(): void {
        $event_id = $this->createEvent();
        foreach (array('2030-07-25', '2030-08-01', '2030-08-08') as $date) {
            $this->createOccurrence($event_id, $date);
        }

        $xpath = $this->render($event_id);

        // August 1, 2030 is a Thursday: a line on its left and above it,
        // above the rest of that week, and above the Monday to Wednesday
        // below July's last days.
        $first = $this->day($xpath, '2030-08-01');
        $this->assertTrue($this->hasClass($first, 'is-month-left'));
        $this->assertTrue($this->hasClass($first, 'is-month-top'));
        $this->assertTrue($this->hasClass($this->day($xpath, '2030-08-04'), 'is-month-top'));
        $this->assertTrue($this->hasClass($this->day($xpath, '2030-08-06'), 'is-month-top'));
        $this->assertFalse($this->hasClass($this->day($xpath, '2030-08-08'), 'is-month-top'));
        $this->assertFalse($this->hasClass($this->day($xpath, '2030-07-31'), 'is-month-top'));
        $this->assertFalse($this->hasClass($this->day($xpath, '2030-07-31'), 'is-month-left'));

        $this->assertFalse($this->hasClass($this->day($xpath, '2030-07-31'), 'is-alt-month'));
        $this->assertTrue($this->hasClass($first, 'is-alt-month'));
    }

    public function test_weeks_without_dates_fold_into_one_line(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-25');
        $this->createOccurrence($event_id, '2030-08-22');

        $xpath = $this->render($event_id);
        $gaps  = $xpath->query("//*[@class='mindevents-run__gap']");

        $this->assertSame(2, $xpath->query("//*[@class='mindevents-run__week']")->length);
        $this->assertSame(1, $gaps->length);
        $this->assertSame('No dates Jul 29 – Aug 18', $gaps->item(0)->textContent);

        // Below the gap, the grid starts again: the month is named, and no
        // line is drawn against the gap.
        $after = $this->day($xpath, '2030-08-19');
        $this->assertSame(1, $xpath->query(".//*[@class='mindevents-run-day__month']", $after)->length);
        $this->assertFalse($this->hasClass($after, 'is-month-top'));
    }

    public function test_each_day_with_dates_opens_its_list_on_a_narrow_screen(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-27', '14:00', '16:00');
        $this->createOccurrence($event_id, '2030-07-27', '19:30', '21:30');

        $calendar = new mindEventCalendar($event_id, null, '2030-07-01');
        $html     = $calendar->get_run_calendar();
        $dom      = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new DOMXPath($dom);

        $day    = $this->day($xpath, '2030-07-27');
        $button = $xpath->query(".//button[@class='mindevents-run-day__open']", $day)->item(0);
        $this->assertNotNull($button);
        $this->assertSame(2, $xpath->query(".//*[@class='mindevents-run-dot']", $button)->length);
        $this->assertSame(2, $xpath->query(".//*[@class='mindevents-calendar-event-card']", $day)->length, 'The wide layout lists both dates in the day.');

        $template = $dom->getElementById($button->getAttribute('data-template'));
        $this->assertNotNull($template, 'The button names a template in the page.');
        $this->assertSame('template', $template->nodeName);

        // Two calendars on one page don't share template ids.
        $this->assertStringNotContainsString($button->getAttribute('data-template'), (new mindEventCalendar($event_id, null, '2030-07-01'))->get_run_calendar());
    }

    public function test_a_day_without_dates_is_not_a_button(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-25');

        $xpath = $this->render($event_id);

        $this->assertSame(1, $xpath->query("//button[@class='mindevents-run-day__open']")->length);
        $this->assertSame(0, $xpath->query(".//button", $this->day($xpath, '2030-07-24'))->length);
    }

    public function test_an_event_with_no_dates_says_so(): void {
        $html = (new mindEventCalendar($this->createEvent(), null, '2030-07-01'))->get_run_calendar();

        $this->assertStringContainsString('mindevents-notice', $html);
        $this->assertStringNotContainsString('mindevents-run__body', $html);
    }

    public function test_the_count_can_be_worded_for_the_site(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-25');
        $this->createOccurrence($event_id, '2030-07-26');

        $filter = function ($label, $count) {
            return sprintf('%d performances', $count);
        };
        add_filter('mindevents_occurrence_count_label', $filter, 10, 2);
        $xpath = $this->render($event_id);
        remove_filter('mindevents_occurrence_count_label', $filter, 10);

        $this->assertSame('2 performances', $xpath->query("//*[@class='mindevents-run__count']")->item(0)->textContent);
    }

    public function test_month_buttons_on_an_event_say_how_many_dates_each_month_has(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20');
        $this->createOccurrence($event_id, '2030-08-03');
        $this->createOccurrence($event_id, '2030-08-10');

        $html = (new mindEventCalendar($event_id, '2030-07-01', '2030-07-01'))->get_calendar_nav_links('month');

        preg_match_all('#<a [^>]*>(.*?)</a>#s', $html, $links);
        $this->assertStringNotContainsString('mindevents-calendar-nav__count', $links[1][0], 'June has no dates, so its button has no count.');
        $this->assertStringContainsString('2 more dates', $links[1][1]);
        $this->assertStringContainsString('August', $links[1][1]);
    }

    public function test_month_buttons_count_only_dates_still_to_come_when_past_ones_are_hidden(): void {
        $event_id = $this->createEvent();
        $past     = (new DateTimeImmutable('now', wp_timezone()))->modify('first day of last month')->modify('+1 day');
        $this->createOccurrence($event_id, $past->format('Y-m-d'));

        $calendar = new mindEventCalendar($event_id, (new DateTimeImmutable('now', wp_timezone()))->format('Y-m-01'));
        $calendar->set_past_events_display(false);

        $this->assertStringNotContainsString('mindevents-calendar-nav__count', $calendar->get_calendar_nav_links('month'));
    }

    public function test_the_block_can_show_a_run(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2040-07-20');

        $html = render_block(array(
            'blockName'    => 'simple-events/calendar',
            'attrs'        => array('display' => 'run', 'event' => $event_id),
            'innerBlocks'  => array(),
            'innerHTML'    => '',
            'innerContent' => array(),
        ));

        $this->assertStringContainsString('mindevents-run__body', $html);
    }
}
