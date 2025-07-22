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
        'meta_key'       => 'event_time_stamp',
        'meta_type'      => 'DATETIME',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => 'event_time_stamp',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME'
            ),
        ),
    ));

    if ($sub_events->have_posts()) {
        $sub_events->the_post();
        $next_event = get_post();
        $next_event_date = get_post_meta($next_event->ID, 'event_date', true);
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
         'key' => 'event_date',
         'compare' => 'EXISTS',
       ),
       array(
       'key' => 'event_time_stamp', // Check the start date field
       'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
       'compare' => '>=', // Return the ones greater than today's date
       'type' => 'DATETIME' // Let WordPress know we're working with date
     )
     ),
     'orderby' => 'meta_value',
     'meta_key' => 'event_time_stamp',
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
      
        $output .= "BEGIN:VEVENT\r\n";
        $output .= "UID:" . uniqid() . "@makesantafe.org\r\n";
        $output .= "DTSTAMP:" . gmdate('Ymd\THis\Z', strtotime(get_the_date())) . "\r\n";
        $output .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime(get_post_meta(get_the_ID(), 'event_start_time_stamp', true))) . "\r\n";
        $output .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime(get_post_meta(get_the_ID(), 'event_end_time_stamp', true))) . "\r\n";
        $output .= "SUMMARY:" . esc_html(get_the_title(get_post_parent(get_the_ID()))) . "\r\n";
        $output .= "DESCRIPTION:" . get_the_excerpt(get_post_parent(get_the_ID())). "\r\n";
        $output .= "LOCATION:" . esc_html(get_post_meta(get_the_ID(), 'event_location', true)) . "\r\n";
        $output .= "END:VEVENT\r\n";
    }
  }

  $output .= "END:VCALENDAR\r\n";
  return $output;
}



function make_generate_single_event_ics($event_id) {
    $event = get_post($event_id);

    if (!$event) return '';

    $start = get_post_meta($event_id, 'event_start_time_stamp', true); // must be ISO 8601 or timestamp
    $end = get_post_meta($event_id, 'event_end_time_stamp', true);
    $location = get_post_meta($event_id, 'event_location', true);
    $description = get_the_excerpt(get_post_parent($event_id));

    $dtstart = gmdate('Ymd\THis\Z', strtotime($start));
    $dtend   = gmdate('Ymd\THis\Z', strtotime($end));
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
    $html = '<div class="add-to-calendar-dropdown">';
        $html .= '<button class="add-to-calendar-button btn btn-sm btn-light">Add to Calendar <i class="fas fa-angle-down"></i></button>';
        $html .= '<ul class="add-to-calendar-menu">';
            $html .= '<li><a href="' . esc_url($gcal_url) . '" target="_blank" rel="noopener">Google Calendar</a></li>';
            $html .= '<li><a href="' . esc_url($ics_url) . '">Apple / Outlook (.ics)</a></li>';
            $html .= '<li><a href="' . esc_url($yahoo_url) . '" target="_blank" rel="noopener">Yahoo Calendar</a></li>';
        $html .= '</ul>';
    $html .= '</div>';
    return $html;
}