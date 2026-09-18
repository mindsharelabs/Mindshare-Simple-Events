<?php

/**
 * Each weekday heading must sit above days that are actually that weekday.
 */
class WeekdayHeadingTest extends Mindshare_Events_TestCase {
    /**
     * @return array<int, array{label: string, date: string}> One entry per column.
     */
    private function firstWeek(string $html): array {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new DOMXPath($dom);

        $labels = array();
        foreach ($xpath->query("//*[contains(concat(' ', @class, ' '), ' mindevents-calendar-weekday ')]") as $node) {
            $labels[] = trim($node->textContent);
        }

        // The second row is a full week with no padding cells.
        $cells = $xpath->query("(//*[contains(concat(' ', @class, ' '), ' mindevents-calendar-week ')])[2]/*[@data-date]");
        $columns = array();
        foreach ($cells as $index => $cell) {
            $columns[] = array('label' => $labels[$index], 'date' => $cell->getAttribute('data-date'));
        }

        return $columns;
    }

    private function assertHeadingsMatchDates(string $start_day): void {
        update_option('mindevents_support_settings', array('mindevents_start_day' => $start_day));

        $columns = $this->firstWeek((new mindEventCalendar($this->createEvent(), '2026-09-01'))->get_calendar());

        $this->assertCount(7, $columns);
        $this->assertSame(wp_date('l', strtotime("next $start_day")), $columns[0]['label'], "Week should start on $start_day");
        foreach ($columns as $column) {
            $this->assertSame(wp_date('l', strtotime($column['date'])), $column['label'], "{$column['date']} is under the wrong heading");
        }
    }

    public function test_headings_match_dates_when_weeks_start_on_monday(): void {
        $this->assertHeadingsMatchDates('Monday');
    }

    public function test_headings_match_dates_when_weeks_start_on_sunday(): void {
        $this->assertHeadingsMatchDates('Sunday');
    }
}
