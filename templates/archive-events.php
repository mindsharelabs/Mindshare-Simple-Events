<?php
/**
 * The Template for displaying event archives.
 * This template can be overridden by copying it to yourtheme/archive-events.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action('mindevents_before_main_content');

echo '<main role="main" aria-label="Content">';
  do_action('mindevents_archive_loop_start');
  if(have_posts()) :
    while(have_posts()) :
      the_post();
      $calendar = new mindEventCalendar(get_the_ID());
      $show_all = apply_filters(MINDRETURNS_PREPEND . 'events_archive_show_past_events', true);
      $calendar->set_past_events_display($show_all);
      $sub_events = $calendar->get_sub_events();
      
      if($sub_events) :
        foreach ($sub_events as $key => $event) :
          $starttime = get_post_meta($event->ID, 'starttime', true);
          $endtime = get_post_meta($event->ID, 'endtime', true);
          $date = get_post_meta($event->ID, 'event_date', true);
          $color = get_post_meta($event->ID, 'eventColor', true);
          if(!$color){
            $color = '#858585';
          }
          $inside = '<span class="sub-event-toggle" data-eventid="' . $event->ID . '" style="background:' . $color .'" >' . $starttime . '</span>';
          $html = $calendar->get_daily_event_html($inside);
          $eventDates = $calendar->addDailyHtml($html, $date);
        endforeach;
      endif;
    endwhile;

    echo '<div id="archiveCalendar" class="calendar-wrap">';
      echo '<div class="calendar-nav">';
        echo '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
        echo '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
      echo '</div>';

      echo '<div id="publicCalendar">';
        echo $calendar->get_front_calendar();
      echo '</div>';
    echo '</div>';


  endif;
  do_action('mindevents_archive_loop_end');
echo '</main>';
do_action('mindevents_after_main_content');
get_footer('events');
