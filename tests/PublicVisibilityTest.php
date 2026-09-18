<?php

/**
 * Nothing that is not a published, public occurrence of a published event
 * may be served to an anonymous visitor.
 */
class PublicVisibilityTest extends Mindshare_Events_TestCase {
    private function eventDetail(int $post_id): array {
        return $this->ajax('mindevents_get_event_meta_html', array(
            'eventid' => $post_id,
            'nonce'   => wp_create_nonce('mindevents_ajax'),
        ));
    }

    public function test_event_detail_serves_a_public_occurrence(): void {
        $event_id      = $this->createEvent(array('post_title' => 'Open Studio'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $response = $this->eventDetail($occurrence_id);

        $this->assertTrue($response['success'] ?? false);
        $this->assertStringContainsString('Open Studio', $response['data']['html']);
    }

    public function test_event_detail_hides_occurrences_of_draft_events(): void {
        $event_id      = $this->createEvent(array('post_title' => 'Secret Draft'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');
        wp_update_post(array('ID' => $event_id, 'post_status' => 'draft'));

        $response = $this->eventDetail($occurrence_id);

        $this->assertStringNotContainsString('Secret Draft', wp_json_encode($response));
        $this->assertFalse($response['success'] ?? false);
    }

    public function test_event_detail_hides_occurrences_of_private_events(): void {
        $event_id      = $this->createEvent(array('post_title' => 'Staff Only', 'post_status' => 'private'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $response = $this->eventDetail($occurrence_id);

        $this->assertStringNotContainsString('Staff Only', wp_json_encode($response));
        $this->assertFalse($response['success'] ?? false);
    }

    public function test_event_detail_refuses_posts_that_are_not_occurrences(): void {
        $page_id = wp_insert_post(array(
            'post_type'    => 'page',
            'post_title'   => 'Unpublished Pricing Page',
            'post_excerpt' => 'Confidential numbers',
            'post_status'  => 'draft',
        ));

        $response = $this->eventDetail($page_id);

        $this->assertStringNotContainsString('Unpublished Pricing Page', wp_json_encode($response));
        $this->assertStringNotContainsString('Confidential numbers', wp_json_encode($response));
        $this->assertFalse($response['success'] ?? false);
    }

    public function test_single_ics_download_refuses_occurrences_of_draft_events(): void {
        $event_id      = $this->createEvent(array('post_title' => 'Secret Draft'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');
        wp_update_post(array('ID' => $event_id, 'post_status' => 'draft'));

        $this->assertStringNotContainsString('BEGIN:VEVENT', mindevents_generate_single_event_ics($occurrence_id));
    }

    public function test_single_ics_download_refuses_trashed_occurrences(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');
        wp_trash_post($occurrence_id);

        $this->assertStringNotContainsString('BEGIN:VEVENT', mindevents_generate_single_event_ics($occurrence_id));
    }

    public function test_event_list_excludes_occurrences_of_private_events(): void {
        $public  = $this->createOccurrence($this->createEvent(), '2030-05-01');
        $private = $this->createOccurrence($this->createEvent(array('post_status' => 'private')), '2030-05-02');

        $calendar = new mindEventCalendar();
        $calendar->get_front_list();
        $listed = wp_list_pluck($calendar->get_last_front_list_query()->posts, 'ID');

        $this->assertContains($public, $listed);
        $this->assertNotContains($private, $listed);
    }

    public function test_next_occurrence_subtitle_skips_unpublished_occurrences(): void {
        $event_id = $this->createEvent();
        wp_trash_post($this->createOccurrence($event_id, '2030-05-01'));
        $this->createOccurrence($event_id, '2030-06-15');

        ob_start();
        do_action('mindevents_single_title', $event_id);
        $html = ob_get_clean();

        $this->assertStringContainsString(date_i18n(get_option('date_format'), strtotime('2030-06-15')), $html);
        $this->assertStringNotContainsString(date_i18n(get_option('date_format'), strtotime('2030-05-01')), $html);
    }

    public function test_occurrences_added_to_an_unpublished_event_are_not_published(): void {
        $draft_id      = $this->createEvent(array('post_title' => 'Unannounced Workshop', 'post_status' => 'draft'));
        $occurrence_id = $this->createOccurrence($draft_id, '2030-05-01');

        $this->assertNotSame('publish', get_post_status($occurrence_id));

        $rest = rest_do_request(new WP_REST_Request('GET', '/simple-events/v1/events'));
        $this->assertNotContains($occurrence_id, wp_list_pluck($rest->get_data()['events'], 'id'));
        $this->assertStringNotContainsString('Unannounced Workshop', mindevents_generate_ics_feed());
    }

    public function test_occurrences_follow_their_event_when_it_is_published(): void {
        $event_id      = $this->createEvent(array('post_status' => 'draft'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        wp_publish_post($event_id);

        $this->assertSame('publish', get_post_status($occurrence_id));
    }

    public function test_occurrences_of_a_scheduled_event_wait_for_it_to_publish(): void {
        $event_id      = $this->createEvent(array('post_status' => 'draft'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        wp_update_post(array(
            'ID'            => $event_id,
            'edit_date'     => true,
            'post_status'   => 'future',
            'post_date'     => gmdate('Y-m-d H:i:s', time() + WEEK_IN_SECONDS),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', time() + WEEK_IN_SECONDS),
        ));

        $this->assertSame('future', get_post_status($event_id));
        $this->assertNotSame('publish', get_post_status($occurrence_id));
    }

    /**
     * Pages are often served from a full-page cache for longer than a nonce
     * lives, so a read-only public request must not depend on one.
     */
    public function test_event_detail_works_without_a_nonce(): void {
        $occurrence_id = $this->createOccurrence($this->createEvent(array('post_title' => 'Open Studio')), '2030-05-01');

        $response = $this->ajax('mindevents_get_event_meta_html', array('eventid' => $occurrence_id));

        $this->assertTrue($response['success'] ?? false, wp_json_encode($response));
    }

    public function test_changing_data_still_requires_a_nonce(): void {
        $this->actAs('administrator');
        $occurrence_id = $this->createOccurrence($this->createEvent(), '2030-05-01');

        $this->ajax('mindevents_deleteevent', array('eventid' => $occurrence_id));

        $this->assertNotNull(get_post($occurrence_id));
    }
}
