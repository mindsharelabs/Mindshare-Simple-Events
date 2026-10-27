<?php

/**
 * Migration from the 1.x data model: `events`, `sub_event` with local
 * time stamps, and `event_category`.
 *
 * run() migrates every 1.x post in the database, not only the ones a test
 * creates, so assertions are about the test's own posts.
 */
class MigrationTest extends Mindshare_Events_TestCase {
    private function createOldEvent(array $args = array()): int {
        $event_id = wp_insert_post(wp_slash(array_merge(array(
            'post_type'   => 'events',
            'post_title'  => 'Old Show',
            'post_status' => 'publish',
        ), $args)), true);

        $this->assertIsInt($event_id);

        return $event_id;
    }

    private function createOldOccurrence(int $event_id, string $start, string $end, array $args = array()): int {
        $occurrence_id = wp_insert_post(wp_slash(array_merge(array(
            'post_type'   => 'sub_event',
            'post_title'  => 'Old Show | date',
            'post_status' => 'publish',
            'post_parent' => $event_id,
            'meta_input'  => array(
                'event_start_time_stamp' => $start,
                'event_end_time_stamp'   => $end,
                'eventColor'             => '#ffd700',
                'eventDescription'       => 'Opening night',
            ),
        ), $args)), true);

        $this->assertIsInt($occurrence_id);

        return $occurrence_id;
    }

    /**
     * A category in the old taxonomy. It cannot be created there through the
     * API without registering the old name, which would block the migration.
     */
    private function createOldCategory(string $name, array $post_ids): int {
        global $wpdb;

        $term = wp_insert_term($name, 'mind_event_category');
        foreach ($post_ids as $post_id) {
            wp_set_post_terms($post_id, array($term['term_id']), 'mind_event_category');
        }

        $wpdb->update($wpdb->term_taxonomy, array('taxonomy' => 'event_category'), array('term_id' => $term['term_id']));
        wp_cache_flush();

        return (int) $term['term_id'];
    }

    private function local(string $time): DateTimeImmutable {
        return new DateTimeImmutable($time, wp_timezone());
    }

    public function test_events_occurrences_and_categories_are_migrated(): void {
        $event_id      = $this->createOldEvent(array('meta_input' => array('cast_0_name' => 'Ada')));
        $occurrence_id = $this->createOldOccurrence($event_id, '2030-07-01 19:30:00', '2030-07-01 22:00:00');
        $term_id       = $this->createOldCategory('Season 2030', array($event_id, $occurrence_id));

        $this->assertTrue(mindEventsMigration::has_legacy_data());

        mindEventsMigration::run();

        $this->assertSame('mind_events', get_post_type($event_id));
        $this->assertSame('mind_sub_event', get_post_type($occurrence_id));
        $this->assertSame('publish', get_post_status($occurrence_id));
        $this->assertSame('Old Show | 2030-07-01 | 19:30-22:00', get_the_title($occurrence_id));

        $times = mindevents_get_occurrence_times($occurrence_id);
        $this->assertEquals($this->local('2030-07-01 19:30'), $times['start']);
        $this->assertEquals($this->local('2030-07-01 22:00'), $times['end']);
        $this->assertSame(mindevents_to_utc($this->local('2030-07-01 19:30')), get_post_meta($event_id, 'mindevents_first_start_utc', true));

        $this->assertSame(array('#ffd700'), mindevents_get_occurrence_colors($occurrence_id));
        $this->assertSame('Opening night', get_post_meta($occurrence_id, 'eventDescription', true));
        $this->assertSame('Ada', get_post_meta($event_id, 'cast_0_name', true), 'Other meta is kept.');

        $this->assertTrue(has_term($term_id, 'mind_event_category', $event_id));
        $this->assertTrue(has_term($term_id, 'mind_event_category', $occurrence_id));
        $this->assertSame(2, get_term($term_id, 'mind_event_category')->count);
    }

    public function test_times_follow_daylight_saving_in_the_site_timezone(): void {
        $timezone = get_option('timezone_string');
        update_option('timezone_string', 'America/Denver');

        try {
            $event_id = $this->createOldEvent();
            $winter   = $this->createOldOccurrence($event_id, '2030-01-15 19:00:00', '2030-01-15 21:00:00');
            $summer   = $this->createOldOccurrence($event_id, '2030-07-15 19:00:00', '2030-07-15 21:00:00');

            mindEventsMigration::run();

            $this->assertSame('2030-01-16 02:00:00', get_post_meta($winter, 'mindevents_start_utc', true));
            $this->assertSame('2030-07-16 01:00:00', get_post_meta($summer, 'mindevents_start_utc', true));
        } finally {
            update_option('timezone_string', $timezone);
        }
    }

    public function test_an_occurrence_with_no_end_time_ends_when_it_starts(): void {
        $event_id      = $this->createOldEvent();
        $occurrence_id = $this->createOldOccurrence($event_id, '2030-07-01 16:00:00', '2030-07-01 00:00:00');

        $this->assertContains($occurrence_id, mindEventsMigration::survey()['clamped']);

        $report = mindEventsMigration::run();

        $this->assertContains($occurrence_id, $report['clamped']);
        $this->assertSame(get_post_meta($occurrence_id, 'mindevents_start_utc', true), get_post_meta($occurrence_id, 'mindevents_end_utc', true));
    }

    public function test_orphaned_and_trashed_occurrences_are_left_alone(): void {
        $orphan  = $this->createOldOccurrence(0, '2030-07-01 19:00:00', '2030-07-01 21:00:00');
        $trashed = $this->createOldOccurrence($this->createOldEvent(), '2030-07-02 19:00:00', '2030-07-02 21:00:00', array('post_status' => 'trash'));

        $report = mindEventsMigration::run();

        $this->assertArrayHasKey($orphan, $report['skipped']);
        $this->assertSame('sub_event', get_post_type($orphan));
        $this->assertSame('sub_event', get_post_type($trashed));
        $this->assertSame('', get_post_meta($trashed, 'mindevents_start_utc', true));
    }

    public function test_running_again_finds_nothing_left(): void {
        $event_id = $this->createOldEvent();
        $this->createOldOccurrence($event_id, '2030-07-01 19:00:00', '2030-07-01 21:00:00');

        mindEventsMigration::run();

        $this->assertFalse(mindEventsMigration::has_legacy_data());
        $survey = mindEventsMigration::survey();
        $this->assertSame(0, $survey['events']);
        $this->assertSame(0, $survey['occurrences']);
        $this->assertSame(0, $survey['categories']);
        $this->assertSame(0, $survey['acf']);
    }

    public function test_it_will_not_run_while_the_old_names_are_registered(): void {
        $this->assertSame('', mindEventsMigration::blocker());

        register_post_type('events');
        try {
            $this->assertStringContainsString('"events"', mindEventsMigration::blocker());
        } finally {
            unregister_post_type('events');
        }

        register_taxonomy('event_category', array());
        try {
            $this->assertStringContainsString('"event_category"', mindEventsMigration::blocker());
        } finally {
            unregister_taxonomy('event_category');
        }
    }

    public function test_acf_locations_and_taxonomy_fields_use_the_new_names(): void {
        $group_id = wp_insert_post(array(
            'post_type'    => 'acf-field-group',
            'post_status'  => 'publish',
            'post_title'   => 'Event Options',
            'post_content' => serialize(array(
                'location' => array(
                    array(array('param' => 'post_type', 'operator' => '==', 'value' => 'events')),
                    array(array('param' => 'post_taxonomy', 'operator' => '==', 'value' => 'event_category:season-2030')),
                    array(array('param' => 'post_type', 'operator' => '==', 'value' => 'page')),
                ),
            )),
        ));
        $field_id = wp_insert_post(array(
            'post_type'    => 'acf-field',
            'post_status'  => 'publish',
            'post_parent'  => $group_id,
            'post_content' => serialize(array('type' => 'taxonomy', 'taxonomy' => 'event_category')),
        ));

        mindEventsMigration::run();

        $location = unserialize(get_post_field('post_content', $group_id));
        $this->assertSame('mind_events', $location['location'][0][0]['value']);
        $this->assertSame('mind_event_category:season-2030', $location['location'][1][0]['value']);
        $this->assertSame('page', $location['location'][2][0]['value']);

        $this->assertSame('mind_event_category', unserialize(get_post_field('post_content', $field_id))['taxonomy']);
    }

    public function test_only_administrators_can_run_it(): void {
        $event_id = $this->createOldEvent();
        $this->actAs('editor');

        $die = function () {
            return function ($message) {
                throw new Mindshare_Events_Ajax_Die((string) $message);
            };
        };
        add_filter('wp_die_handler', $die);

        try {
            (new mindEventsMigration())->handle_run();
            $this->fail('An editor ran the migration.');
        } catch (Mindshare_Events_Ajax_Die $exception) {
            $this->assertSame('events', get_post_type($event_id));
        } finally {
            remove_filter('wp_die_handler', $die);
        }
    }
}
