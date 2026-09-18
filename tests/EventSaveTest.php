<?php

class EventSaveTest extends Mindshare_Events_TestCase {
    public function test_saving_an_event_without_a_status_change_leaves_occurrences_alone(): void {
        $event_id = $this->createEvent();
        foreach (array('2030-05-01', '2030-05-02', '2030-05-03') as $date) {
            $this->createOccurrence($event_id, $date);
        }

        $occurrence_saves = 0;
        add_action('save_post_sub_event', function () use (&$occurrence_saves) {
            $occurrence_saves++;
        });

        wp_update_post(array('ID' => $event_id, 'post_title' => 'Renamed'));

        $this->assertSame(0, $occurrence_saves);
    }
}
