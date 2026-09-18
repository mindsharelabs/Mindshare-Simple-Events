<?php

/**
 * A user may only change occurrences that belong to events they can edit,
 * whatever parent ID the request claims.
 */
class OccurrenceOwnershipTest extends Mindshare_Events_TestCase {
    private $own_event;
    private $others_event;
    private $others_occurrence;

    protected function setUp(): void {
        parent::setUp();

        // Someone else's event and occurrence.
        $other_user = wp_insert_user(array('user_login' => 'other_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => 'administrator'));
        $this->others_event      = $this->createEvent(array('post_title' => 'Not Yours', 'post_author' => $other_user));
        $this->others_occurrence = $this->createOccurrence($this->others_event, '2030-05-01', '19:00', '21:00');

        // A user who can edit only events they own.
        $this->actAs($this->ownEventsOnlyRole());
        $this->own_event = $this->createEvent(array('post_title' => 'Yours', 'post_author' => get_current_user_id()));
    }

    protected function tearDown(): void {
        remove_role('mindevents_test_own_only');
        parent::tearDown();
    }

    /**
     * Returns a role that can edit and publish its own events but not others'.
     */
    private function ownEventsOnlyRole(): string {
        $type = get_post_type_object('events');
        add_role('mindevents_test_own_only', 'Own events only', array(
            'read'                         => true,
            $type->cap->edit_posts         => true,
            $type->cap->publish_posts      => true,
            $type->cap->edit_published_posts => true,
            $type->cap->delete_posts       => true,
            $type->cap->delete_published_posts => true,
        ));

        return 'mindevents_test_own_only';
    }

    private function request(string $action, array $data): array {
        return $this->ajax($action, array_merge(array('nonce' => wp_create_nonce('mindevents_ajax')), $data));
    }

    public function test_user_can_move_their_own_occurrence(): void {
        $own_occurrence = $this->createOccurrence($this->own_event, '2030-05-01', '10:00', '11:00');

        $response = $this->request('mindevents_moveevent', array(
            'eventid'  => $own_occurrence,
            'parentid' => $this->own_event,
            'new_date' => '2030-05-03',
        ));

        $this->assertTrue($response['success'] ?? false, wp_json_encode($response));
    }

    public function test_cannot_move_another_events_occurrence_by_claiming_own_parent(): void {
        $before = get_post_meta($this->others_occurrence);

        $this->request('mindevents_moveevent', array(
            'eventid'    => $this->others_occurrence,
            'parentid'   => $this->own_event,
            'new_date'   => '2030-05-09',
            'start_date' => '2030-05-01 19:00:00',
            'end_date'   => '2030-05-01 21:00:00',
        ));

        wp_cache_flush();
        $this->assertSame($before, get_post_meta($this->others_occurrence));
    }

    public function test_cannot_overwrite_another_events_occurrence_by_claiming_own_parent(): void {
        $before_title = get_post_field('post_title', $this->others_occurrence);

        $this->request('mindevents_updatesubevent', array(
            'eventid'  => $this->others_occurrence,
            'parentid' => $this->own_event,
            'meta'     => array('event_date' => '2030-05-09', 'starttime' => '08:00', 'endtime' => '09:00'),
        ));

        wp_cache_flush();
        $this->assertSame($before_title, get_post_field('post_title', $this->others_occurrence));
    }

    public function test_cannot_read_another_events_occurrence_form_by_claiming_own_parent(): void {
        $response = $this->request('mindevents_editevent', array(
            'eventid'  => $this->others_occurrence,
            'parentid' => $this->own_event,
        ));

        $this->assertFalse($response['success'] ?? false);
    }

    public function test_cannot_attach_occurrences_to_posts_that_are_not_events(): void {
        $page_id = wp_insert_post(array('post_type' => 'post', 'post_title' => 'A blog post', 'post_status' => 'publish', 'post_author' => get_current_user_id()));
        $this->actAs('administrator');

        $this->request('mindevents_selectday', array(
            'eventid' => $page_id,
            'date'    => '2030-05-01',
            'meta'    => array('event' => array('starttime' => '10:00', 'endtime' => '11:00')),
        ));

        $children = get_posts(array('post_type' => 'sub_event', 'post_parent' => $page_id, 'post_status' => 'any'));
        $this->assertEmpty($children);
    }

    public function test_delete_endpoint_only_deletes_occurrences(): void {
        $this->actAs('administrator');
        $page_id = wp_insert_post(array('post_type' => 'page', 'post_title' => 'About Us', 'post_status' => 'publish'));

        $this->request('mindevents_deleteevent', array('eventid' => $page_id));

        $this->assertNotNull(get_post($page_id), 'A page was permanently deleted through the occurrence endpoint.');
    }

    public function test_moving_keeps_the_stored_time_of_day_and_duration(): void {
        $own_occurrence = $this->createOccurrence($this->own_event, '2030-05-01', '22:00', '23:30');

        $this->request('mindevents_moveevent', array(
            'eventid'  => $own_occurrence,
            'new_date' => '2030-05-03',
        ));

        wp_cache_flush();
        $this->assertSame('2030-05-03 22:00:00', get_post_meta($own_occurrence, 'event_start_time_stamp', true));
        $this->assertSame('2030-05-03 23:30:00', get_post_meta($own_occurrence, 'event_end_time_stamp', true));
    }

    public function test_invalid_input_is_not_echoed_back_in_errors(): void {
        $own_occurrence = $this->createOccurrence($this->own_event, '2030-05-01');
        $payload        = '<img src=x onerror=alert(1)>';

        $update = $this->request('mindevents_updatesubevent', array(
            'eventid' => $own_occurrence,
            'meta'    => array('event_date' => $payload, 'starttime' => '10:00', 'endtime' => '11:00'),
        ));
        $add = $this->request('mindevents_selectday', array(
            'eventid' => $this->own_event,
            'date'    => $payload,
            'meta'    => array('event' => array('starttime' => '10:00', 'endtime' => '11:00')),
        ));

        $this->assertFalse($update['success'] ?? true);
        $this->assertStringNotContainsString('<img', wp_json_encode($update, JSON_UNESCAPED_SLASHES));
        $this->assertStringNotContainsString('<img', wp_json_encode($add, JSON_UNESCAPED_SLASHES));
    }
}
