<?php

/**
 * Registered meta is sanitized on every write, whichever path it arrives by.
 */
class MetaSanitizationTest extends Mindshare_Events_TestCase {
    /**
     * Events do not support custom-fields, so their meta is not in the REST
     * API today. The sanitizers run on every write regardless, so any other
     * code path gets the same treatment.
     */
    public function test_event_meta_is_sanitized_on_every_write(): void {
        $event_id = $this->createEvent();

        update_post_meta($event_id, 'mindevents_location', '<b>Main</b> Hall');
        update_post_meta($event_id, 'mindevents_organizer_name', "Ana\n<script>x</script>");
        update_post_meta($event_id, 'mindevents_visibility', 'anything-else');
        update_post_meta($event_id, 'mindevents_organizer_image_id', '-12');

        $this->assertSame('Main Hall', get_post_meta($event_id, 'mindevents_location', true));
        $this->assertSame('Ana', get_post_meta($event_id, 'mindevents_organizer_name', true));
        $this->assertSame('public', get_post_meta($event_id, 'mindevents_visibility', true));
        $this->assertSame('12', get_post_meta($event_id, 'mindevents_organizer_image_id', true));
    }

    public function test_category_color_written_through_the_rest_api_is_sanitized(): void {
        $this->actAs('administrator');
        $term = wp_insert_term('Glass', 'event_category');

        $request = new WP_REST_Request('POST', '/wp/v2/event_category/' . $term['term_id']);
        $request->set_body_params(array('meta' => array('mindevents_category_color' => 'red;background:url(x)')));
        rest_do_request($request);

        $this->assertSame('', get_term_meta($term['term_id'], 'mindevents_category_color', true));
    }
}
