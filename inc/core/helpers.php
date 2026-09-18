<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mindevents_wp_timezone')) {
    function mindevents_wp_timezone() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $timezone_string = get_option('timezone_string');

        return new DateTimeZone($timezone_string ? $timezone_string : 'UTC');
    }
}

if (!function_exists('mindevents_normalize_time_value')) {
    function mindevents_normalize_time_value($value, $fallback = '') {
        $value = sanitize_text_field((string) $value);

        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $fallback;
        }

        return gmdate('H:i', $timestamp);
    }
}

if (!function_exists('mindevents_sanitize_visibility')) {
    function mindevents_sanitize_visibility($value) {
        $value = sanitize_key((string) $value);

        return ($value === 'internal') ? 'internal' : 'public';
    }
}

if (!function_exists('mindevents_apply_visibility_meta')) {
    function mindevents_apply_visibility_meta($post_id, $visibility) {
        $visibility = mindevents_sanitize_visibility($visibility);

        update_post_meta($post_id, 'mindevents_visibility', $visibility);
    }
}

if (!function_exists('mindevents_get_post_visibility')) {
    function mindevents_get_post_visibility($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return 'public';
        }

        $value = get_post_meta($post_id, 'mindevents_visibility', true);
        if ($value === 'public' || $value === 'internal') {
            return $value;
        }

        $parent_id = (int) wp_get_post_parent_id($post_id);
        if ($parent_id > 0) {
            return mindevents_get_post_visibility($parent_id);
        }

        return 'public';
    }
}

if (!function_exists('mindevents_is_internal_post')) {
    function mindevents_is_internal_post($post_id) {
        return mindevents_get_post_visibility($post_id) === 'internal';
    }
}

if (!function_exists('mindevents_plain_text')) {
    /**
     * Display HTML as plain text, for output that is data rather than
     * markup: JSON-LD, ICS, calendar links and API responses.
     *
     * WordPress display functions return entities such as &#038;, which
     * those formats would show literally.
     */
    function mindevents_plain_text($html) {
        return trim(html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

if (!function_exists('mindevents_get_plain_title')) {
    function mindevents_get_plain_title($post_id) {
        return mindevents_plain_text(get_the_title($post_id));
    }
}

if (!function_exists('mindevents_is_public_occurrence')) {
    /**
     * Whether an occurrence may be shown to someone who cannot edit it.
     *
     * The occurrence and its event must both be published, and neither may
     * be internal. Every public read path that is handed an ID, rather
     * than running a filtered query, must check this first.
     */
    function mindevents_is_public_occurrence($post_id) {
        $occurrence = get_post(absint($post_id));
        if (!$occurrence || $occurrence->post_type !== 'sub_event' || $occurrence->post_status !== 'publish') {
            return false;
        }

        $event = get_post($occurrence->post_parent);
        if (!$event || $event->post_type !== 'events' || $event->post_status !== 'publish') {
            return false;
        }

        return !mindevents_is_internal_post($occurrence->ID) && !mindevents_is_internal_post($event->ID);
    }
}

if (!function_exists('mindevents_public_visibility_meta_query')) {
    function mindevents_public_visibility_meta_query() {
        return array(
            'relation' => 'OR',
            array(
                'key'     => 'mindevents_visibility',
                'value'   => 'public',
                'compare' => '=',
            ),
            array(
                'key'     => 'mindevents_visibility',
                'compare' => 'NOT EXISTS',
            ),
        );
    }
}

if (!function_exists('mindevents_sync_event_visibility_to_children')) {
    function mindevents_sync_event_visibility_to_children($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $visibility = mindevents_get_post_visibility($event_id);
        $children   = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $event_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            mindevents_apply_visibility_meta($child_id, $visibility);
        }
    }
}

if (!function_exists('mindevents_sync_event_date_range')) {
    function mindevents_sync_event_date_range($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $first_event = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private'),
            'posts_per_page' => 1,
            'post_parent'    => $event_id,
            'orderby'        => 'meta_value',
            'meta_key'       => 'event_start_time_stamp',
            'meta_type'      => 'DATETIME',
            'order'          => 'ASC',
            'suppress_filters' => true,
        ));

        $last_event = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private'),
            'posts_per_page' => 1,
            'post_parent'    => $event_id,
            'orderby'        => 'meta_value',
            'meta_key'       => 'event_end_time_stamp',
            'meta_type'      => 'DATETIME',
            'order'          => 'DESC',
            'suppress_filters' => true,
        ));

        if (!empty($first_event[0])) {
            update_post_meta($event_id, 'first_event_date', get_post_meta($first_event[0]->ID, 'event_start_time_stamp', true));
        } else {
            delete_post_meta($event_id, 'first_event_date');
        }

        if (!empty($last_event[0])) {
            update_post_meta($event_id, 'last_event_date', get_post_meta($last_event[0]->ID, 'event_end_time_stamp', true));
        } else {
            delete_post_meta($event_id, 'last_event_date');
        }
    }
}

if (!function_exists('mindevents_sync_event_taxonomies_to_children')) {
    function mindevents_sync_event_taxonomies_to_children($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $term_ids = wp_get_post_terms($event_id, 'event_category', array('fields' => 'ids'));
        $children = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $event_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            wp_set_post_terms($child_id, $term_ids, 'event_category');
        }
    }
}

if (!function_exists('mindevents_transition_child_statuses')) {
    function mindevents_transition_child_statuses($new_status, $old_status, $parent_post) {
        if (!($parent_post instanceof WP_Post) || $parent_post->post_type !== 'events') {
            return;
        }

        $children = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $parent_post->ID,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            wp_update_post(array(
                'ID'          => $child_id,
                'post_status' => $new_status,
            ));
        }
    }
}

if (!function_exists('mindevents_delete_child_occurrences')) {
    function mindevents_delete_child_occurrences($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $children = get_posts(array(
            'post_type'      => 'sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $event_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            wp_delete_post($child_id, true);
        }
    }
}

if (!function_exists('mindevents_get_category_color')) {
    function mindevents_get_category_color($term) {
        $term_object = null;

        if ($term instanceof WP_Term) {
            $term_object = $term;
        } elseif (is_numeric($term)) {
            $term_object = get_term((int) $term, 'event_category');
        }

        if (!($term_object instanceof WP_Term)) {
            return '';
        }

        $color = get_term_meta($term_object->term_id, 'mindevents_category_color', true);

        return $color ? (string) sanitize_hex_color($color) : '';
    }
}

if (!function_exists('mindevents_get_post_category_colors')) {
    function mindevents_get_post_category_colors($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return array();
        }

        $colors = array();
        $terms  = get_the_terms($post_id, 'event_category');

        if ((!$terms || is_wp_error($terms)) && wp_get_post_parent_id($post_id)) {
            $terms = get_the_terms(wp_get_post_parent_id($post_id), 'event_category');
        }

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $color = mindevents_get_category_color($term);
                if ($color) {
                    $colors[] = $color;
                }
            }
        }

        $event_color = get_post_meta($post_id, 'eventColor', true);
        if (!$colors && $event_color) {
            $colors[] = sanitize_hex_color($event_color);
        }

        return array_values(array_filter(array_unique($colors)));
    }
}

if (!function_exists('mindevents_get_occurrence_location')) {
    function mindevents_get_occurrence_location($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        $location = get_post_meta($post_id, 'mindevents_location', true);
        if ($location !== '') {
            return sanitize_text_field($location);
        }

        $parent_id = (int) wp_get_post_parent_id($post_id);
        if ($parent_id > 0) {
            return mindevents_get_occurrence_location($parent_id);
        }

        return '';
    }
}

if (!function_exists('mindevents_get_organizer_data')) {
    /**
     * The occurrence's organizer, or its event's when it has none.
     */
    function mindevents_get_organizer_data($post_id) {
        $post_id = absint($post_id);
        $data    = array(
            'name'      => '',
            'title'     => '',
            'image_id'  => 0,
            'image_url' => '',
        );

        if (!$post_id) {
            return $data;
        }

        $source_id = $post_id;
        if (get_post_meta($post_id, 'mindevents_organizer_name', true) === '') {
            $source_id = (int) wp_get_post_parent_id($post_id);
        }

        if (!$source_id) {
            return $data;
        }

        $data['name']     = sanitize_text_field((string) get_post_meta($source_id, 'mindevents_organizer_name', true));
        $data['title']    = sanitize_text_field((string) get_post_meta($source_id, 'mindevents_organizer_title', true));
        $data['image_id'] = absint(get_post_meta($source_id, 'mindevents_organizer_image_id', true));

        if ($data['image_id']) {
            $data['image_url'] = (string) wp_get_attachment_image_url($data['image_id'], 'medium');
        }

        return $data;
    }
}

if (!function_exists('mindevents_get_occurrence_excerpt')) {
    function mindevents_get_occurrence_excerpt($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        $description = get_post_meta($post_id, 'eventDescription', true);
        if ($description !== '') {
            return wp_kses_post($description);
        }

        $parent_id = (int) wp_get_post_parent_id($post_id);
        if ($parent_id > 0) {
            return (string) get_the_excerpt($parent_id);
        }

        return (string) get_the_excerpt($post_id);
    }
}

if (!function_exists('mindevents_get_occurrence_terms_payload')) {
    function mindevents_get_occurrence_terms_payload($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return array();
        }

        $terms = get_the_terms($post_id, 'event_category');
        if ((!$terms || is_wp_error($terms)) && wp_get_post_parent_id($post_id)) {
            $terms = get_the_terms(wp_get_post_parent_id($post_id), 'event_category');
        }

        if (!$terms || is_wp_error($terms)) {
            return array();
        }

        $payload = array();

        foreach ($terms as $term) {
            $payload[] = array(
                'id'    => (int) $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'color' => mindevents_get_category_color($term),
            );
        }

        return $payload;
    }
}

if (!function_exists('mindevents_get_site_prod_id')) {
    function mindevents_get_site_prod_id() {
        $name = wp_strip_all_tags(get_bloginfo('name'));
        if ($name === '') {
            $name = 'Simple Events';
        }

        return '-//' . $name . '//Simple Events//EN';
    }
}
