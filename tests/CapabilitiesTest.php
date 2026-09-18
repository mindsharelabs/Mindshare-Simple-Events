<?php

class CapabilitiesTest extends Mindshare_Events_TestCase {
    public function test_events_use_their_own_namespaced_capabilities(): void {
        $caps = get_post_type_object('events')->cap;

        $this->assertSame('edit_mindevents_events', $caps->edit_posts);
        $this->assertSame('edit_mindevents_events', get_post_type_object('sub_event')->cap->edit_posts);
    }

    public function test_administrators_and_editors_manage_events(): void {
        foreach (array('administrator', 'editor') as $role) {
            $this->actAs($role);
            $event_id = $this->createEvent(array('post_author' => 1));

            $this->assertTrue(current_user_can('edit_post', $event_id), "$role should edit events");
            $this->assertTrue(current_user_can('manage_mindevents_categories'), "$role should manage event categories");
        }
    }

    public function test_other_default_roles_cannot_edit_events(): void {
        $event_id = $this->createEvent();

        foreach (array('author', 'contributor', 'subscriber') as $role) {
            $this->actAs($role);
            $this->assertFalse(current_user_can('edit_post', $event_id), "$role should not edit events");
        }
    }

    public function test_event_manager_manages_events_and_nothing_else(): void {
        $this->actAs('mindevents_manager');
        $event_id = $this->createEvent(array('post_author' => 1));
        $page_id  = wp_insert_post(array('post_type' => 'page', 'post_title' => 'About', 'post_status' => 'publish', 'post_author' => 1));
        $post_id  = wp_insert_post(array('post_type' => 'post', 'post_title' => 'News', 'post_status' => 'publish', 'post_author' => 1));

        $this->assertTrue(current_user_can('edit_post', $event_id));
        $this->assertTrue(current_user_can('publish_mindevents_events'));
        $this->assertTrue(current_user_can('manage_mindevents_categories'));
        $this->assertTrue(current_user_can('manage_mindevents_settings'));
        $this->assertTrue(current_user_can('upload_files'));

        $this->assertFalse(current_user_can('edit_post', $page_id));
        $this->assertFalse(current_user_can('edit_post', $post_id));
        $this->assertFalse(current_user_can('manage_options'));
        $this->assertFalse(current_user_can('manage_categories'));
    }

    public function test_only_admins_and_event_managers_change_settings(): void {
        $this->actAs('editor');
        $this->assertFalse(current_user_can('manage_mindevents_settings'));

        $this->actAs('administrator');
        $this->assertTrue(current_user_can('manage_mindevents_settings'));
    }

    public function test_occurrence_permissions_follow_the_event(): void {
        $owner = $this->actAs('administrator');
        add_role('mindevents_test_own_only', 'Own only', array(
            'read'                                 => true,
            'edit_mindevents_events'               => true,
            'edit_published_mindevents_events'     => true,
            'publish_mindevents_events'            => true,
        ));

        $others_event = $this->createEvent(array('post_author' => $owner));
        $others_occ   = $this->createOccurrence($others_event, '2030-05-01');

        $this->actAs('mindevents_test_own_only');
        $own_event = $this->createEvent(array('post_author' => get_current_user_id()));
        $own_occ   = $this->createOccurrence($own_event, '2030-05-01');

        // Force the occurrence's own author to someone else: only the event decides.
        wp_update_post(array('ID' => $own_occ, 'post_author' => $owner));

        $this->assertTrue(current_user_can('edit_post', $own_occ));
        $this->assertTrue(current_user_can('delete_post', $own_occ));
        $this->assertFalse(current_user_can('edit_post', $others_occ));
        $this->assertFalse(current_user_can('delete_post', $others_occ));

        remove_role('mindevents_test_own_only');
    }

    public function test_missing_capabilities_are_reinstalled_after_a_version_change(): void {
        get_role('editor')->remove_cap('edit_mindevents_events');
        delete_option('mindevents_schema_version');

        mindEventsInstaller::maybe_install();

        $this->assertTrue(get_role('editor')->has_cap('edit_mindevents_events'));
    }
}
