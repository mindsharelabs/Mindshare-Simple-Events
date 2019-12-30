<?php
/**
 * The Template for displaying a single event.
 * This template can be overridden by copying it to yourtheme/single-events.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action('mindevents_before_main_content');

echo '<main role="main" aria-label="Content">';
  do_action('mindevents_single_page_start');

    if(have_posts()) :
      while(have_posts()) :
        the_post();

        do_action('mindevents_before_single_container');
        echo '<div id="singleEventContainer" class="event-wrap">';
          $calendar = new mindEventCalendar(get_the_ID());
          $display_type = get_post_meta(get_the_ID(), 'cal_display', true);
          $show_all = get_post_meta(get_the_ID(), 'show_past_events', true);
          $calendar->set_past_events_display($show_all);

          $first_event = new DateTime(get_post_meta(get_the_ID(), 'first_event_date', true));
          $startdate = apply_filters('mindevents_start_date', $first_event->format('F j, Y'), $first_event);

          $last_event = new DateTime(get_post_meta(get_the_ID(), 'last_event_date', true));
          $enddate = apply_filters('mindevents_end_date', $last_event->format('F j, Y'), $last_event);

          echo '<div class="event-title-container">';
            echo '<h1>' . get_the_title() . '</h1>';
          echo '</div>';


          echo '<div class="event-datespan">';
            echo apply_filters('mindevents_single_datespan', '<span class="start-date">' . $startdate . '</span> - <span class="end-date">' . $enddate . '</span>', $startdate, $enddate);
          echo '</div>';


          echo '<div class="content-wrap">';
            do_action('mindevents_single_before_content');
              the_content();
            do_action('mindevents_single_after_content');
          echo '</div>';


          echo '<div class="events-wrap">';
            if($display_type == 'calendar') :
              echo apply_filters('mindevents_calednar_label', '<h3>Calendar</h3>');
              do_action('mindevents_single_before_calendar');
              echo '<div class="calendar-nav">';
                echo '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
                echo '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
              echo '</div>';

              echo '<div id="publicCalendar">';
                echo $calendar->get_front_calendar();
              echo '</div>';
              do_action('mindevents_single_after_calendar');
            elseif('list') :
              echo apply_filters('mindevents_list_label', '<h3>Events</h3>');
              do_action('mindevents_single_before_list');
              echo '<div id="mindEventList" class="mindevents-list">';
                echo $calendar->get_front_list();
              echo '</div>';
              do_action('mindevents_single_after_list');
            endif;
          echo '</div>';
        echo '</div>';
        do_action('mindevents_after_single_container');
      endwhile;
    endif;

    //content



  do_action('mindevents_single_page_end');
echo '</main>';
do_action('mindevents_after_main_content');
get_footer('events');
