<?php
/**
 * The Template for displaying event archives.
 * This template can be overridden by copying it to yourtheme/archive-events.php.
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

$calendar->set_past_events_display($show_all);

echo '<main class="mindevents-shell mindevents-shell--archive" role="main" aria-label="' . esc_attr__('Content', 'simple-events') . '">';
echo '<header class="mindevents-archive-header">';
    echo '<h1 class="mindevents-page-title">' . esc_html(post_type_archive_title('', false)) . '</h1>';
echo '</header>';

echo '<section id="archiveContainer" class="mindevents-surface mindevents-schedule-panel">';
if (function_exists('mindevents_get_frontend_filter_form')) {
    echo mindevents_get_frontend_filter_form($filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
}

echo '<div class="mindevents-section-heading-wrap">';
echo wp_kses_post(apply_filters(MINDEVENTS_PREPEND . 'calendar_label', '<h2 class="mindevents-section-heading">' . esc_html__('Event Schedule', 'simple-events') . '</h2>'));
echo '</div>';

do_action(MINDEVENTS_PREPEND . 'single_before_calendar', get_the_ID());

echo '<div id="publicCalendar" class="mindevents-calendar-region">';
if ($event_view === 'list') {
    echo $calendar->get_front_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
    if (function_exists('mindevents_get_frontend_list_pagination')) {
        echo mindevents_get_frontend_list_pagination($calendar, $filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
    }
} else {
    echo $calendar->get_front_calendar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it is built.
}
echo '</div>';

do_action(MINDEVENTS_PREPEND . 'single_after_calendar', get_the_ID());
echo '</section>';
echo '</main>';

do_action(MINDEVENTS_PREPEND . 'archive_loop_end');
do_action(MINDEVENTS_PREPEND . 'after_main_content');
get_footer('events');
