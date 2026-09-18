<?php

/**
 * Lane layout for overlapping occurrences in the week view.
 */
class WeekLayoutTest extends Mindshare_Events_TestCase {
    private function layout(array $occurrences): array {
        $method = new ReflectionMethod('mindEventCalendar', 'position_day_occurrences');
        $method->setAccessible(true);

        $byId = array();
        foreach ($method->invoke(new mindEventCalendar(), $occurrences) as $item) {
            $byId[$item['id']] = $item;
        }

        return $byId;
    }

    private function occurrence(int $id, string $start, string $end): array {
        list($sh, $sm) = array_map('intval', explode(':', $start));
        list($eh, $em) = array_map('intval', explode(':', $end));

        return array('id' => $id, 'start_minutes' => $sh * 60 + $sm, 'end_minutes' => $eh * 60 + $em);
    }

    public function test_simultaneous_occurrences_split_the_column(): void {
        $layout = $this->layout(array(
            $this->occurrence(1, '09:00', '10:00'),
            $this->occurrence(2, '09:00', '10:00'),
        ));

        $this->assertEqualsWithDelta(50, $layout[1]['width'], 0.001);
        $this->assertEqualsWithDelta(50, $layout[2]['width'], 0.001);
        $this->assertNotEquals($layout[1]['left'], $layout[2]['left']);
    }

    public function test_sequential_occurrences_keep_full_width(): void {
        $layout = $this->layout(array(
            $this->occurrence(1, '09:00', '10:00'),
            $this->occurrence(2, '11:00', '12:00'),
        ));

        $this->assertEqualsWithDelta(100, $layout[1]['width'], 0.001);
        $this->assertEqualsWithDelta(100, $layout[2]['width'], 0.001);
    }

    public function test_back_to_back_occurrences_do_not_count_as_overlapping(): void {
        $layout = $this->layout(array(
            $this->occurrence(1, '09:00', '10:00'),
            $this->occurrence(2, '10:00', '11:00'),
        ));

        $this->assertEqualsWithDelta(100, $layout[2]['width'], 0.001);
    }

    public function test_a_freed_lane_is_reused_instead_of_opening_a_new_one(): void {
        // 1 frees lane 1 at 10:00 while 2 still holds lane 0.
        $layout = $this->layout(array(
            $this->occurrence(1, '09:00', '10:00'),
            $this->occurrence(2, '09:00', '11:00'),
            $this->occurrence(3, '10:00', '10:30'),
        ));

        $this->assertSame(1, $layout[3]['lane']);
        $this->assertSame(2, $layout[3]['lanes']);
    }

    public function test_clusters_are_independent(): void {
        $layout = $this->layout(array(
            $this->occurrence(1, '09:00', '10:00'),
            $this->occurrence(2, '09:00', '10:00'),
            $this->occurrence(3, '15:00', '16:00'),
        ));

        $this->assertEqualsWithDelta(50, $layout[1]['width'], 0.001);
        $this->assertEqualsWithDelta(100, $layout[3]['width'], 0.001);
    }

    public function test_zero_length_occurrences_get_a_minimum_height(): void {
        $layout = $this->layout(array($this->occurrence(1, '09:00', '09:00')));

        $expected = (mindEventCalendar::MIN_EVENT_MINUTES / mindEventCalendar::MINUTES_PER_DAY) * 100;
        $this->assertEqualsWithDelta($expected, $layout[1]['height'], 0.0001);
    }

    public function test_geometry_stays_inside_the_column(): void {
        $layout = $this->layout(array(
            $this->occurrence(1, '00:00', '23:59'),
            $this->occurrence(2, '00:00', '23:59'),
            $this->occurrence(3, '00:00', '23:59'),
            $this->occurrence(4, '06:00', '07:00'),
        ));

        foreach ($layout as $item) {
            $this->assertGreaterThanOrEqual(0, $item['left']);
            $this->assertLessThanOrEqual(100.0001, $item['left'] + $item['width']);
            $this->assertLessThanOrEqual(100.0001, $item['top'] + $item['height']);
        }
    }
}
