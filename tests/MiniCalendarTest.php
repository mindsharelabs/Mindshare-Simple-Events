<?php

class MiniCalendarTest extends Mindshare_Events_TestCase {
    private function render(int $event_id): DOMXPath {
        $calendar = new mindEventCalendar($event_id, null, '2030-07-15');
        $html     = $calendar->get_mini_calendar();

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new DOMXPath($dom);
    }

    private function monthTitles(DOMXPath $xpath): array {
        $titles = array();
        foreach ($xpath->query("//*[@class='mindevents-mini-month__title']") as $node) {
            $titles[] = $node->textContent;
        }

        return $titles;
    }

    private function eventDays(DOMXPath $xpath): array {
        $days = array();
        foreach ($xpath->query("//button[contains(concat(' ', @class, ' '), ' has-events ')]") as $node) {
            $days[] = substr($node->getAttribute('data-template'), -10);
        }

        return $days;
    }

    public function test_months_share_the_width_up_to_three_across(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20');
        $this->assertStringContainsString('mindevents-mini-calendar--columns-1', (new mindEventCalendar($event_id))->get_mini_calendar());

        foreach (array('2030-08-20', '2030-09-20', '2030-10-20') as $date) {
            $this->createOccurrence($event_id, $date);
        }
        $this->assertStringContainsString('mindevents-mini-calendar--columns-3', (new mindEventCalendar($event_id))->get_mini_calendar());
    }

    public function test_the_event_page_has_no_summary_column(): void {
        $this->assertFalse(has_action('mindevents_single_thumb'));
        $this->assertFalse(has_action('mindevents_single_content'));
    }

    public function test_two_calendars_on_one_page_do_not_share_template_ids(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20');

        $first  = $this->eventTemplates($this->render($event_id));
        $second = $this->eventTemplates($this->render($event_id));

        $this->assertCount(1, $first);
        $this->assertEmpty(array_intersect($first, $second));
    }

    private function eventTemplates(DOMXPath $xpath): array {
        $ids = array();
        foreach ($xpath->query('//template') as $node) {
            $ids[] = $node->getAttribute('id');
        }

        return $ids;
    }

    public function test_the_display_type_can_be_saved(): void {
        $method = new ReflectionMethod('mindeventsAdmin', 'sanitize_event_meta');
        $method->setAccessible(true);

        $meta = $method->invoke(new mindeventsAdmin(), array('cal_display' => 'mini'));

        $this->assertSame('mini', $meta['cal_display']);
    }

    public function test_only_months_with_dates_are_shown_in_order(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-09-05');
        $this->createOccurrence($event_id, '2030-07-20');

        $this->assertSame(array('July 2030', 'September 2030'), $this->monthTitles($this->render($event_id)));
    }

    public function test_only_days_with_dates_are_buttons(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20');
        $this->createOccurrence($event_id, '2030-07-22');

        $xpath = $this->render($event_id);

        $this->assertSame(array('2030-07-20', '2030-07-22'), $this->eventDays($xpath));
        $this->assertSame(31, $xpath->query("//*[contains(concat(' ', @class, ' '), ' mindevents-mini-day ')][not(contains(@class, '--empty'))]")->length);
    }

    public function test_a_date_running_past_midnight_marks_both_days(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20', '22:00', '02:00');

        $this->assertSame(array('2030-07-20', '2030-07-21'), $this->eventDays($this->render($event_id)));
    }

    public function test_each_day_lists_its_own_dates(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20', '10:00', '11:00');
        $this->createOccurrence($event_id, '2030-07-20', '19:00', '21:00');
        $this->createOccurrence($event_id, '2030-07-22');

        $xpath = $this->render($event_id);
        $day   = function (string $date) use ($xpath) {
            $button = $xpath->query("//button[substring(@data-template, string-length(@data-template) - 9) = '$date']")->item(0);
            $cards  = $xpath->query("//template[@id='" . $button->getAttribute('data-template') . "']//article")->length;

            return array($button->getAttribute('aria-label'), $cards);
        };

        $this->assertStringContainsString('2 events', $day('2030-07-20')[0]);
        $this->assertSame(2, $day('2030-07-20')[1]);
        $this->assertSame(1, $day('2030-07-22')[1]);
    }

    public function test_past_dates_are_left_out_when_the_event_shows_upcoming_only(): void {
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2020-07-20');
        $this->createOccurrence($event_id, '2040-07-20');

        $calendar = new mindEventCalendar($event_id);
        $calendar->set_past_events_display('0');

        $this->assertStringNotContainsString('July 2020', $calendar->get_mini_calendar());
        $this->assertStringContainsString('July 2040', $calendar->get_mini_calendar());
    }

    public function test_an_event_with_no_dates_says_so(): void {
        $html = (new mindEventCalendar($this->createEvent()))->get_mini_calendar();

        $this->assertStringContainsString('There are no upcoming events.', $html);
    }

    public function test_the_weekday_row_follows_the_start_of_week_setting(): void {
        update_option('mindevents_support_settings', array('mindevents_start_day' => 'Sunday'));
        $event_id = $this->createEvent();
        $this->createOccurrence($event_id, '2030-07-20');

        $xpath   = $this->render($event_id);
        $leading = $xpath->query("//*[contains(@class, 'mindevents-mini-day--empty')]")->length;

        // 1 July 2030 is a Monday: one blank Sunday before it.
        $this->assertSame('S', $xpath->query("//*[@class='mindevents-mini-month__weekday']")->item(0)->textContent);
        $this->assertSame(1, $leading);
    }
}
