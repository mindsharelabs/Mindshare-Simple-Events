<?php
/**
 * The Template for displaying event categories.
 * This template can be overridden by copying it to yourtheme/taxonomy-event-category.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action(MINDEVENTS_PREPEND . 'before_main_content');

do_action(MINDEVENTS_PREPEND . 'archive_loop_start');

$filters               = function_exists('mindevents_get_frontend_filters') ? mindevents_get_frontend_filters() : array();
$event_view            = !empty($filters['event_view']) ? $filters['event_view'] : 'month';
$initial_calendar_date = function_exists('mindevents_get_archive_initial_calendar_date') ? mindevents_get_archive_initial_calendar_date($filters) : null;
$calendar              = new mindEventCalendar('', $initial_calendar_date);
$show_all              = apply_filters(MINDEVENTS_PREPEND . 'events_archive_show_past_events', true);
$queried               = get_queried_object();

$calendar->set_past_events_display($show_all);

echo '<main class="mindevents-shell mindevents-shell--archive" role="main" aria-label="' . esc_attr__('Content', 'simple-events') . '">';
echo '<header class="mindevents-archive-header">';
echo '<p class="mindevents-kicker">' . esc_html__('Category Archive', 'simple-events') . '</p>';

if ($queried instanceof WP_Term) {
    echo '<h1 class="mindevents-page-title">' . esc_html($queried->name) . '</h1>';
    if ($queried->description) {
        echo '<div class="mindevents-page-intro">' . wp_kses_post(wpautop($queried->description)) . '</div>';
    }
}

echo '</header>';
echo '<section id="archiveContainer" class="mindevents-surface mindevents-schedule-panel">';

if (function_exists('mindevents_get_frontend_filter_form')) {
    echo mindevents_get_frontend_filter_form($filters);
}

do_action(MINDEVENTS_PREPEND . 'archive_before_calendar_buttons');
do_action(MINDEVENTS_PREPEND . 'archive_after_calendar_buttons');

echo '<div id="publicCalendar" class="mindevents-calendar-region">';
if ($event_view === 'list') {
    echo $calendar->get_front_list();
    if (function_exists('mindevents_get_frontend_list_pagination')) {
        echo mindevents_get_frontend_list_pagination($calendar, $filters);
    }
} else {
    echo $calendar->get_front_calendar();
}
echo '</div>';

echo '</section>';
echo '</main>';

do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
