<?php

add_filter('template_include', function ( $template ) {
  if ( is_post_type_archive('events') ) {
    $theme_files = array('archive-events.php', 'templates/archive-events.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/archive-events.php';
    }
  }
  if ( is_singular('events') ) {
    $theme_files = array('single-events.php', 'templates/single-events.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/single-events.php';
    }
  }

  if ( is_tax( 'event_category') ) {
    $theme_files = array('taxonomy-event-category.php', 'templates/taxonomy-event-category.php');
    $exists_in_theme = locate_template($theme_files, false);
    if ( $exists_in_theme != '' ) {
      return $exists_in_theme;
    } else {
      return MINDEVENTS_ABSPATH . 'templates/taxonomy-event-category.php';
    }
  }
  return $template;
});

function mindevents_get_frontend_filter_context($context = null) {
  $allowed_contexts = array('events_archive', 'event_category_archive');
  if (is_string($context) && in_array($context, $allowed_contexts, true)) {
    return $context;
  }

  if (function_exists('is_post_type_archive') && is_post_type_archive('events')) {
    return 'events_archive';
  }

  if (function_exists('is_tax') && is_tax('event_category')) {
    return 'event_category_archive';
  }

  return '';
}

function mindevents_get_frontend_filter_timezone() {
  if (function_exists('wp_timezone')) {
    return wp_timezone();
  }

  return new DateTimeZone(get_option('timezone_string') ?: 'UTC');
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
  global $wpdb;

  $search_term = sanitize_text_field((string) $search_term);
  if ($search_term === '') {
    return array();
  }

  $like = '%' . $wpdb->esc_like($search_term) . '%';
  $sql  = $wpdb->prepare(
    "
      SELECT ID
      FROM {$wpdb->posts}
      WHERE post_type = %s
        AND post_status = %s
        AND post_title LIKE %s
      ORDER BY post_title ASC
    ",
    'events',
    'publish',
    $like
  );

  return array_map('intval', $wpdb->get_col($sql));
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
    'context'             => $context,
    'apply'               => in_array($context, array('events_archive', 'event_category_archive'), true),
    'event_view'          => 'month',
    'calendar_date'       => '',
    'event_search'        => '',
    'paged'               => 1,
    'selected_category_ids' => array(),
    'query_category_ids'  => array(),
    'title_parent_ids'    => array(),
    'force_empty'         => false,
    'has_user_filters'    => false,
    'scoped_term_id'      => 0,
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
    $raw_category_ids      = $source['event_category_filter'] ?? array();
    foreach ((array) $raw_category_ids as $term_id) {
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

  $filters['has_user_filters'] = (
    !empty($filters['selected_category_ids']) ||
    $filters['event_search'] !== ''
  );

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
    ),
  );
  $query_args = mindevents_apply_frontend_filters_to_sub_event_query_args($query_args, $filters);
  $events     = get_posts($query_args);

  if (!empty($events[0])) {
    $event_start = get_post_meta($events[0]->ID, 'event_start_time_stamp', true);
    $event_date  = mindevents_parse_frontend_filter_date(substr((string) $event_start, 0, 10));
    if ($event_date instanceof DateTimeImmutable) {
      return ($selected_view === 'week')
        ? $event_date->format('Y-m-d')
        : $event_date->modify('first day of this month')->format('Y-m-d');
    }
  }

  return ($selected_view === 'week')
    ? $fallback->format('Y-m-d')
    : $fallback->modify('first day of this month')->format('Y-m-d');
}

function mindevents_get_category_filter_summary($categories, $selected_ids) {
  $selected_ids = array_map('absint', (array) $selected_ids);
  $selected_ids = array_values(array_filter(array_unique($selected_ids)));

  if (empty($selected_ids)) {
    return 'Categories';
  }

  $selected_names = array();
  foreach ((array) $categories as $category) {
    if ($category instanceof WP_Term && in_array((int) $category->term_id, $selected_ids, true)) {
      $selected_names[] = $category->name;
    }
  }

  if (count($selected_names) === 1) {
    return $selected_names[0];
  }

  return count($selected_ids) . ' Categories';
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
  $form_classes = array('mindevents-event-filters', 'mb-4');
  if (!empty($filters['has_user_filters'])) {
    $form_classes[] = 'is-open';
    $form_classes[] = 'has-active-filters';
  }

  $reset_args = function_exists('mindevents_get_frontend_filter_query_args')
    ? mindevents_get_frontend_filter_query_args($filters, array(), array('event_search', 'event_category_filter', 'paged'))
    : array();

  ob_start();
  echo '<form method="get" action="' . esc_url($action_url) . '" class="' . esc_attr(implode(' ', $form_classes)) . '">';
    echo '<div class="mindevents-filter-toolbar">';
      echo '<div class="mindevents-view-toggle-group" role="tablist" aria-label="Event views">';
      foreach (array(
        'month' => array('label' => 'Month', 'icon' => 'fa-calendar-days'),
        'week'  => array('label' => 'Week', 'icon' => 'fa-calendar-week'),
        'list'  => array('label' => 'List', 'icon' => 'fa-list-ul'),
      ) as $view_key => $view_meta) {
        $view_classes = array('mindevents-view-toggle');
        if ($filters['event_view'] === $view_key) {
          $view_classes[] = 'is-active';
        }
        $view_args = function_exists('mindevents_get_frontend_filter_query_args')
          ? mindevents_get_frontend_filter_query_args($filters, array('event_view' => $view_key), array('paged'))
          : array('event_view' => $view_key);

        echo '<a href="' . esc_url(add_query_arg($view_args, $action_url)) . '" class="' . esc_attr(implode(' ', $view_classes)) . '" role="tab" aria-selected="' . ($filters['event_view'] === $view_key ? 'true' : 'false') . '">';
          echo '<i class="fas ' . esc_attr($view_meta['icon']) . '" aria-hidden="true"></i>';
          echo '<span>' . esc_html($view_meta['label']) . '</span>';
        echo '</a>';
      }
      echo '</div>';

      echo '<button type="button" class="mindevents-filter-toggle" aria-expanded="' . (!empty($filters['has_user_filters']) ? 'true' : 'false') . '" aria-controls="' . esc_attr($panel_id) . '">';
        echo '<i class="fas fa-filter" aria-hidden="true"></i>';
        echo '<span class="mindevents-filter-toggle-text">Filters</span>';
        echo '<i class="fas fa-chevron-down mindevents-chevron" aria-hidden="true"></i>';
      echo '</button>';
    echo '</div>';

    echo '<div id="' . esc_attr($panel_id) . '" class="mindevents-filter-panel"' . (!empty($filters['has_user_filters']) ? '' : ' hidden') . '>';
      echo '<div class="mindevents-filter-row">';
        if ($is_events_archive && !empty($categories) && !is_wp_error($categories)) {
          echo '<div class="mindevents-multiselect" data-default-label="Categories">';
            echo '<button type="button" class="mindevents-multiselect-toggle" aria-expanded="false">';
              echo '<i class="fas fa-tags" aria-hidden="true"></i>';
              echo '<span class="mindevents-multiselect-label">' . esc_html(mindevents_get_category_filter_summary($categories, $filters['selected_category_ids'])) . '</span>';
              echo '<i class="fas fa-chevron-down mindevents-chevron" aria-hidden="true"></i>';
            echo '</button>';
            echo '<div class="mindevents-multiselect-menu">';
            foreach ($categories as $category) {
              $checked = in_array((int) $category->term_id, $filters['selected_category_ids'], true) ? ' checked' : '';
              echo '<label class="mindevents-filter-checkbox">';
                echo '<input type="checkbox" name="event_category_filter[]" value="' . esc_attr($category->term_id) . '"' . $checked . '>';
                echo '<span class="mindevents-filter-checkbox-text">' . esc_html($category->name) . '</span>';
              echo '</label>';
            }
            echo '</div>';
          echo '</div>';
        }

        echo '<label class="mindevents-filter-pill mindevents-filter-pill-search">';
          echo '<i class="fas fa-search" aria-hidden="true"></i>';
          echo '<input type="search" id="mindevents-event-search" name="event_search" value="' . esc_attr($filters['event_search']) . '" placeholder="Search class title" aria-label="Search class title">';
        echo '</label>';

        echo '<div class="mindevents-filter-actions">';
          echo '<button type="submit" class="mindevents-filter-submit">';
            echo '<i class="fas fa-check" aria-hidden="true"></i>';
            echo '<span>Apply</span>';
          echo '</button>';
          echo '<a class="mindevents-filter-reset" href="' . esc_url(add_query_arg($reset_args, $action_url)) . '">';
            echo '<i class="fas fa-undo" aria-hidden="true"></i>';
            echo '<span>Reset</span>';
          echo '</a>';
        echo '</div>';
      echo '</div>';
    echo '</div>';

    echo '<input type="hidden" name="event_view" value="' . esc_attr($filters['event_view']) . '">';

    if (!empty($filters['calendar_date'])) {
      echo '<input type="hidden" name="calendar_date" value="' . esc_attr($filters['calendar_date']) . '">';
    }
  echo '</form>';

  return ob_get_clean();
}

add_filter('mindevents_front_calendar_query_args', function($args) {
  return mindevents_apply_frontend_filters_to_sub_event_query_args($args);
}, 10, 1);

add_filter('mindevents_front_list_query_args', function($args) {
  return mindevents_apply_frontend_filters_to_sub_event_query_args($args);
}, 10, 1);


add_action(MINDEVENTS_PREPEND . 'single_title', 'mind_events_single_title', 10, 1);
function mind_events_single_title($id) {
    echo '<h1 class="event-title">' . get_the_title($id) . '</h1>';
}

add_action(MINDEVENTS_PREPEND . 'single_title', MINDEVENTS_PREPEND . 'single_datespan', 20, 1);
function mindevents_single_datespan($id) {
    // Query sub_events for this parent event
    $now = current_time('Y-m-d H:i:s');
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
                'type'    => 'DATETIME'
            ),
        ),
    ));

    if ($sub_events->have_posts()) {
        $sub_events->the_post();
        $next_event = get_post();
        $next_event_date = get_post_meta($next_event->ID, 'event_start_time_stamp', true);
        $next_event_date_obj = new DateTime($next_event_date);
        $startdate = $next_event_date_obj->format('F j, Y');
        
        echo '<div class="event-datespan">';
          echo '<span class="start-date">';
            echo apply_filters(MINDEVENTS_PREPEND . 'single_datespan', __('Next Occurrence: ', 'mindshare') . $startdate, $startdate, '');
          echo '</span>';
        echo '</div>';
        wp_reset_postdata();
    } else {
        // fallback to first_event_date if no upcoming sub_event
        $first_event = new DateTime(get_post_meta($id, 'first_event_date', true));
        $startdate = $first_event->format('F j, Y');
        echo '<div class="event-datespan">';
          echo apply_filters(MINDEVENTS_PREPEND . 'single_datespan', '<span class="start-date">' . $startdate . '</span>', $startdate, '');
        echo '</div>';
    }
}


//
add_action(MINDEVENTS_PREPEND . 'single_content', function ($id) {
  $excerpt = get_the_excerpt($id);
  if($excerpt) :
    echo '<div class="content-wrap excerpt">';
      echo $excerpt;
    echo '</div>';
  endif;
}, 10, 1);



add_action( MINDEVENTS_PREPEND . 'single_before_events', function() {
  echo '<div class="content">';
    the_content();
  echo '</div>';
}, 1);



add_filter('query_vars', function($vars) {
    $vars[] = 'calendar_feed';
    return $vars;
});



add_action('init', function () {
    add_rewrite_rule('^events-feed\.ics$', 'index.php?calendar_feed=1', 'top');
    add_rewrite_rule('^event-ics/([0-9]+)/?', 'index.php?event_ics_id=$matches[1]', 'top');
    add_rewrite_tag('%event_ics_id%', '([0-9]+)');
});




add_action(MINDEVENTS_PREPEND . 'single_after_calendar', function() {
  $str = home_url('/events-feed.ics');
  $str = preg_replace('#^https?://#i', 'webcal://', $str);
  echo '<div class="row my-1">';
    echo '<div class="col-12 text-end">';
      echo '<a href="' . esc_url($str) . '" class="btn btn-sm btn-info">Subscribe to Calendar</a>';
    echo '</div>';
  echo '</div>';
});



add_action('template_redirect', function() {
    if (get_query_var('calendar_feed')) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="events.ics"');

        echo make_generate_ics_feed(); // Write this function
        exit;
    } elseif (get_query_var('event_ics_id')) {
        $event_id = get_query_var('event_ics_id');
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-' . $event_id . '.ics"');

        echo make_generate_single_event_ics($event_id);
        exit;
    }
});


function make_generate_ics_feed() {
  $events = new WP_Query(array(
     'meta_query' => array(
       'relation' => 'AND',
       'start_clause' => array(
         'key' => 'starttime',
         'compare' => 'EXISTS',
       ),
       'date_clause' => array(
         'key' => 'event_start_time_stamp',
         'compare' => 'EXISTS',
       ),
       array(
       'key' => 'event_start_time_stamp', // Check the start date field
       'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
       'compare' => '>=', // Return the ones greater than today's date
       'type' => 'DATETIME' // Let WordPress know we're working with date
     )
     ),
     'orderby' => 'meta_value',
     'meta_key' => 'event_start_time_stamp',
     'meta_type' => 'DATETIME',
     'order'            => 'ASC',
     'post_type'        => 'sub_event',
     'suppress_filters' => true,
     'posts_per_page'   => -1
    ));


  $output = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Make Santa Fe//Events Calendar//EN\r\n";
  if($events->have_posts()) {
    while ($events->have_posts()) {
      $events->the_post();
      $start = get_post_meta(get_the_id(), 'event_start_time_stamp', true); // must be ISO 8601 or timestamp
      $end = get_post_meta(get_the_id(), 'event_end_time_stamp', true);
      // Convert from America/Denver to UTC for ICS
      $tz = new DateTimeZone('America/Denver');
      $start_dt = new DateTime($start, $tz);
      $end_dt = new DateTime($end, $tz);
      $start_dt->setTimezone(new DateTimeZone('UTC'));
      $end_dt->setTimezone(new DateTimeZone('UTC'));

      $dtstart = $start_dt->format('Ymd\THis\Z');
      $dtend   = $end_dt->format('Ymd\THis\Z');
      
        $output .= "BEGIN:VEVENT\r\n";
        $output .= "UID:" . get_the_id() . "@makesantafe.org\r\n";
        $output .= "DTSTAMP:" . gmdate('Ymd\THis\Z', strtotime(get_the_date())) . "\r\n";
        $output .= "DTSTART:" . $dtstart . "\r\n";
        $output .= "DTEND:" . $dtend . "\r\n";
        $output .= "SUMMARY:" . esc_html(get_the_title(get_post_parent(get_the_ID()))) . "\r\n";
        $output .= "DESCRIPTION:" . get_the_excerpt(get_post_parent(get_the_ID())). "\r\n";
        $output .= "LOCATION:" . esc_html(get_post_meta(get_the_ID(), 'event_location', true)) . "\r\n";
        $output .= "END:VEVENT\r\n";
    }
  }

  $output .= "END:VCALENDAR\r\n";
  mapi_write_log($output);
  return $output;
}



function make_generate_single_event_ics($event_id) {
    $event = get_post($event_id);

    if (!$event) return '';

    $start = get_post_meta($event_id, 'event_start_time_stamp', true); // must be ISO 8601 or timestamp
    $end = get_post_meta($event_id, 'event_end_time_stamp', true);
    $location = get_post_meta($event_id, 'event_location', true);
    $description = get_the_excerpt(get_post_parent($event_id));

    // Convert from America/Denver to UTC for ICS
    $tz = new DateTimeZone('America/Denver');
    $start_dt = new DateTime($start, $tz);
    $end_dt = new DateTime($end, $tz);
    $start_dt->setTimezone(new DateTimeZone('UTC'));
    $end_dt->setTimezone(new DateTimeZone('UTC'));

    $dtstart = $start_dt->format('Ymd\THis\Z');
    $dtend   = $end_dt->format('Ymd\THis\Z');
    $dtstamp = gmdate('Ymd\THis\Z');

    return "BEGIN:VCALENDAR\r\n" .
           "VERSION:2.0\r\n" .
           "PRODID:-//Make Santa Fe//EN\r\n" .
           "BEGIN:VEVENT\r\n" .
           "UID:event-$event_id@makesantafe.org\r\n" .
           "DTSTAMP:$dtstamp\r\n" .
           "DTSTART:$dtstart\r\n" .
           "DTEND:$dtend\r\n" .
           "SUMMARY:" . esc_html(get_the_title(get_post_parent($event_id))) . "\r\n" .
           "DESCRIPTION:" . esc_html(strip_tags($description)) . "\r\n" .
           "LOCATION:" . esc_html($location) . "\r\n" .
           "END:VEVENT\r\n" .
           "END:VCALENDAR\r\n";
}





function make_get_event_add_to_calendar_links($event_id) {
    $event = get_post($event_id);
    if (!$event) return '';

    $start = get_post_meta($event_id, 'event_start_time_stamp', true);
    $end = get_post_meta($event_id, 'event_end_time_stamp', true);
    $location = (get_post_meta($event_id, 'event_location', true) ? get_post_meta($event_id, 'event_location', true) : ''); //TODO: Add location support
    $description = get_the_excerpt(get_post_parent($event_id));
    $title = $event->post_parent ? get_the_title($event->post_parent) : $event->post_title;

    // Convert local time to UTC for Google Calendar
    $timezone = new DateTimeZone('America/Denver'); // <-- set your local timezone
    $start_dt = new DateTime($start, $timezone);
    $end_dt = new DateTime($end, $timezone);
    $start_dt->setTimezone(new DateTimeZone('UTC'));
    $end_dt->setTimezone(new DateTimeZone('UTC'));
    $start_gcal = $start_dt->format('Ymd\THis\Z');
    $end_gcal   = $end_dt->format('Ymd\THis\Z');

    $ics_url = home_url('/event-ics/' . $event_id . '/');

    $gcal_url = add_query_arg([
        'action' => 'TEMPLATE',
        'text' => $title,
        'dates' => $start_gcal . '/' . $end_gcal,
        'details' => $description,
        'location' => $location,
        'sf' => 'true',
        'output' => 'xml',
    ], 'https://calendar.google.com/calendar/render');

    $yahoo_url = add_query_arg([
        'v' => 60,
        'view' => 'd',
        'type' => '20',
        'title' => $title,
        'st' => gmdate('Ymd\THi\Z', strtotime($start)),
        'et' => gmdate('Ymd\THi\Z', strtotime($end)),
        'desc' => $description,
        'in_loc' => $location,
    ], 'https://calendar.yahoo.com/');


    ob_start();
    $html = '<div class="add-to-calendar-dropdown mt-0">';
        $html .= '<button class="add-to-calendar-button btn btn-sm btn-light text-nowrap">Add to Calendar <i class="fas fa-angle-down"></i></button>';
        $html .= '<ul class="add-to-calendar-menu">';
            $html .= '<li><a href="' . esc_url($gcal_url) . '" target="_blank" rel="noopener">Google Calendar</a></li>';
            $html .= '<li><a href="' . esc_url($ics_url) . '">Apple / Outlook (.ics)</a></li>';
            $html .= '<li><a href="' . esc_url($yahoo_url) . '" target="_blank" rel="noopener">Yahoo Calendar</a></li>';
        $html .= '</ul>';
    $html .= '</div>';
    return $html;
}
