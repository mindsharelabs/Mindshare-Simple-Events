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
  $filters = function_exists('mindevents_get_frontend_filters') ? mindevents_get_frontend_filters() : array();
  $event_view = !empty($filters['event_view']) ? $filters['event_view'] : 'month';
  $initial_calendar_date = function_exists('mindevents_get_archive_initial_calendar_date') ? mindevents_get_archive_initial_calendar_date($filters) : null;
  $calendar = new mindEventCalendar('', $initial_calendar_date);
  $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
  $calendar->set_past_events_display($show_all);

  echo '<div id="archiveContainer" class="calendar-wrap">';
    echo '<div id="cartErrorContainer"></div>';
    if (function_exists('mindevents_get_frontend_filter_form')) {
      echo mindevents_get_frontend_filter_form($filters);
    }
    echo apply_filters(MINDEVENTS_PREPEND . 'calendar_label', '<h3 class="event-schedule">Event Schedule</h3>');
    do_action(MINDEVENTS_PREPEND . 'single_before_calendar', get_the_ID());
    echo '<div id="publicCalendar">';
      echo ($event_view === 'list') ? $calendar->get_front_list() : $calendar->get_front_calendar();
    echo '</div>';
    do_action(MINDEVENTS_PREPEND . 'single_after_calendar', get_the_ID());
  echo '</div>';

  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
