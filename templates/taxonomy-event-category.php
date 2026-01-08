<?php
/**
 * The Template for displaying event categories.
 * This template can be overridden by copying it to yourtheme/taxonomy-event-category.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action(MINDEVENTS_PREPEND . 'before_main_content');

echo '<main role="main" aria-label="Content">';
  do_action(MINDEVENTS_PREPEND . 'archive_loop_start');

    if(have_posts()) :
      $calendar = new mindEventCalendar();
      $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
      $calendar->set_past_events_display($show_all);

      $queried = get_queried_object();
      $calendar->setEventCategories($queried->slug);

      echo '<div class="event-category-header container">';
        echo '<div class="row">';
          echo '<div class="col-12">';
          //display category title
            echo '<h1 class="event-category-title text-center display-3">';
              echo $queried->name;
            echo '</h1>';

             //display category description
            if($queried->description) {
              echo '<div class="event-category-description">';
                echo $queried->description;
              echo '</div>';
            }




          echo '</div>';
        echo '</div>';
      echo '</div>';
      
     


      echo '<div id="archiveContainer" class="calendar-wrap">';
        do_action(MINDEVENTS_PREPEND . 'archive_before_calendar_buttons');
        do_action(MINDEVENTS_PREPEND . 'archive_after_calendar_buttons');
        echo '<div id="publicCalendar" class="container">';
          echo $calendar->get_front_list();
        echo '</div>';
      echo '</div>';

    endif;



  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
