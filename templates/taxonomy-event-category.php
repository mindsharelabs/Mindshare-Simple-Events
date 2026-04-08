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
  $calendar = new mindEventCalendar();
  $show_all = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
  $calendar->set_past_events_display($show_all);
  $filters = function_exists('mindevents_get_frontend_filters') ? mindevents_get_frontend_filters() : array();
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
    if (function_exists('mindevents_get_frontend_filter_form')) {
      echo mindevents_get_frontend_filter_form($filters);
    }
    do_action(MINDEVENTS_PREPEND . 'archive_before_calendar_buttons');
    do_action(MINDEVENTS_PREPEND . 'archive_after_calendar_buttons');
    echo '<div id="publicCalendar" class="container">';
      echo $calendar->get_front_list();
    echo '</div>';

    $list_query = $calendar->get_last_front_list_query();
    if ($list_query instanceof WP_Query && $list_query->max_num_pages > 1) {
      $pagination_args = array();
      $pagination_base = get_pagenum_link(999999999);
      if (function_exists('mindevents_get_frontend_filter_query_args')) {
        $pagination_args = mindevents_get_frontend_filter_query_args($filters, array(), array('paged', 'calendar_date'));
        if (!empty($pagination_args)) {
          $pagination_base = remove_query_arg(array_keys($pagination_args), $pagination_base);
        }
      }

      echo '<nav class="mindevents-pagination" aria-label="Event pagination">';
        echo paginate_links(array(
          'base'      => str_replace(999999999, '%#%', esc_url($pagination_base)),
          'format'    => '',
          'current'   => max(1, get_query_var('paged') ?: ($filters['paged'] ?? 1)),
          'total'     => $list_query->max_num_pages,
          'prev_text' => __('&laquo; Previous', 'mindshare'),
          'next_text' => __('Next &raquo;', 'mindshare'),
          'add_args'  => $pagination_args,
        ));
      echo '</nav>';
    }
  echo '</div>';

  do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
