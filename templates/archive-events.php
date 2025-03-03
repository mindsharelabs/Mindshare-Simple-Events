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
      echo '<div id="cartErrorContainer"></div>';
      mapi_write_log(MINDEVENTS_IS_MOBILE);
      if(MINDEVENTS_IS_MOBILE) :

        
        echo apply_filters(MINDEVENTS_PREPEND . 'list_label', '<h3>Occurences</h3>');
        do_action(MINDEVENTS_PREPEND . 'single_before_list', get_the_ID());
        echo '<div id="mindEventList" class="mindevents-list">';
          echo $calendar->get_front_list('archive');
        echo '</div>';
        do_action(MINDEVENTS_PREPEND . 'single_after_list', get_the_ID());


      else :
        echo apply_filters(MINDEVENTS_PREPEND . 'calendar_label', '<h3 class="event-schedule">Event Schedule</h3>');
        do_action(MINDEVENTS_PREPEND . 'single_before_calendar', get_the_ID());
        echo '<div class="calendar-nav">';
          echo '<button data-dir="prev" class="calnav prev"><span><i class="fas fa-arrow-left"></i></span></button>';
          echo '<button data-dir="next" class="calnav next"><span><i class="fas fa-arrow-right"></i></span></button>';
        echo '</div>';
        echo '<div id="publicCalendar">';
          echo $calendar->get_front_calendar('archive');
        echo '</div>';
        do_action(MINDEVENTS_PREPEND . 'single_after_calendar', get_the_ID());
      endif;
      echo '</div>';



    endif;



  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
