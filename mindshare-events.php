<?php
/**
 * Plugin Name: Mindshare Simple Events
 * Plugin URI: https://mind.sh/are
 * Description: A self-contained WordPress events plugin with occurrence management, archive views, ICS feeds, and a read-only events API.
 * Version: 2.0.0
 * Author: Mindshare Labs, Inc.
 * Author URI: https://mind.sh/are
 * Text Domain: simple-events
 */

if (!defined('ABSPATH')) {
    exit;
}

final class mindEvents {
    private static $instance = null;
    private $components = array();

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->bootstrap();
    }

    private function define_constants() {
        if (!defined('MINDEVENTS_PLUGIN_FILE')) {
            define('MINDEVENTS_PLUGIN_FILE', __FILE__);
        }

        if (!defined('MINDEVENTS_ABSPATH')) {
            define('MINDEVENTS_ABSPATH', plugin_dir_path(__FILE__));
        }

        if (!defined('MINDEVENTS_PLUGIN_VERSION')) {
            define('MINDEVENTS_PLUGIN_VERSION', '2.0.0');
        }

        if (!defined('MINDEVENTS_PREPEND')) {
            define('MINDEVENTS_PREPEND', 'mindevents_');
        }
    }

    private function includes() {
        require_once MINDEVENTS_ABSPATH . 'inc/core/helpers.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/post-types.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/options.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/admin.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/events.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/api.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/frontend.php';
        require_once MINDEVENTS_ABSPATH . 'inc/core/ajax.php';
    }

    private function bootstrap() {
        $this->components['options'] = new mindEventsOptions();
        $this->components['post_types'] = new mindEventsCPTS();
        $this->components['admin'] = new mindeventsAdmin();
        $this->components['ajax'] = new mindEventsAjax();

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_front_assets'));
        add_action('wp_head', array($this, 'output_schema'), 20);

        add_action('save_post_events', array($this, 'sync_event_children'), 40, 2);
        add_action('save_post_sub_event', array($this, 'sync_sub_event_parent_dates'), 20, 3);
        add_action('transition_post_status', array($this, 'transition_sub_events'), 20, 3);
        add_action('before_delete_post', array($this, 'maybe_delete_event_children'), 20);
        add_action('deleted_post', array($this, 'sync_parent_after_delete'), 20, 2);
    }

    public function enqueue_front_assets() {
        if (!is_post_type_archive('events') && !is_singular('events') && !is_tax('event_category')) {
            return;
        }

        wp_enqueue_style(
            'mindevents-frontend',
            plugins_url('css/style.css', MINDEVENTS_PLUGIN_FILE),
            array(),
            MINDEVENTS_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'mindevents-frontend',
            plugins_url('js/mindevents.js', MINDEVENTS_PLUGIN_FILE),
            array('jquery'),
            MINDEVENTS_PLUGIN_VERSION,
            true
        );

        $post_id = is_post_type_archive('events') || is_tax('event_category') ? 0 : get_the_ID();

        wp_localize_script('mindevents-frontend', 'mindeventsSettings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mindevents_ajax'),
            'post_id'  => absint($post_id),
        ));
    }

    public function enqueue_admin_assets() {
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $is_event_editor = ($screen->post_type === 'events' && in_array($screen->base, array('post', 'post-new'), true));
        $is_event_terms = ($screen->taxonomy ?? '') === 'event_category';
        $is_settings = $screen->id === 'settings_page_mindevents-settings';

        if (!$is_event_editor && !$is_event_terms && !$is_settings) {
            return;
        }

        wp_enqueue_style(
            'mindevents-admin',
            plugins_url('css/admin.css', MINDEVENTS_PLUGIN_FILE),
            array(),
            MINDEVENTS_PLUGIN_VERSION
        );

        if ($is_event_editor) {
            wp_enqueue_script(
                'mindevents-admin',
                plugins_url('js/admin.js', MINDEVENTS_PLUGIN_FILE),
                array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
                MINDEVENTS_PLUGIN_VERSION,
                true
            );

            wp_localize_script('mindevents-admin', 'mindeventsSettings', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mindevents_ajax'),
                'post_id'  => get_the_ID(),
            ));
        }
    }

    public function output_schema() {
        if (!is_singular('events')) {
            return;
        }

        $calendar = new mindEventCalendar(get_queried_object_id());
        $schema   = $calendar->generate_schema();

        if ($schema === '') {
            return;
        }

        echo '<script type="application/ld+json">' . $schema . '</script>';
    }

    public function sync_event_children($post_id, $post) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'events') {
            return;
        }

        mindevents_sync_event_taxonomies_to_children($post_id);
        mindevents_sync_event_visibility_to_children($post_id);
        mindevents_sync_event_date_range($post_id);
    }

    public function sync_sub_event_parent_dates($post_id, $post, $update = false) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'sub_event') {
            return;
        }

        $parent_id = (int) $post->post_parent;
        if ($parent_id > 0) {
            mindevents_sync_event_date_range($parent_id);
        }
    }

    public function transition_sub_events($new_status, $old_status, $parent_post) {
        mindevents_transition_child_statuses($new_status, $old_status, $parent_post);
    }

    public function maybe_delete_event_children($post_id) {
        $post = get_post($post_id);
        if ($post instanceof WP_Post && $post->post_type === 'events') {
            mindevents_delete_child_occurrences($post_id);
        }
    }

    public function sync_parent_after_delete($post_id, $post = null) {
        if (!($post instanceof WP_Post) || $post->post_type !== 'sub_event') {
            return;
        }

        $parent_id = (int) $post->post_parent;
        if ($parent_id > 0) {
            mindevents_sync_event_date_range($parent_id);
        }
    }
}

function mindevents_activate() {
    mindEvents::get_instance();
    flush_rewrite_rules();
}

function mindevents_deactivate() {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'mindevents_activate');
register_deactivation_hook(__FILE__, 'mindevents_deactivate');

mindEvents::get_instance();
