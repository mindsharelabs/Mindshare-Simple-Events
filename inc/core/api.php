<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('simple-events/v1', '/events', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'mindevents_rest_events',
        'permission_callback' => '__return_true',
        'args'                => array(
            'per_page'   => array('type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100),
            'page'       => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
            'status'     => array('type' => 'string', 'default' => 'upcoming', 'enum' => array('upcoming', 'past', 'all')),
            'after'      => array('type' => 'string'),
            'before'     => array('type' => 'string'),
            'orderby'    => array('type' => 'string', 'default' => 'start_time', 'enum' => array('start_time', 'end_time', 'title', 'date')),
            'order'      => array('type' => 'string', 'default' => 'ASC', 'enum' => array('ASC', 'DESC')),
            'categories' => array('type' => 'string'),
            'search'     => array('type' => 'string'),
            'parent'     => array('type' => 'string'),
            'include'    => array('type' => 'string'),
            'exclude'    => array('type' => 'string'),
        ),
    ));
});

function mindevents_rest_csv_to_ints($value) {
    if (empty($value)) {
        return array();
    }

    $parts = array_map('trim', explode(',', (string) $value));
    $ids   = array();

    foreach ($parts as $part) {
        $id = absint($part);
        if ($id) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * A date filter from the request, read in the site timezone unless it
 * carries its own offset, formatted for comparing against stored values.
 */
function mindevents_rest_datetime_to_utc($value) {
    if (empty($value)) {
        return '';
    }

    try {
        $date = new DateTimeImmutable((string) $value, wp_timezone());
    } catch (Exception $exception) {
        return '';
    }

    return mindevents_to_utc($date);
}

function mindevents_rest_find_parent_ids($search_term) {
    $search_term = sanitize_text_field((string) $search_term);
    if ($search_term === '') {
        return array();
    }

    return get_posts(array(
        'post_type'      => 'mind_events',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        's'              => $search_term,
    ));
}

function mindevents_rest_event_payload($occurrence_id) {
    $occurrence_id = absint($occurrence_id);
    $parent_id     = (int) wp_get_post_parent_id($occurrence_id);

    return array(
        'id'         => $occurrence_id,
        'parent_id'  => $parent_id,
        'title'      => mindevents_get_plain_title($parent_id ?: $occurrence_id),
        'permalink'  => get_permalink($parent_id ?: $occurrence_id),
        'excerpt'    => mindevents_plain_text(mindevents_get_occurrence_excerpt($occurrence_id)),
        'image'      => get_the_post_thumbnail_url($parent_id ?: $occurrence_id, 'large') ?: '',
        'start'      => mindevents_iso8601(get_post_meta($occurrence_id, 'mindevents_start_utc', true)),
        'end'        => mindevents_iso8601(get_post_meta($occurrence_id, 'mindevents_end_utc', true)),
        'location'   => mindevents_get_occurrence_location($occurrence_id),
        'organizer'  => mindevents_get_organizer_data($occurrence_id),
        'categories' => mindevents_get_occurrence_terms_payload($occurrence_id),
    );
}

function mindevents_rest_events(WP_REST_Request $request) {
    $per_page = min(100, max(1, (int) $request->get_param('per_page')));
    $page     = max(1, (int) $request->get_param('page'));
    $status   = $request->get_param('status') ?: 'upcoming';
    $after    = mindevents_rest_datetime_to_utc($request->get_param('after'));
    $before   = mindevents_rest_datetime_to_utc($request->get_param('before'));
    $orderby  = $request->get_param('orderby') ?: 'start_time';
    $order    = strtoupper((string) $request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';
    $search   = sanitize_text_field((string) $request->get_param('search'));
    $parent   = mindevents_rest_csv_to_ints($request->get_param('parent'));
    $include  = mindevents_rest_csv_to_ints($request->get_param('include'));
    $exclude  = mindevents_rest_csv_to_ints($request->get_param('exclude'));
    $cats     = sanitize_text_field((string) $request->get_param('categories'));

    $meta_query = array();

    if ($after) {
        $meta_query[] = array(
            'key'     => 'mindevents_start_utc',
            'value'   => $after,
            'compare' => '>=',
            'type'    => 'DATETIME',
        );
    } elseif ($status === 'upcoming') {
        $meta_query[] = array(
            'key'     => 'mindevents_start_utc',
            'value'   => mindevents_now_utc(),
            'compare' => '>=',
            'type'    => 'DATETIME',
        );
    } elseif ($status === 'past') {
        $meta_query[] = array(
            'key'     => 'mindevents_start_utc',
            'value'   => mindevents_now_utc(),
            'compare' => '<',
            'type'    => 'DATETIME',
        );
    }

    if ($before) {
        $meta_query[] = array(
            'key'     => 'mindevents_start_utc',
            'value'   => $before,
            'compare' => '<=',
            'type'    => 'DATETIME',
        );
    }

    $orderby_map = array(
        'start_time' => array('meta_key' => 'mindevents_start_utc', 'orderby' => 'meta_value'),
        'end_time'   => array('meta_key' => 'mindevents_end_utc', 'orderby' => 'meta_value'),
        'title'      => array('orderby' => 'title'),
        'date'       => array('orderby' => 'date'),
    );
    $orderby_config = $orderby_map[$orderby] ?? $orderby_map['start_time'];

    $parent_ids = $parent;
    if ($search !== '') {
        $search_parent_ids = array_map('intval', mindevents_rest_find_parent_ids($search));

        if (empty($search_parent_ids)) {
            return new WP_REST_Response(array(
                'events'      => array(),
                'total'       => 0,
                'total_pages' => 0,
                'page'        => $page,
                'per_page'    => $per_page,
            ), 200);
        }

        $parent_ids = empty($parent_ids)
            ? $search_parent_ids
            : array_values(array_intersect($parent_ids, $search_parent_ids));

        if (empty($parent_ids)) {
            return new WP_REST_Response(array(
                'events'      => array(),
                'total'       => 0,
                'total_pages' => 0,
                'page'        => $page,
                'per_page'    => $per_page,
            ), 200);
        }
    }

    $query_args = array(
        'post_type'        => 'mind_sub_event',
        'post_status'      => 'publish',
        'posts_per_page'   => $per_page,
        'paged'            => $page,
        'orderby'          => $orderby_config['orderby'],
        'order'            => $order,
        'meta_query'       => $meta_query,
        'suppress_filters' => true,
    );

    if (!empty($orderby_config['meta_key'])) {
        $query_args['meta_key']  = $orderby_config['meta_key'];
        $query_args['meta_type'] = 'DATETIME';
    }

    if ($include) {
        $query_args['post__in'] = $include;
        $query_args['orderby']  = 'post__in';
        unset($query_args['meta_key'], $query_args['meta_type']);
    }

    if ($exclude) {
        $query_args['post__not_in'] = $exclude;
    }

    if ($parent_ids) {
        $query_args['post_parent__in'] = array_map('absint', $parent_ids);
    }

    if ($cats !== '') {
        $cat_slugs = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $cats))));
        if ($cat_slugs) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy'         => 'event_category',
                    'field'            => 'slug',
                    'terms'            => $cat_slugs,
                    'include_children' => true,
                    'operator'         => 'IN',
                ),
            );
        }
    }

    $query  = new WP_Query($query_args);
    $events = array();

    foreach ($query->posts as $post) {
        $events[] = mindevents_rest_event_payload($post->ID);
    }

    return new WP_REST_Response(array(
        'events'      => $events,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'page'        => $page,
        'per_page'    => $per_page,
        'order'       => $order,
        'orderby'     => $orderby,
        'status'      => $status,
    ), 200);
}

