<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('template_include', function($template) {
    if (is_post_type_archive('events')) {
        $theme_files = array('archive-events.php', 'templates/archive-events.php');
        $exists = locate_template($theme_files, false);

        return ($exists !== '') ? $exists : MINDEVENTS_ABSPATH . 'templates/archive-events.php';
    }

    if (is_singular('events')) {
        $theme_files = array('single-events.php', 'templates/single-events.php');
        $exists = locate_template($theme_files, false);

        return ($exists !== '') ? $exists : MINDEVENTS_ABSPATH . 'templates/single-events.php';
    }

    if (is_tax('event_category')) {
        $theme_files = array('taxonomy-event-category.php', 'templates/taxonomy-event-category.php');
        $exists = locate_template($theme_files, false);

        return ($exists !== '') ? $exists : MINDEVENTS_ABSPATH . 'templates/taxonomy-event-category.php';
    }

    return $template;
});

function mindevents_get_frontend_filter_context($context = null) {
    $allowed_contexts = array('events_archive', 'event_category_archive');
    if (is_string($context) && in_array($context, $allowed_contexts, true)) {
        return $context;
    }

    if (is_post_type_archive('events')) {
        return 'events_archive';
    }

    if (is_tax('event_category')) {
        return 'event_category_archive';
    }

    return '';
}

function mindevents_get_frontend_filter_timezone() {
    return mindevents_wp_timezone();
}

function mindevents_normalize_frontend_event_view($view) {
    $view = sanitize_key((string) $view);

    return in_array($view, array('month', 'week', 'list'), true) ? $view : 'month';
}

function mindevents_parse_frontend_filter_date($value) {
    $value = sanitize_text_field(wp_unslash((string) $value));
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, mindevents_get_frontend_filter_timezone());
    if (!($date instanceof DateTimeImmutable) || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $date;
}

function mindevents_expand_event_category_ids($term_ids) {
    $expanded_ids = array();

    foreach ((array) $term_ids as $term_id) {
        $term_id = absint($term_id);
        if (!$term_id) {
            continue;
        }

        $expanded_ids[] = $term_id;
        $children = get_term_children($term_id, 'event_category');
        if (is_wp_error($children) || empty($children)) {
            continue;
        }

        foreach ($children as $child_id) {
            $child_id = absint($child_id);
            if ($child_id) {
                $expanded_ids[] = $child_id;
            }
        }
    }

    $expanded_ids = array_values(array_unique(array_filter($expanded_ids)));
    sort($expanded_ids);

    return $expanded_ids;
}

function mindevents_find_parent_event_ids_by_title($search_term) {
    $search_term = sanitize_text_field((string) $search_term);
    if ($search_term === '') {
        return array();
    }

    $parent_query = get_posts(array(
        'post_type'      => 'events',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        's'              => $search_term,
    ));

    return array_map('intval', $parent_query);
}

function mindevents_get_frontend_filters($source = null, $context = null) {
    static $cached_filters = null;

    $use_cache = ($source === null && $context === null);
    if ($use_cache && is_array($cached_filters)) {
        return $cached_filters;
    }

    $context = mindevents_get_frontend_filter_context($context);
    $source  = is_array($source) ? $source : $_GET;

    $filters = array(
        'context'               => $context,
        'apply'                 => in_array($context, array('events_archive', 'event_category_archive'), true),
        'event_view'            => 'month',
        'calendar_date'         => '',
        'event_search'          => '',
        'paged'                 => 1,
        'selected_category_ids' => array(),
        'query_category_ids'    => array(),
        'title_parent_ids'      => array(),
        'force_empty'           => false,
        'has_user_filters'      => false,
        'scoped_term_id'        => 0,
    );

    if (!$filters['apply']) {
        if ($use_cache) {
            $cached_filters = $filters;
        }

        return $filters;
    }

    $calendar_date = mindevents_parse_frontend_filter_date($source['calendar_date'] ?? '');
    if ($calendar_date instanceof DateTimeImmutable) {
        $filters['calendar_date'] = $calendar_date->format('Y-m-d');
    }

    $filters['event_search'] = sanitize_text_field(wp_unslash((string) ($source['event_search'] ?? '')));
    $filters['event_view']   = mindevents_normalize_frontend_event_view($source['event_view'] ?? 'month');
    $filters['paged']        = max(1, absint($source['paged'] ?? 1));

    if ($context === 'events_archive') {
        $selected_category_ids = array();
        foreach ((array) ($source['event_category_filter'] ?? array()) as $term_id) {
            $term_id = absint($term_id);
            if ($term_id) {
                $selected_category_ids[] = $term_id;
            }
        }

        $filters['selected_category_ids'] = array_values(array_unique($selected_category_ids));
        $filters['query_category_ids']    = mindevents_expand_event_category_ids($filters['selected_category_ids']);
    } elseif ($context === 'event_category_archive') {
        $queried_term = get_queried_object();
        if ($queried_term instanceof WP_Term && $queried_term->taxonomy === 'event_category') {
            $filters['scoped_term_id']     = (int) $queried_term->term_id;
            $filters['query_category_ids'] = mindevents_expand_event_category_ids(array($queried_term->term_id));
        }
    }

    if ($filters['event_search'] !== '') {
        $filters['title_parent_ids'] = mindevents_find_parent_event_ids_by_title($filters['event_search']);
        if (empty($filters['title_parent_ids'])) {
            $filters['force_empty'] = true;
        }
    }

    $filters['has_user_filters'] = (!empty($filters['selected_category_ids']) || $filters['event_search'] !== '');

    if ($use_cache) {
        $cached_filters = $filters;
    }

    return $filters;
}

function mindevents_apply_frontend_filters_to_sub_event_query_args($args, $filters = null) {
    $filters = is_array($filters) ? $filters : mindevents_get_frontend_filters();
    if (empty($filters['apply'])) {
        return $args;
    }

    if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
        $args['meta_query'] = array();
    }

    if (!empty($filters['force_empty'])) {
        $args['post__in'] = array(0);
        return $args;
    }

    if (!empty($filters['query_category_ids'])) {
        $category_query = array(
            'taxonomy'         => 'event_category',
            'field'            => 'term_id',
            'terms'            => array_map('absint', $filters['query_category_ids']),
            'include_children' => false,
        );

        if (!empty($args['tax_query']) && is_array($args['tax_query'])) {
            if (!isset($args['tax_query']['relation'])) {
                $args['tax_query']['relation'] = 'AND';
            }
            $args['tax_query'][] = $category_query;
        } else {
            $args['tax_query'] = array($category_query);
        }
    }

    if (!empty($filters['title_parent_ids'])) {
        $parent_ids = array_map('absint', $filters['title_parent_ids']);

        if (!empty($args['post_parent'])) {
            if (!in_array((int) $args['post_parent'], $parent_ids, true)) {
                $args['post__in'] = array(0);
            }

            return $args;
        }

        if (!empty($args['post_parent__in']) && is_array($args['post_parent__in'])) {
            $parent_ids = array_values(array_intersect(array_map('absint', $args['post_parent__in']), $parent_ids));
            if (empty($parent_ids)) {
                $args['post__in'] = array(0);
                return $args;
            }
        }

        $args['post_parent__in'] = $parent_ids;
    }

    return $args;
}

function mindevents_get_frontend_filter_query_args($filters = null, $extra_args = array(), $remove_args = array()) {
    $filters = is_array($filters) ? $filters : mindevents_get_frontend_filters();
    $args    = array();

    if (!empty($filters['event_view'])) {
        $args['event_view'] = $filters['event_view'];
    }

    if ($filters['context'] === 'events_archive' && !empty($filters['selected_category_ids'])) {
        $args['event_category_filter'] = array_map('absint', $filters['selected_category_ids']);
    }

    if (!empty($filters['event_search'])) {
        $args['event_search'] = $filters['event_search'];
    }

    if (!empty($filters['calendar_date'])) {
        $args['calendar_date'] = $filters['calendar_date'];
    }

    if ($filters['context'] === 'event_category_archive' && !empty($filters['paged']) && $filters['paged'] > 1) {
        $args['paged'] = $filters['paged'];
    }

    foreach ((array) $remove_args as $remove_arg) {
        unset($args[$remove_arg]);
    }

    foreach ((array) $extra_args as $key => $value) {
        if ($value === null || $value === '' || $value === false || (is_array($value) && empty($value))) {
            unset($args[$key]);
            continue;
        }

        $args[$key] = $value;
    }

    return $args;
}

function mindevents_get_archive_initial_calendar_date($filters = null) {
    $filters = is_array($filters) ? $filters : mindevents_get_frontend_filters();
    $selected_view = !empty($filters['event_view']) ? mindevents_normalize_frontend_event_view($filters['event_view']) : 'month';

    if (!empty($filters['calendar_date'])) {
        return $filters['calendar_date'];
    }

    $fallback = new DateTimeImmutable(current_time('mysql'), mindevents_get_frontend_filter_timezone());

    if (!empty($filters['force_empty'])) {
        return ($selected_view === 'week')
            ? $fallback->format('Y-m-d')
            : $fallback->modify('first day of this month')->format('Y-m-d');
    }

    $query_args = array(
        'post_type'        => 'sub_event',
        'posts_per_page'   => 1,
        'orderby'          => 'meta_value',
        'meta_key'         => 'event_start_time_stamp',
        'meta_type'        => 'DATETIME',
        'order'            => 'ASC',
        'suppress_filters' => true,
        'meta_query'       => array(
            array(
                'key'     => 'event_start_time_stamp',
                'value'   => current_time('mysql'),
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
            mindevents_public_visibility_meta_query(),
        ),
    );

    $query_args = mindevents_apply_frontend_filters_to_sub_event_query_args($query_args, $filters);

    $upcoming = get_posts($query_args);

    if (!empty($upcoming[0])) {
        $start = get_post_meta($upcoming[0]->ID, 'event_start_time_stamp', true);
        if ($start) {
            $date = new DateTimeImmutable($start, mindevents_get_frontend_filter_timezone());

            return ($selected_view === 'week')
                ? $date->format('Y-m-d')
                : $date->modify('first day of this month')->format('Y-m-d');
        }
    }

    return ($selected_view === 'week')
        ? $fallback->format('Y-m-d')
        : $fallback->modify('first day of this month')->format('Y-m-d');
}

function mindevents_get_category_filter_summary($categories, $selected_ids) {
    $selected_ids = array_map('absint', (array) $selected_ids);
    if (empty($selected_ids)) {
        return __('Categories', 'simple-events');
    }

    $selected_names = array();
    foreach ((array) $categories as $category) {
        if (in_array((int) $category->term_id, $selected_ids, true)) {
            $selected_names[] = $category->name;
        }
    }

    if (!$selected_names) {
        return __('Categories', 'simple-events');
    }

    if (count($selected_names) === 1) {
        return $selected_names[0];
    }

    return sprintf(_n('%d Category', '%d Categories', count($selected_names), 'simple-events'), count($selected_names));
}

function mindevents_get_frontend_filter_form($filters = null) {
    $filters = is_array($filters) ? $filters : mindevents_get_frontend_filters();
    if (empty($filters['apply'])) {
        return '';
    }

    $is_events_archive = ($filters['context'] === 'events_archive');
    $action_url        = $is_events_archive ? get_post_type_archive_link('events') : get_term_link(get_queried_object());

    if (is_wp_error($action_url)) {
        return '';
    }

    $categories = array();
    if ($is_events_archive) {
        $categories = get_terms(array(
            'taxonomy'   => 'event_category',
            'hide_empty' => false,
            'parent'     => 0,
        ));
    }

    $panel_id = function_exists('wp_unique_id') ? wp_unique_id('mindevents-filter-panel-') : uniqid('mindevents-filter-panel-');
    $classes  = array('mindevents-event-filters');

    if (!empty($filters['has_user_filters'])) {
        $classes[] = 'is-open';
        $classes[] = 'has-active-filters';
    }

    $reset_args = mindevents_get_frontend_filter_query_args($filters, array(), array('event_search', 'event_category_filter', 'paged'));

    ob_start();
    ?>
    <form method="get" action="<?php echo esc_url($action_url); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <div class="mindevents-filter-toolbar">
            <div class="mindevents-view-toggle-group" role="tablist" aria-label="<?php esc_attr_e('Event views', 'simple-events'); ?>">
                <?php foreach (array('month' => __('Month', 'simple-events'), 'week' => __('Week', 'simple-events'), 'list' => __('List', 'simple-events')) as $view_key => $label) : ?>
                    <?php
                    $view_classes = array('mindevents-view-toggle');
                    if ($filters['event_view'] === $view_key) {
                        $view_classes[] = 'is-active';
                    }
                    $view_args = mindevents_get_frontend_filter_query_args($filters, array('event_view' => $view_key), array('paged'));
                    ?>
                    <a href="<?php echo esc_url(add_query_arg($view_args, $action_url)); ?>" class="<?php echo esc_attr(implode(' ', $view_classes)); ?>" role="tab" aria-selected="<?php echo ($filters['event_view'] === $view_key) ? 'true' : 'false'; ?>">
                        <span><?php echo esc_html($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <button type="button" class="mindevents-filter-toggle" aria-expanded="<?php echo !empty($filters['has_user_filters']) ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($panel_id); ?>">
                <span><?php esc_html_e('Filters', 'simple-events'); ?></span>
                <span class="mindevents-chevron" aria-hidden="true">▾</span>
            </button>
        </div>

        <div id="<?php echo esc_attr($panel_id); ?>" class="mindevents-filter-panel"<?php echo !empty($filters['has_user_filters']) ? '' : ' hidden'; ?>>
            <div class="mindevents-filter-row">
                <?php if ($is_events_archive && !empty($categories) && !is_wp_error($categories)) : ?>
                    <div class="mindevents-multiselect" data-default-label="<?php echo esc_attr__('Categories', 'simple-events'); ?>">
                        <button type="button" class="mindevents-multiselect-toggle" aria-expanded="false">
                            <span class="mindevents-multiselect-label"><?php echo esc_html(mindevents_get_category_filter_summary($categories, $filters['selected_category_ids'])); ?></span>
                            <span class="mindevents-chevron" aria-hidden="true">▾</span>
                        </button>
                        <div class="mindevents-multiselect-menu">
                            <?php foreach ($categories as $category) : ?>
                                <label class="mindevents-filter-checkbox">
                                    <input type="checkbox" name="event_category_filter[]" value="<?php echo esc_attr($category->term_id); ?>"<?php checked(in_array((int) $category->term_id, $filters['selected_category_ids'], true)); ?>>
                                    <span class="mindevents-filter-checkbox-text"><?php echo esc_html($category->name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <label class="mindevents-filter-pill mindevents-filter-pill-search">
                    <span class="screen-reader-text"><?php esc_html_e('Search events', 'simple-events'); ?></span>
                    <input type="search" name="event_search" value="<?php echo esc_attr($filters['event_search']); ?>" placeholder="<?php esc_attr_e('Search event title', 'simple-events'); ?>" aria-label="<?php esc_attr_e('Search event title', 'simple-events'); ?>">
                </label>

                <div class="mindevents-filter-actions">
                    <button type="submit" class="mindevents-filter-submit"><?php esc_html_e('Apply', 'simple-events'); ?></button>
                    <a class="mindevents-filter-reset" href="<?php echo esc_url(add_query_arg($reset_args, $action_url)); ?>"><?php esc_html_e('Reset', 'simple-events'); ?></a>
                </div>
            </div>
        </div>

        <input type="hidden" name="event_view" value="<?php echo esc_attr($filters['event_view']); ?>">
        <?php if (!empty($filters['calendar_date'])) : ?>
            <input type="hidden" name="calendar_date" value="<?php echo esc_attr($filters['calendar_date']); ?>">
        <?php endif; ?>
    </form>
    <?php

    return ob_get_clean();
}

function mindevents_get_frontend_list_pagination($calendar, $filters = null) {
    if (!($calendar instanceof mindEventCalendar)) {
        return '';
    }

    $query = $calendar->get_last_front_list_query();
    if (!($query instanceof WP_Query) || $query->max_num_pages < 2) {
        return '';
    }

    $filters    = is_array($filters) ? $filters : mindevents_get_frontend_filters();
    $current    = max(1, absint($filters['paged'] ?? 1));
    $base_url   = is_tax('event_category') ? get_term_link(get_queried_object()) : get_post_type_archive_link('events');

    if (is_wp_error($base_url) || !$base_url) {
        return '';
    }

    $base = add_query_arg(mindevents_get_frontend_filter_query_args($filters, array('paged' => '__PAGE__')), $base_url);
    $base = str_replace('__PAGE__', '%#%', $base);

    $links = paginate_links(array(
        'base'      => $base,
        'format'    => '',
        'current'   => $current,
        'total'     => max(1, (int) $query->max_num_pages),
        'type'      => 'list',
        'prev_text' => __('Previous', 'simple-events'),
        'next_text' => __('Next', 'simple-events'),
    ));

    if (!$links) {
        return '';
    }

    return '<nav class="mindevents-pagination" aria-label="' . esc_attr__('Event list pagination', 'simple-events') . '">' . $links . '</nav>';
}

add_filter('mindevents_front_calendar_query_args', function($args) {
    return mindevents_apply_frontend_filters_to_sub_event_query_args($args);
}, 10, 1);

add_filter('mindevents_front_list_query_args', function($args) {
    return mindevents_apply_frontend_filters_to_sub_event_query_args($args);
}, 10, 1);

add_action(MINDEVENTS_PREPEND . 'single_title', function($id) {
    echo '<h1 class="mindevents-single-title">' . esc_html(get_the_title($id)) . '</h1>';
}, 10, 1);

add_action(MINDEVENTS_PREPEND . 'single_title', function($id) {
    $now = current_time('mysql');
    $sub_events = new WP_Query(array(
        'post_type'      => 'sub_event',
        'post_parent'    => $id,
        'posts_per_page' => 1,
        'orderby'        => 'meta_value',
        'meta_key'       => 'event_start_time_stamp',
        'meta_type'      => 'DATETIME',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => 'event_start_time_stamp',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
            mindevents_public_visibility_meta_query(),
        ),
    ));

    if ($sub_events->have_posts()) {
        $sub_events->the_post();
        $next_event = get_post();
        $next_date  = get_post_meta($next_event->ID, 'event_start_time_stamp', true);
        $start_date = new DateTimeImmutable($next_date, mindevents_wp_timezone());
        echo '<p class="mindevents-single-subtitle">' . esc_html(sprintf(__('Next occurrence: %s', 'simple-events'), $start_date->format(get_option('date_format') ?: 'F j, Y'))) . '</p>';
        wp_reset_postdata();
        return;
    }

    $first_event = get_post_meta($id, 'first_event_date', true);
    if ($first_event) {
        $start_date = new DateTimeImmutable($first_event, mindevents_wp_timezone());
        echo '<p class="mindevents-single-subtitle">' . esc_html($start_date->format(get_option('date_format') ?: 'F j, Y')) . '</p>';
    }
}, 20, 1);

add_action(MINDEVENTS_PREPEND . 'single_thumb', function($id) {
    if (!has_post_thumbnail($id)) {
        return;
    }

    echo '<div class="mindevents-single-media">';
    echo get_the_post_thumbnail($id, 'large', array('class' => 'mindevents-single-image'));
    echo '</div>';
}, 10, 1);

add_action(MINDEVENTS_PREPEND . 'single_content', function($id) {
    $excerpt = get_the_excerpt($id);
    if ($excerpt) {
        echo '<div class="mindevents-single-excerpt">' . wp_kses_post(wpautop($excerpt)) . '</div>';
    }
}, 10, 1);

add_action(MINDEVENTS_PREPEND . 'single_before_events', function() {
    echo '<div class="mindevents-single-content">';
    the_content();
    echo '</div>';
}, 10);

add_action(MINDEVENTS_PREPEND . 'single_after_calendar', function() {
    $url = home_url('/events-feed.ics');
    $webcal = preg_replace('#^https?://#i', 'webcal://', $url);
    echo '<div class="mindevents-subscribe-link">';
    echo '<a class="mindevents-button mindevents-button--secondary" href="' . esc_url($webcal) . '">' . esc_html__('Subscribe to Calendar Feed', 'simple-events') . '</a>';
    echo '</div>';
});

add_filter('query_vars', function($vars) {
    $vars[] = 'calendar_feed';
    $vars[] = 'event_ics_id';
    return $vars;
});

add_action('init', function() {
    add_rewrite_rule('^events-feed\.ics$', 'index.php?calendar_feed=1', 'top');
    add_rewrite_rule('^event-ics/([0-9]+)/?', 'index.php?event_ics_id=$matches[1]', 'top');
    add_rewrite_tag('%event_ics_id%', '([0-9]+)');
});

add_action('template_redirect', function() {
    if (get_query_var('calendar_feed')) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="events.ics"');
        echo mindevents_generate_ics_feed();
        exit;
    }

    $event_ics_id = absint(get_query_var('event_ics_id'));
    if ($event_ics_id && !mindevents_is_public_occurrence($event_ics_id)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }

    if ($event_ics_id) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-' . $event_ics_id . '.ics"');
        echo mindevents_generate_single_event_ics($event_ics_id);
        exit;
    }
});

function mindevents_get_ics_uid_domain() {
    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    return $host ? $host : 'example.com';
}

function mindevents_build_ics_event_block($occurrence_id) {
    $occurrence_id = absint($occurrence_id);
    if (!mindevents_is_public_occurrence($occurrence_id)) {
        return '';
    }

    $start = get_post_meta($occurrence_id, 'event_start_time_stamp', true);
    $end   = get_post_meta($occurrence_id, 'event_end_time_stamp', true);
    if (!$start || !$end) {
        return '';
    }

    $timezone = mindevents_wp_timezone();
    $start_dt = new DateTimeImmutable($start, $timezone);
    $end_dt   = new DateTimeImmutable($end, $timezone);
    $title    = mindevents_get_plain_title(wp_get_post_parent_id($occurrence_id));
    $summary  = mindevents_plain_text(mindevents_get_occurrence_excerpt($occurrence_id));
    $location = mindevents_get_occurrence_location($occurrence_id);
    $domain   = mindevents_get_ics_uid_domain();

    $block  = "BEGIN:VEVENT\r\n";
    $block .= 'UID:event-' . $occurrence_id . '@' . $domain . "\r\n";
    $block .= 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
    $block .= 'DTSTART:' . $start_dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n";
    $block .= 'DTEND:' . $end_dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . "\r\n";
    $block .= 'SUMMARY:' . str_replace(array("\r", "\n"), ' ', $title) . "\r\n";
    $block .= 'DESCRIPTION:' . str_replace(array("\r", "\n"), ' ', $summary) . "\r\n";
    if ($location !== '') {
        $block .= 'LOCATION:' . str_replace(array("\r", "\n"), ' ', $location) . "\r\n";
    }
    $block .= "END:VEVENT\r\n";

    return $block;
}

function mindevents_generate_ics_feed() {
    $events = get_posts(array(
        'post_type'      => 'sub_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => 'event_start_time_stamp',
        'meta_type'      => 'DATETIME',
        'order'          => 'ASC',
        'suppress_filters' => true,
        'meta_query'     => array(
            array(
                'key'     => 'event_start_time_stamp',
                'value'   => current_time('mysql'),
                'compare' => '>=',
                'type'    => 'DATETIME',
            ),
            mindevents_public_visibility_meta_query(),
        ),
    ));

    $output  = "BEGIN:VCALENDAR\r\n";
    $output .= "VERSION:2.0\r\n";
    $output .= 'PRODID:' . mindevents_get_site_prod_id() . "\r\n";

    foreach ($events as $event) {
        $output .= mindevents_build_ics_event_block($event->ID);
    }

    $output .= "END:VCALENDAR\r\n";

    return $output;
}

function mindevents_generate_single_event_ics($event_id) {
    $output  = "BEGIN:VCALENDAR\r\n";
    $output .= "VERSION:2.0\r\n";
    $output .= 'PRODID:' . mindevents_get_site_prod_id() . "\r\n";
    $output .= mindevents_build_ics_event_block($event_id);
    $output .= "END:VCALENDAR\r\n";

    return $output;
}

function mindevents_get_event_add_to_calendar_links($event_id) {
    $event_id = absint($event_id);
    if (!mindevents_is_public_occurrence($event_id)) {
        return '';
    }

    $start = get_post_meta($event_id, 'event_start_time_stamp', true);
    $end   = get_post_meta($event_id, 'event_end_time_stamp', true);
    if (!$start || !$end) {
        return '';
    }

    $title       = mindevents_get_plain_title(wp_get_post_parent_id($event_id));
    $description = mindevents_plain_text(mindevents_get_occurrence_excerpt($event_id));
    $location    = mindevents_get_occurrence_location($event_id);
    $timezone    = mindevents_wp_timezone();
    $start_dt    = new DateTimeImmutable($start, $timezone);
    $end_dt      = new DateTimeImmutable($end, $timezone);
    $ics_url     = home_url('/event-ics/' . $event_id . '/');

    // add_query_arg() does not encode values; an & or # in a title would
    // otherwise cut the URL short.
    $gcal_url = add_query_arg(array_map('rawurlencode', array(
        'action'   => 'TEMPLATE',
        'text'     => $title,
        'dates'    => $start_dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z') . '/' . $end_dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
        'details'  => $description,
        'location' => $location,
        'output'   => 'xml',
    )), 'https://calendar.google.com/calendar/render');

    $yahoo_url = add_query_arg(array_map('rawurlencode', array(
        'v'      => 60,
        'view'   => 'd',
        'type'   => '20',
        'title'  => $title,
        'st'     => gmdate('Ymd\THi\Z', strtotime($start)),
        'et'     => gmdate('Ymd\THi\Z', strtotime($end)),
        'desc'   => $description,
        'in_loc' => $location,
    )), 'https://calendar.yahoo.com/');

    ob_start();
    ?>
    <div class="add-to-calendar-dropdown">
        <button type="button" class="add-to-calendar-button mindevents-button mindevents-button--ghost" aria-expanded="false">
            <span><?php esc_html_e('Add to Calendar', 'simple-events'); ?></span>
            <span aria-hidden="true">▾</span>
        </button>
        <ul class="add-to-calendar-menu">
            <li><a href="<?php echo esc_url($gcal_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Google Calendar', 'simple-events'); ?></a></li>
            <li><a href="<?php echo esc_url($ics_url); ?>"><?php esc_html_e('Apple / Outlook (.ics)', 'simple-events'); ?></a></li>
            <li><a href="<?php echo esc_url($yahoo_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Yahoo Calendar', 'simple-events'); ?></a></li>
        </ul>
    </div>
    <?php

    return ob_get_clean();
}
