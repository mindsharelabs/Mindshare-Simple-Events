<?php

/**
 * Private events use WordPress's own Private status, so core hides them
 * everywhere, including surfaces the plugin does not control. (S8)
 */
class PrivateEventTest extends Mindshare_Events_TestCase {
    private $event_id;
    private $occurrence_id;

    protected function setUp(): void {
        parent::setUp();
        $this->event_id      = $this->createEvent(array('post_title' => 'Zebra Staff Retreat', 'post_status' => 'private'));
        $this->occurrence_id = $this->createOccurrence($this->event_id, '2030-05-01');
    }

    public function test_occurrences_of_a_private_event_are_private(): void {
        $this->assertSame('private', get_post_status($this->occurrence_id));
    }

    public function test_occurrences_follow_when_an_event_is_made_private(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        wp_update_post(array('ID' => $event_id, 'post_status' => 'private'));

        $this->assertSame('private', get_post_status($occurrence_id));
    }

    public function test_hidden_from_anonymous_visitors_everywhere(): void {
        $single = new WP_Query(array('post_type' => 'events', 'p' => $this->event_id));
        $search = new WP_Query(array('s' => 'Zebra Staff Retreat', 'post_type' => 'any'));
        $rest   = rest_do_request(new WP_REST_Request('GET', '/wp/v2/events'));
        $plugin = rest_do_request(new WP_REST_Request('GET', '/simple-events/v1/events'));
        $map    = (new WP_Sitemaps_Posts())->get_url_list(1, 'events');

        $this->assertSame(0, $single->post_count, 'single page');
        $this->assertNotContains($this->event_id, wp_list_pluck($search->posts, 'ID'), 'site search');
        $this->assertNotContains($this->event_id, wp_list_pluck($rest->get_data(), 'id'), 'core REST');
        $this->assertNotContains($this->occurrence_id, wp_list_pluck($plugin->get_data()['events'], 'id'), 'plugin REST');
        $this->assertNotContains(get_permalink($this->event_id), wp_list_pluck($map, 'loc'), 'sitemap');
        $this->assertStringNotContainsString('Zebra', mindevents_generate_ics_feed(), 'ICS feed');
        $this->assertSame('', (new mindEventCalendar($this->event_id))->generate_schema(), 'schema');
    }

    public function test_people_who_manage_events_can_still_see_it(): void {
        $this->actAs('mindevents_manager');

        $single = new WP_Query(array('post_type' => 'events', 'p' => $this->event_id));

        $this->assertSame(1, $single->post_count);
        $this->assertTrue(current_user_can('read_post', $this->event_id));
    }

    public function test_no_visibility_meta_is_stored(): void {
        $this->assertFalse(metadata_exists('post', $this->event_id, 'mindevents_visibility'));
        $this->assertFalse(metadata_exists('post', $this->occurrence_id, 'mindevents_visibility'));
    }
}
