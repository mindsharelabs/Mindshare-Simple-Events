<?php
/**
 * The Template for displaying a single event.
 * This template can be overridden by copying it to yourtheme/single-events.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action(MINDEVENTS_PREPEND . 'before_main_content', get_the_ID());

echo '<main role="main" aria-label="Content">';
  do_action(MINDEVENTS_PREPEND . 'single_page_start');

    if(have_posts()) :
      while(have_posts()) :
        the_post();

        do_action(MINDEVENTS_PREPEND . 'before_single_container');

        echo '<div id="singleEventContainer" class="event-wrap">';
          $calendar = new mindEventCalendar(get_the_ID());
          $display_type = get_post_meta(get_the_ID(), 'cal_display', true);
          $show_all = get_post_meta(get_the_ID(), 'show_past_events', true);
          $calendar->set_past_events_display($show_all);

          echo '<div class="event-title-container">';
            //@hooked mind_events_single_title - 10
            //@hooked mindevents_single_datespan - 20
            do_action(MINDEVENTS_PREPEND . 'single_title', get_the_ID());
          echo '</div>';


          echo '<section class="content">';

            //@hooked mindevents_thumbnail - 10
            do_action(MINDEVENTS_PREPEND . 'single_thumb', get_the_ID());


            //@hooked mindevents_content - 10
            do_action(MINDEVENTS_PREPEND . 'single_content', get_the_ID());

          echo '</section>';


          do_action(MINDEVENTS_PREPEND . 'single_before_events', get_the_ID());
          
          echo '<div class="events-wrap">';
            echo '<div id="cartErrorContainer"></div>';
            if($display_type == 'calendar') :
              echo apply_filters(MINDEVENTS_PREPEND . 'calednar_label', '<h3 class="event-schedule">Event Schedule</h3>');
              do_action(MINDEVENTS_PREPEND . 'single_before_calendar', get_the_ID());
              echo '<div class="calendar-nav">';
                echo '<button data-dir="prev" class="calnav prev"><span><i class="fas fa-arrow-left"></i></span></button>';
                echo '<button data-dir="next" class="calnav next"><span><i class="fas fa-arrow-right"></i></span></button>';
              echo '</div>';
              echo '<div id="publicCalendar">';
                echo $calendar->get_front_calendar();
              echo '</div>';
              do_action(MINDEVENTS_PREPEND . 'single_after_calendar', get_the_ID());

            elseif($display_type == 'list') :
              echo apply_filters(MINDEVENTS_PREPEND . 'list_label', '<h3>Events</h3>');
              do_action(MINDEVENTS_PREPEND . 'single_before_list', get_the_ID());
              echo '<div id="mindEventList" class="mindevents-list">';
                echo $calendar->get_front_list();
              echo '</div>';
              do_action(MINDEVENTS_PREPEND . 'single_after_list', get_the_ID());
            endif;
          echo '</div>';

          do_action(MINDEVENTS_PREPEND . 'single_after_events', get_the_ID());


        echo '</div>';

        do_action(MINDEVENTS_PREPEND . 'after_single_container', get_the_ID());

      endwhile;
    endif;

    //content



  do_action(MINDEVENTS_PREPEND . 'single_page_end', get_the_ID());
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content', get_the_ID());
get_footer('events');
