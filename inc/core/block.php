<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Events Calendar block: a list, calendar or mini calendar on any
 * page, of every event, the events in chosen categories, or one event.
 *
 * It is rendered on the server by the same code as the event pages. The
 * editor script in blocks/calendar/ only provides the settings and a
 * preview.
 */
class mindEventsBlock {
    const DISPLAYS = array('calendar', 'list', 'mini');

    public function __construct() {
        add_action('init', array($this, 'register'));
    }

    public function register() {
        register_block_type(MINDEVENTS_ABSPATH . 'blocks/calendar', array(
            'render_callback' => array($this, 'render'),
        ));
    }

    public function render($attributes) {
        $display  = in_array($attributes['display'] ?? '', self::DISPLAYS, true) ? $attributes['display'] : 'calendar';
        $event_id = absint($attributes['event'] ?? 0);
        $args     = array();

        if ($event_id) {
            if (get_post_type($event_id) !== 'mind_events' || get_post_status($event_id) !== 'publish') {
                return '';
            }

            $calendar = new mindEventCalendar($event_id);
            // One event follows its own Past Events setting.
            $show_past = (get_post_meta($event_id, 'show_past_events', true) === '1');
        } else {
            $calendar = new mindEventCalendar();
            // A month grid shows its whole month; lists show what is to come.
            $show_past = ($display === 'calendar');

            $category_ids = array_filter(array_map('absint', (array) ($attributes['categories'] ?? array())));
            if ($category_ids) {
                $args['post_parent__in'] = $this->events_in_categories($category_ids);
            }
        }

        $calendar->set_past_events_display($show_past);

        if (isset($args['post_parent__in']) && !$args['post_parent__in']) {
            $html = '<p class="mindevents-notice">' . esc_html__('There are no upcoming events.', 'simple-events') . '</p>';
        } elseif ($display === 'mini') {
            $html = $calendar->get_mini_calendar($args, true);
        } elseif ($display === 'list') {
            if (!$show_past) {
                $args['meta_query'] = array(
                    array(
                        'key'     => 'mindevents_end_utc',
                        'value'   => mindevents_now_utc(),
                        'compare' => '>',
                        'type'    => 'DATETIME',
                    ),
                );
            }
            $html = $calendar->get_front_list('', $args);
        } else {
            $html = $calendar->get_front_calendar($args);
        }

        $wrapper = get_block_wrapper_attributes(array('class' => 'mindevents-shell mindevents-shell--block'));

        return '<div ' . $wrapper . '><div class="mindevents-calendar-region">' . $html . '</div></div>';
    }

    /**
     * Filtering on the events rather than on their dates, so a date always
     * follows its event's current categories.
     */
    private function events_in_categories(array $category_ids) {
        return get_posts(array(
            'post_type'      => 'mind_events',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'mind_event_category',
                    'terms'    => $category_ids,
                ),
            ),
        ));
    }
}
