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
  $filters = function_exists('mindevents_get_frontend_filters') ? mindevents_get_frontend_filters() : array();
  $event_view = !empty($filters['event_view']) ? $filters['event_view'] : 'month';
  $initial_calendar_date = function_exists('mindevents_get_archive_initial_calendar_date') ? mindevents_get_archive_initial_calendar_date($filters) : null;
  $calendar = new mindEventCalendar('', $initial_calendar_date);
  $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
  $calendar->set_past_events_display($show_all);
  $queried = get_queried_object();

  if ($queried instanceof WP_Term) :
    echo '<div class="event-category-header container">';
      echo '<div class="row">';
        echo '<div class="col-12">';
          echo '<h1 class="event-category-title text-center display-3">';
            echo esc_html($queried->name);
          echo '</h1>';

          if ($queried->description) {
            echo '<div class="event-category-description">';
              echo wp_kses_post($queried->description);
            echo '</div>';
          }
        echo '</div>';
      echo '</div>';
    echo '</div>';
  endif;

  echo '<div id="archiveContainer" class="calendar-wrap">';
    echo '<div id="cartErrorContainer"></div>';
    if (function_exists('mindevents_get_frontend_filter_form')) {
      echo mindevents_get_frontend_filter_form($filters);
    }
    do_action(MINDEVENTS_PREPEND . 'archive_before_calendar_buttons');
    do_action(MINDEVENTS_PREPEND . 'archive_after_calendar_buttons');
    echo '<div id="publicCalendar" class="container">';
      echo ($event_view === 'list') ? $calendar->get_front_list() : $calendar->get_front_calendar();
    echo '</div>';
  echo '</div>';

  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
