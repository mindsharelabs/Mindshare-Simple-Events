<?php

class CalendarDateInputTest extends Mindshare_Events_TestCase {
    public function test_invalid_calendar_date_in_the_url_does_not_crash(): void {
        $_GET['calendar_date'] = 'not-a-date';

        $calendar = new mindEventCalendar();

        $this->assertSame(wp_date('Y-m'), $calendar->getDate()->format('Y-m'));
    }

    public function test_valid_calendar_date_in_the_url_is_used(): void {
        $_GET['calendar_date'] = '2031-02-10';

        $calendar = new mindEventCalendar();

        $this->assertSame('2031-02-10', $calendar->getDate()->format('Y-m-d'));
    }

    public function test_loose_date_strings_in_the_url_are_ignored(): void {
        // Relative formats such as "+1 year" would otherwise be accepted.
        $_GET['calendar_date'] = '+1 year';

        $calendar = new mindEventCalendar();

        $this->assertSame(wp_date('Y-m'), $calendar->getDate()->format('Y-m'));
    }
}
