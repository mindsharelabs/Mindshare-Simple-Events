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

    public function test_event_detail_hides_internal_occurrences(): void {
        $event_id      = $this->createEvent(array('post_title' => 'Staff Only'));
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01', '19:00', '21:00', array('mindevents_visibility' => 'internal'));

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

    public function test_event_list_excludes_internal_occurrences(): void {
        $event_id = $this->createEvent();
        $public   = $this->createOccurrence($event_id, '2030-05-01');
        $internal = $this->createOccurrence($event_id, '2030-05-02', '19:00', '21:00', array('mindevents_visibility' => 'internal'));

        $calendar = new mindEventCalendar($event_id);
        $calendar->get_front_list();
        $listed = wp_list_pluck($calendar->get_last_front_list_query()->posts, 'ID');

        $this->assertContains($public, $listed);
        $this->assertNotContains($internal, $listed);
    }
}
