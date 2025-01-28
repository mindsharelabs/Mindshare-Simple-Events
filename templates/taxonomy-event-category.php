<?php
/**
 * The Template for displaying event categories.
 * This template can be overridden by copying it to yourtheme/taxonomy-event-category.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action('mindevents_before_main_content');

echo '<main role="main" aria-label="Content">';
  do_action('mindevents_archive_loop_start');

    if(have_posts()) :
      $calendar = new mindEventCalendar();
      $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
      $calendar->set_past_events_display($show_all);

      $queried = get_queried_object();
      $calendar->setEventCategories($queried->slug);

      echo '<div id="archiveContainer" class="calendar-wrap">';
        do_action('mindevents_archive_before_calendar_buttons');
        echo '<div class="calendar-nav">';
          echo '<button data-dir="prev" data-cat="' . $queried->slug . '" class="calnav prev"><span>&#8592;</span></button>';
          echo '<button data-dir="next" data-cat="' . $queried->slug . '" class="calnav next"><span>&#8594;</span></button>';
        echo '</div>';
        do_action('mindevents_archive_after_calendar_buttons');
        echo '<div id="publicCalendar">';
          echo $calendar->get_front_calendar('archive');
        echo '</div>';
      echo '</div>';

    endif;



  do_action('mindevents_archive_loop_end');
echo '</main>';
do_action('mindevents_after_main_content');
get_footer('events');
