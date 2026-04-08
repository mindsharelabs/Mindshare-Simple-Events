<?php
/**
 * The Template for displaying a single event.
 * This template can be overridden by copying it to yourtheme/single-events.php.
 */
defined( 'ABSPATH' ) || exit;

get_header('events');
do_action(MINDEVENTS_PREPEND . 'before_main_content', get_the_ID());

echo '<main class="mindevents-shell mindevents-shell--single" role="main" aria-label="Content">';
do_action(MINDEVENTS_PREPEND . 'single_page_start');

if (have_posts()) :
    while (have_posts()) :
        the_post();

        do_action(MINDEVENTS_PREPEND . 'before_single_container');

        $calendar     = new mindEventCalendar(get_the_ID());
        $display_type = get_post_meta(get_the_ID(), 'cal_display', true) ?: 'calendar';
        $show_all     = get_post_meta(get_the_ID(), 'show_past_events', true);

        $calendar->set_past_events_display($show_all);

        echo '<article id="singleEventContainer" class="mindevents-single">';
        echo '<header class="mindevents-single-header">';
        do_action(MINDEVENTS_PREPEND . 'single_title', get_the_ID());
        echo '</header>';

        echo '<section class="mindevents-single-layout">';
        echo '<div class="mindevents-single-summary">';
        do_action(MINDEVENTS_PREPEND . 'single_thumb', get_the_ID());
        do_action(MINDEVENTS_PREPEND . 'single_content', get_the_ID());
        echo '</div>';

        echo '<div class="mindevents-surface mindevents-single-details">';
        do_action(MINDEVENTS_PREPEND . 'single_before_events', get_the_ID());
        echo '</div>';
        echo '</section>';

        echo '<section class="mindevents-surface mindevents-single-schedule">';
        if ($display_type === 'list') {
            echo apply_filters(MINDEVENTS_PREPEND . 'list_label', '<h2 class="mindevents-section-heading">' . esc_html__('Occurrences', 'simple-events') . '</h2>');
            do_action(MINDEVENTS_PREPEND . 'single_before_list', get_the_ID());
            echo '<div id="mindEventList" class="mindevents-calendar-region">';
            echo $calendar->get_front_list();
            echo '</div>';
            do_action(MINDEVENTS_PREPEND . 'single_after_list', get_the_ID());
        } else {
            echo apply_filters(MINDEVENTS_PREPEND . 'calendar_label', '<h2 class="mindevents-section-heading">' . esc_html__('Event Schedule', 'simple-events') . '</h2>');
            do_action(MINDEVENTS_PREPEND . 'single_before_calendar', get_the_ID());
            echo '<div id="publicCalendar" class="mindevents-calendar-region">';
            echo $calendar->get_front_calendar();
            echo '</div>';
            do_action(MINDEVENTS_PREPEND . 'single_after_calendar', get_the_ID());
        }
        echo '</section>';

        do_action(MINDEVENTS_PREPEND . 'single_after_events', get_the_ID());
        echo '</article>';

        do_action(MINDEVENTS_PREPEND . 'after_single_container', get_the_ID());
    endwhile;
endif;

do_action(MINDEVENTS_PREPEND . 'single_page_end', get_the_ID());
echo '</main>';
do_action(MINDEVENTS_PREPEND . 'after_main_content', get_the_ID());
get_footer('events');
