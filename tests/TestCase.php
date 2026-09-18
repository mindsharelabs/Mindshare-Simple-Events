<?php

use PHPUnit\Framework\TestCase;

/**
 * Thrown in place of wp_die() while a test is calling an AJAX handler.
 */
class Mindshare_Events_Ajax_Die extends Exception {}

/**
 * Base class for plugin tests.
 *
 * Every test runs inside a database transaction that is rolled back in
 * tearDown(), and the object cache is flushed either side, so tests can
 * create posts, users and roles freely without touching the site's data.
 */
abstract class Mindshare_Events_TestCase extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        wp_cache_flush();
        $wpdb->query('START TRANSACTION');
    }

    protected function tearDown(): void {
        global $wpdb;
        $wpdb->query('ROLLBACK');
        wp_cache_flush();

        // Roles are cached in memory, so reload them from the rolled-back option.
        wp_roles()->for_site();

        wp_set_current_user(0);
        unset($GLOBALS['current_screen']);
        $_GET = $_POST = $_REQUEST = array();

        remove_all_filters('wp_doing_ajax');
        remove_all_filters('wp_die_ajax_handler');

        parent::tearDown();
    }

    /**
     * Create a user with the given role and make them the current user.
     */
    protected function actAs(string $role): int {
        $user_id = wp_insert_user(array(
            'user_login' => 'test_' . $role . '_' . wp_generate_password(6, false),
            'user_pass'  => wp_generate_password(),
            'role'       => $role,
        ));

        $this->assertIsInt($user_id);
        wp_set_current_user($user_id);

        return $user_id;
    }

    protected function createEvent(array $args = array()): int {
        // wp_insert_post() expects slashed input, as it would get from a form.
        $event_id = wp_insert_post(wp_slash(array_merge(array(
            'post_type'   => 'mind_events',
            'post_title'  => 'Test Event',
            'post_status' => 'publish',
        ), $args)), true);

        $this->assertIsInt($event_id);

        return $event_id;
    }

    /**
     * Add an occurrence through the plugin's own API.
     *
     * $date is Y-m-d; times are H:i in the site timezone.
     */
    protected function createOccurrence(int $event_id, string $date, string $start = '19:00', string $end = '21:00', array $meta = array()): int {
        $calendar = new mindEventCalendar($event_id);
        $meta     = array_merge(array('starttime' => $start, 'endtime' => $end), $meta);

        $occurrence_id = $calendar->add_sub_event($date, $meta, $event_id);

        $this->assertIsInt($occurrence_id, 'Occurrence could not be created.');

        return $occurrence_id;
    }

    /**
     * Call a registered AJAX action the way admin-ajax.php would.
     *
     * Returns the decoded JSON response, or ['died' => message] when the
     * handler stopped with wp_die() before sending JSON.
     */
    protected function ajax(string $action, array $data = array()): array {
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';

        $GLOBALS['current_screen'] = WP_Screen::get('admin-ajax');

        $_POST = $_REQUEST = wp_slash($data);

        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', function () {
            return function ($message) {
                throw new Mindshare_Events_Ajax_Die((string) $message);
            };
        });

        $hook = is_user_logged_in() ? 'wp_ajax_' . $action : 'wp_ajax_nopriv_' . $action;

        ob_start();
        try {
            do_action($hook);
            $died = null;
        } catch (Mindshare_Events_Ajax_Die $exception) {
            $died = $exception->getMessage();
        }
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return array('died' => $died, 'output' => $output);
    }
}
