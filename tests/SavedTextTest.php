<?php

/**
 * Text is stored exactly as entered. WordPress's meta and post functions
 * unslash their input, so values that are already unslashed must be
 * slashed again before being handed over, or backslashes are lost.
 */
class SavedTextTest extends Mindshare_Events_TestCase {
    public function test_adding_an_occurrence_keeps_backslashes(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01', '19:00', '21:00', array(
            'mindevents_location' => 'C:\\Studio',
            'eventDescription'    => 'Bring a 1\\2 inch brush',
        ));

        $this->assertSame('C:\\Studio', get_post_meta($occurrence_id, 'mindevents_location', true));
        $this->assertSame('Bring a 1\\2 inch brush', get_post_meta($occurrence_id, 'eventDescription', true));
    }

    public function test_editing_an_occurrence_keeps_backslashes(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        (new mindEventCalendar($event_id))->update_sub_event($occurrence_id, array(
            'event_date'          => '2030-05-01',
            'starttime'           => '19:00',
            'endtime'             => '21:00',
            'mindevents_location' => 'C:\\Studio',
        ), $event_id);

        $this->assertSame('C:\\Studio', get_post_meta($occurrence_id, 'mindevents_location', true));
    }

    public function test_saving_event_settings_keeps_backslashes(): void {
        $this->actAs('administrator');
        $event_id = $this->createEvent();

        $_POST = wp_slash(array(
            'mindevents_event_meta_nonce' => wp_create_nonce('mindevents_event_meta'),
            'event_meta'                  => array('mindevents_location' => 'C:\\Studio'),
        ));
        do_action('save_post_mind_events', $event_id, get_post($event_id), true);

        $this->assertSame('C:\\Studio', get_post_meta($event_id, 'mindevents_location', true));
    }

    public function test_occurrence_titles_keep_backslashes_from_the_event_title(): void {
        $event_id      = $this->createEvent(array('post_title' => 'AC\\DC Tribute'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $this->assertStringStartsWith('AC\\DC Tribute |', get_post_field('post_title', $occurrence_id));
    }
}
