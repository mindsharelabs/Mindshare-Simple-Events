<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Separate processes, since uninstall.php defines WP_UNINSTALL_PLUGIN.
 */
class UninstallTest extends Mindshare_Events_TestCase {
    private function uninstall(): void {
        define('WP_UNINSTALL_PLUGIN', plugin_basename(MINDEVENTS_PLUGIN_FILE));
        include MINDEVENTS_ABSPATH . 'uninstall.php';
        wp_roles()->for_site();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_uninstall_removes_settings_roles_and_capabilities(): void {
        update_option('mindevents_support_settings', array('mindevents_start_day' => 'Sunday'));

        $this->uninstall();

        $this->assertFalse(get_option('mindevents_support_settings'));
        $this->assertFalse(get_option('mindevents_schema_version'));
        $this->assertNull(get_role('mindevents_manager'));
        foreach (wp_roles()->role_objects as $role) {
            $this->assertFalse($role->has_cap('edit_mindevents_events'), $role->name);
            $this->assertFalse($role->has_cap('manage_mindevents_settings'), $role->name);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_uninstall_keeps_events_unless_told_to_remove_them(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');

        $this->uninstall();

        $this->assertNotNull(get_post($event_id));
        $this->assertNotNull(get_post($occurrence_id));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_uninstall_removes_all_data_when_asked(): void {
        $event_id      = $this->createEvent();
        $occurrence_id = $this->createOccurrence($event_id, '2030-05-01');
        $term          = wp_insert_term('Ceramics', 'mind_event_category');
        update_term_meta($term['term_id'], 'mindevents_category_color', '#aa0000');

        define('MINDEVENTS_REMOVE_ALL_DATA', true);
        $this->uninstall();
        wp_cache_flush();

        $this->assertNull(get_post($event_id));
        $this->assertNull(get_post($occurrence_id));
        $this->assertSame(array(), get_post_meta($occurrence_id));
        $this->assertNull(term_exists($term['term_id']));
    }
}
