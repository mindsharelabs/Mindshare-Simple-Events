<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Time
 *
 * An occurrence's time is stored in exactly two meta keys,
 * mindevents_start_utc and mindevents_end_utc, as Y-m-d H:i:s in UTC: the
 * same way WordPress stores post_date_gmt. Nothing else is stored.
 *
 * Admin input is read as wall-clock time in the site timezone and converted
 * once, on save. Display converts back to the site timezone. The functions
 * below are the only code that needs to know how times are stored.
 */

if (!function_exists('mindevents_to_utc')) {
    /**
     * A moment in time, formatted for storage.
     */
    function mindevents_to_utc(DateTimeInterface $time) {
        return gmdate('Y-m-d H:i:s', $time->getTimestamp());
    }
}

if (!function_exists('mindevents_from_utc')) {
    /**
     * A stored value, in the site timezone. Null if missing or malformed.
     */
    function mindevents_from_utc($value) {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $time ? $time->setTimezone(wp_timezone()) : null;
    }
}

if (!function_exists('mindevents_iso8601')) {
    /**
     * A stored value as ISO 8601 with the site's UTC offset, for output
     * read by machines. Empty if the value is missing.
     */
    function mindevents_iso8601($utc) {
        $time = mindevents_from_utc($utc);

        return $time ? $time->format('c') : '';
    }
}

if (!function_exists('mindevents_now_utc')) {
    /**
     * The current time, formatted for comparing against stored values.
     */
    function mindevents_now_utc() {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('mindevents_get_occurrence_times')) {
    /**
     * An occurrence's start and end in the site timezone, or null.
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}|null
     */
    function mindevents_get_occurrence_times($occurrence_id) {
        $start = mindevents_from_utc(get_post_meta($occurrence_id, 'mindevents_start_utc', true));
        $end   = mindevents_from_utc(get_post_meta($occurrence_id, 'mindevents_end_utc', true));

        if (!$start || !$end) {
            return null;
        }

        return array('start' => $start, 'end' => $end);
    }
}

if (!function_exists('mindevents_local_times')) {
    /**
     * An occurrence's start and end, from a Y-m-d date and two H:i
     * wall-clock times in the site timezone.
     *
     * An end earlier than the start is taken to be on the following day, so
     * 22:00 to 01:00 runs overnight. Returns null if anything is invalid.
     */
    function mindevents_local_times($date, $start_time, $end_time) {
        $timezone = wp_timezone();
        $start    = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start_time, $timezone);
        $end      = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $end_time, $timezone);

        if (!$start || !$end || $start->format('Y-m-d') !== $date) {
            return null;
        }

        if ($end < $start) {
            $end = $end->modify('+1 day');
        }

        return array('start' => $start, 'end' => $end);
    }
}

if (!function_exists('mindevents_overlapping_meta_query')) {
    /**
     * A meta query clause matching occurrences that overlap a period.
     *
     * An occurrence overlaps when it starts before the period ends and ends
     * after it starts, so one that began the night before still appears.
     */
    function mindevents_overlapping_meta_query(DateTimeInterface $from, DateTimeInterface $to) {
        return array(
            'relation' => 'AND',
            array(
                'key'     => 'mindevents_start_utc',
                'value'   => mindevents_to_utc($to),
                'compare' => '<=',
                'type'    => 'DATETIME',
            ),
            array(
                'key'     => 'mindevents_end_utc',
                'value'   => mindevents_to_utc($from),
                'compare' => '>',
                'type'    => 'DATETIME',
            ),
        );
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

if (!function_exists('mindevents_sanitize_color')) {
    /**
     * A hex color, or '' for anything else. sanitize_hex_color() returns
     * null, which meta cannot store.
     */
    function mindevents_sanitize_color($value) {
        return (string) sanitize_hex_color((string) $value);
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
     * The occurrence and its event must both be published. Every public
     * read path that is handed an ID, rather than running a query limited
     * to published posts, must check this first.
     */
    function mindevents_is_public_occurrence($post_id) {
        $occurrence = get_post(absint($post_id));
        if (!$occurrence || $occurrence->post_type !== 'mind_sub_event' || $occurrence->post_status !== 'publish') {
            return false;
        }

        $event = get_post($occurrence->post_parent);
        if (!$event || $event->post_type !== 'mind_events' || $event->post_status !== 'publish') {
            return false;
        }

        return true;
    }
}

if (!function_exists('mindevents_sync_event_date_range')) {
    /**
     * Record an event's first start and last end, for sorting and schema.
     */
    function mindevents_sync_event_date_range($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $range = array(
            'mindevents_first_start_utc' => array('mindevents_start_utc', 'ASC'),
            'mindevents_last_end_utc'    => array('mindevents_end_utc', 'DESC'),
        );

        foreach ($range as $event_key => $order) {
            list($occurrence_key, $direction) = $order;

            $occurrences = get_posts(array(
                'post_type'        => 'mind_sub_event',
                'post_status'      => array('publish', 'pending', 'draft', 'future', 'private'),
                'post_parent'      => $event_id,
                'posts_per_page'   => 1,
                'fields'           => 'ids',
                'orderby'          => 'meta_value',
                'meta_key'         => $occurrence_key,
                'meta_type'        => 'DATETIME',
                'order'            => $direction,
                'suppress_filters' => true,
            ));

            if ($occurrences) {
                update_post_meta($event_id, $event_key, get_post_meta($occurrences[0], $occurrence_key, true));
            } else {
                delete_post_meta($event_id, $event_key);
            }
        }
    }
}

if (!function_exists('mindevents_sync_event_taxonomies_to_children')) {
    function mindevents_sync_event_taxonomies_to_children($event_id) {
        $event_id = absint($event_id);
        if (!$event_id) {
            return;
        }

        $term_ids = wp_get_post_terms($event_id, 'mind_event_category', array('fields' => 'ids'));
        $children = get_posts(array(
            'post_type'      => 'mind_sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $event_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            wp_set_post_terms($child_id, $term_ids, 'mind_event_category');
        }
    }
}

if (!function_exists('mindevents_occurrence_status')) {
    /**
     * The status an occurrence takes from its event's status.
     *
     * Occurrences are public only once their event is. A scheduled event's
     * occurrences stay drafts until it publishes, because an occurrence
     * given 'future' with a past date would be published by WordPress
     * immediately. Drafts, rather than auto-drafts, so occurrences added
     * before an event's first save still show in the admin calendar.
     */
    function mindevents_occurrence_status($event_status) {
        return in_array($event_status, array('publish', 'private', 'pending', 'trash'), true) ? $event_status : 'draft';
    }
}

if (!function_exists('mindevents_transition_child_statuses')) {
    function mindevents_transition_child_statuses($new_status, $old_status, $parent_post) {
        // transition_post_status fires on every save, not only on changes.
        if ($new_status === $old_status || !($parent_post instanceof WP_Post) || $parent_post->post_type !== 'mind_events') {
            return;
        }

        $children = get_posts(array(
            'post_type'      => 'mind_sub_event',
            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash'),
            'post_parent'    => $parent_post->ID,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        foreach ($children as $child_id) {
            wp_update_post(array(
                'ID'          => $child_id,
                'post_status' => mindevents_occurrence_status($new_status),
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
            'post_type'      => 'mind_sub_event',
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
            $term_object = get_term((int) $term, 'mind_event_category');
        }

        if (!($term_object instanceof WP_Term)) {
            return '';
        }

        $color = get_term_meta($term_object->term_id, 'mindevents_category_color', true);

        return $color ? (string) sanitize_hex_color($color) : '';
    }
}

if (!function_exists('mindevents_get_occurrence_colors')) {
    /**
     * The colors an occurrence is drawn in: its own color if one was set,
     * otherwise the colors of its categories, or its event's.
     */
    function mindevents_get_occurrence_colors($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return array();
        }

        $own_color = sanitize_hex_color((string) get_post_meta($post_id, 'eventColor', true));
        if ($own_color) {
            return array($own_color);
        }

        $terms = get_the_terms($post_id, 'mind_event_category');
        if ((!$terms || is_wp_error($terms)) && wp_get_post_parent_id($post_id)) {
            $terms = get_the_terms(wp_get_post_parent_id($post_id), 'mind_event_category');
        }

        $colors = array();
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $colors[] = mindevents_get_category_color($term);
            }
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

        $terms = get_the_terms($post_id, 'mind_event_category');
        if ((!$terms || is_wp_error($terms)) && wp_get_post_parent_id($post_id)) {
            $terms = get_the_terms(wp_get_post_parent_id($post_id), 'mind_event_category');
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
