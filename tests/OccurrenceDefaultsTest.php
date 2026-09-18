<?php

/**
 * New dates take only their times from the event's defaults. Everything
 * else falls back to the event, so later changes to it reach every date.
 */
class OccurrenceDefaultsTest extends Mindshare_Events_TestCase {
    private $event_id;

    protected function setUp(): void {
        parent::setUp();
        $this->actAs('administrator');
        $this->event_id = $this->createEvent();
        $GLOBALS['post'] = get_post($this->event_id);
    }

    private function metabox(): string {
        ob_start();
        (new mindeventsAdmin())->display_calendar_metabox(get_post($this->event_id));

        return ob_get_clean();
    }

    public function test_the_defaults_form_holds_only_start_and_end_times(): void {
        preg_match('#<fieldset id="defaultEventMeta".*?</fieldset>#s', $this->metabox(), $fieldset);
        preg_match_all('/name="event\[([^\]]+)\]"/', $fieldset[0], $fields);

        $this->assertSame(array('starttime', 'endtime'), $fields[1]);
    }

    public function test_a_new_date_follows_later_changes_to_the_event(): void {
        update_post_meta($this->event_id, 'mindevents_location', 'Hall A');

        $this->ajax('mindevents_selectday', array(
            'nonce'   => wp_create_nonce('mindevents_ajax'),
            'eventid' => $this->event_id,
            'date'    => '2030-05-01',
            'meta'    => array('event' => array('starttime' => '10:00', 'endtime' => '11:00')),
        ));
        update_post_meta($this->event_id, 'mindevents_location', 'Hall B');

        $occurrence = get_posts(array('post_type' => 'mind_sub_event', 'post_parent' => $this->event_id, 'fields' => 'ids'));
        $this->assertSame('Hall B', mindevents_get_occurrence_location($occurrence[0]));
    }

    public function test_saved_defaults_contain_only_times(): void {
        $_POST = wp_slash(array(
            'mindevents_event_meta_nonce' => wp_create_nonce('mindevents_event_meta'),
            'event'                       => array('starttime' => '18:30', 'endtime' => '20:00', 'mindevents_location' => 'Ignored'),
        ));
        do_action('save_post_mind_events', $this->event_id, get_post($this->event_id), true);

        $this->assertSame(array('starttime' => '18:30', 'endtime' => '20:00'), get_post_meta($this->event_id, 'event_defaults', true));
    }
}
