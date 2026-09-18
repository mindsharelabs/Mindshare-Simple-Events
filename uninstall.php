<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Settings, roles and capabilities are always removed. Events, occurrences
 * and event categories are the site's content, so they are only deleted
 * when wp-config.php opts in:
 *
 *     define('MINDEVENTS_REMOVE_ALL_DATA', true);
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/inc/core/installer.php';

mindEventsInstaller::uninstall();
delete_option('mindevents_support_settings');

if (!defined('MINDEVENTS_REMOVE_ALL_DATA') || !MINDEVENTS_REMOVE_ALL_DATA) {
    return;
}

// The plugin is not loaded here, so nothing cascades: delete each type.
foreach (array('mind_sub_event', 'mind_events') as $post_type) {
    $post_ids = get_posts(array(
        'post_type'        => $post_type,
        'post_status'      => array_keys(get_post_stati()),
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'suppress_filters' => true,
    ));

    foreach ($post_ids as $post_id) {
        wp_delete_post($post_id, true);
    }
}

// Terms can only be read and deleted through a registered taxonomy.
if (!taxonomy_exists('event_category')) {
    register_taxonomy('event_category', array());
}

$term_ids = get_terms(array(
    'taxonomy'   => 'event_category',
    'hide_empty' => false,
    'fields'     => 'ids',
));

if (!is_wp_error($term_ids)) {
    foreach ($term_ids as $term_id) {
        wp_delete_term($term_id, 'event_category');
    }
}
