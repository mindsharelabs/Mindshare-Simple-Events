<?php

/**
 * Add-ons put their own buttons on a date, in lists and in the dialog.
 */
class OccurrenceActionsTest extends Mindshare_Events_TestCase {
    protected function tearDown(): void {
        remove_all_actions('mindevents_occurrence_actions');
        parent::tearDown();
    }

    public function test_add_ons_can_add_buttons_to_a_date(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2040-07-20');

        $seen = array();
        add_action('mindevents_occurrence_actions', function ($id, $context) use (&$seen) {
            $seen[$context] = $id;
            echo '<a class="book-now" href="#">Book</a>';
        }, 10, 2);

        $calendar = new mindEventCalendar($event_id);
        $list     = $calendar->get_list_item_html($occurrence_id);
        $dialog   = $calendar->get_cal_meta_html($occurrence_id);

        $this->assertSame(array('list' => $occurrence_id, 'detail' => $occurrence_id), $seen);
        $this->assertStringContainsString('book-now', $list);
        $this->assertStringContainsString('book-now', $dialog);
    }

    public function test_nothing_is_added_when_no_add_on_is_listening(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2040-07-20');

        $this->assertSame('', mindevents_occurrence_actions($occurrence_id, 'list'));
    }
}
