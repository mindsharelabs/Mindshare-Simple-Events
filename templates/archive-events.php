<?php
/**
 * The Template for displaying event archives.
 * This template can be overridden by copying it to yourtheme/archive-events.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action(MINDEVENTS_PREPEND . 'before_main_content');

echo '<main role="main" aria-label="Content">';
  do_action(MINDEVENTS_PREPEND . 'archive_loop_start');

    if(have_posts()) :
      $first_event = get_posts(array(
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'posts_per_page' => 1,
        'meta_query' => array(
            'key' => 'event_start_time_stamp', // Check the start date field
            'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
            'compare' => '>=', // Return the ones greater than today's date
            'type' => 'DATETIME' // Let
          ),
        )
      );
      if($first_event) :
        $first_event = $first_event[0];
        $first_event = get_post_meta($first_event->ID, 'event_start_time_stamp', true);
      else :
        $first_event = null;
      endif;

      $calendar = new mindEventCalendar('', $first_event);
      $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
      $calendar->set_past_events_display($show_all);


      echo '<div id="archiveContainer" class="calendar-wrap">';
        do_action(MINDEVENTS_PREPEND . 'archive_before_calendar_buttons');
        echo '<div class="calendar-nav">';
        echo '<button data-dir="prev" class="calnav prev"><span>&#8592;</span></button>';
        echo '<button data-dir="next" class="calnav next"><span>&#8594;</span></button>';
        echo '</div>';
        do_action(MINDEVENTS_PREPEND . 'archive_after_calendar_buttons');
        echo '<div id="publicCalendar">';
          echo $calendar->get_front_calendar('archive');
        echo '</div>';
      echo '</div>';



    endif;



  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
