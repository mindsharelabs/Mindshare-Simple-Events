<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindEventCalendar {
    const MINUTES_PER_DAY = 1440;

    /** Shortest occurrence that still renders as a clickable block. */
    const MIN_EVENT_MINUTES = 30;

    /** Breathing room on each side of a week view occurrence. */
    const WEEK_EVENT_GUTTER = '0.15rem';
    const WEEK_EVENT_GUTTER_TOTAL = '0.3rem';

    private $eventID = '';
    private $calendar_start_day = 'Monday';
    private $show_past_events = true;
    private $today = null;
    private $now = null;
    private $date_format = 'F j, Y';
    private $time_format = 'g:i a';
    private $dailyHtml = array();
    private $offset = 0;
    private $last_front_list_query = null;
    private $classes = array(
        'calendar'     => 'mindevents-calendar',
        'leading_day'  => 'mindevents-calendar-day mindevents-calendar-day--empty',
        'trailing_day' => 'mindevents-calendar-day mindevents-calendar-day--empty',
        'today'        => 'is-today',
        'event'        => 'mindevents-calendar-event',
        'events'       => 'mindevents-calendar-day-events',
        'past'         => 'is-past',
    );

    public function __construct($id = '', $calendarDate = null, $today = null) {
        $this->eventID = $id;
        $this->setToday($today);

        // Only a strict Y-m-d from the URL; anything else is ignored.
        if (isset($_GET['calendar_date'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only view state.
            $url_date = mindevents_parse_frontend_filter_date(sanitize_text_field(wp_unslash($_GET['calendar_date']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only view state.
            if ($url_date) {
                $calendarDate = $url_date;
            }
        }

        if ($calendarDate) {
            $this->setDate($calendarDate);
        } else {
            // An event's own calendar opens on its first date.
            $this->setDate($id ? mindevents_from_utc(get_post_meta($id, 'mindevents_first_start_utc', true)) : null);
        }

        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());

        $this->calendar_start_day = $options[MINDEVENTS_PREPEND . 'start_day'] ?? 'Monday';
        $this->date_format        = get_option('date_format') ?: 'F j, Y';
        $this->time_format        = get_option('time_format') ?: 'g:i a';
    }

    public function setDate($date = null) {
        $this->now = $this->parseDate($date) ?: new DateTimeImmutable('now', wp_timezone());
    }

    public function getDate() {
        return $this->now;
    }

    public function setToday($today = null) {
        if ($today === false) {
            $this->today = null;
            return;
        }

        if ($today === null || $today === true) {
            $this->today = new DateTimeImmutable('now', wp_timezone());
            return;
        }

        $this->today = $this->parseDate($today);
    }

    public function set_past_events_display($display) {
        if (is_string($display)) {
            $display = ($display === '1');
        }

        $this->show_past_events = ($display !== false);
    }

    /**
     * Add HTML to every day an occurrence covers, in the site timezone.
     *
     * An occurrence that ends exactly at midnight does not spill onto the
     * next day.
     */
    public function addDailyHtml($html, DateTimeImmutable $start, ?DateTimeImmutable $end = null) {
        $end      = ($end && $end > $start) ? $end : $start;
        $last_day = $end->setTime(0, 0, 0);

        if ($end > $start && $end == $last_day) {
            $last_day = $last_day->modify('-1 day');
        }

        for ($day = $start->setTime(0, 0, 0); $day <= $last_day; $day = $day->modify('+1 day')) {
            $this->dailyHtml[(int) $day->format('Y')][(int) $day->format('n')][(int) $day->format('j')][] = $html;
        }
    }

    public function clearDailyHtml() {
        $this->dailyHtml = array();
    }

    public function setStartOfWeek($offset) {
        if (is_int($offset)) {
            $this->offset = $offset % 7;
            return;
        }

        $weekTime = strtotime((string) $offset);
        if ($weekTime === false) {
            throw new InvalidArgumentException('invalid offset');
        }

        $this->offset = (int) date('N', $weekTime) % 7;
    }

    public function render() {
        // Month and list views only. The week view renders from positioned
        // occurrence data, not HTML buckets; it exposes
        // 'mindevents_calendar_week_events' instead.
        $this->dailyHtml = apply_filters('mindevents_calendar_daily_html', $this->dailyHtml, $this);

        $this->setStartOfWeek($this->calendar_start_day);

        $referenceDate = $this->get_calendar_reference_date();
        $year          = (int) $referenceDate->format('Y');
        $month         = (int) $referenceDate->format('n');
        $daysInMonth   = (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $daysOfWeek = $this->weekdays();
        $this->rotate($daysOfWeek, $this->offset);

        $firstDayDate = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $referenceDate->getTimezone());
        $weekDayIndex = (((int) $firstDayDate->format('w')) - $this->offset + 7) % 7;

        $out = '';

        if (!is_admin()) {
            $out .= $this->get_calendar_nav_links();
        }

        $out .= '<div class="mindevents-calendar-wrap">';
        $out .= '<h2 class="mindevents-calendar-title">' . esc_html(wp_date('F Y', $referenceDate->getTimestamp())) . '</h2>';
        $out .= '<div id="mindEventCalendar" class="' . esc_attr($this->classes['calendar']) . '" data-month="' . esc_attr($month) . '" data-year="' . esc_attr($year) . '" data-view="month">';
        $out .= '<div class="mindevents-calendar-weekday-row">';
        foreach ($daysOfWeek as $dayName) {
            $out .= '<div class="mindevents-calendar-weekday">' . esc_html($dayName) . '</div>';
        }
        $out .= '</div>';

        $count = 0;
        $out .= '<div class="mindevents-calendar-week">';

        for ($i = 0; $i < $weekDayIndex; $i++) {
            $out .= '<div class="' . esc_attr($this->classes['leading_day']) . '"></div>';
            $count++;
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date    = $firstDayDate->setDate($year, $month, $day);
            $classes = array('mindevents-calendar-day');

            if ($this->is_today($date)) {
                $classes[] = $this->classes['today'];
            }

            if ($this->is_past_day($date)) {
                $classes[] = $this->classes['past'];
            }

            $out .= '<div class="' . esc_attr(implode(' ', $classes)) . '" data-date="' . esc_attr($date->format('Y-m-d')) . '">';
            $out .= '<button type="button" class="mindevents-calendar-day-number" datetime="' . esc_attr($date->format('Y-m-d')) . '">' . esc_html((string) $day) . '</button>';

            if (isset($this->dailyHtml[$year][$month][$day])) {
                $out .= '<div class="' . esc_attr($this->classes['events']) . '">';
                foreach ($this->dailyHtml[$year][$month][$day] as $dHtml) {
                    $out .= $dHtml;
                }
                $out .= '</div>';
            }

            $out .= '</div>';
            $count++;

            if ($count % 7 === 0 && $day !== $daysInMonth) {
                $out .= '</div><div class="mindevents-calendar-week">';
            }
        }

        while ($count % 7 !== 0) {
            $out .= '<div class="' . esc_attr($this->classes['trailing_day']) . '"></div>';
            $count++;
        }

        $out .= '</div></div></div>';

        return $out;
    }

    public function renderWeek($events = array()) {
        $this->setStartOfWeek($this->calendar_start_day);

        $period    = $this->get_visible_period('week');
        $weekStart = $period['start'];
        $weekEnd   = $period['end'];
        $daysOfWeek = $this->weekdays();
        $weekDates  = array();
        $eventsByDay = $this->build_week_events_by_day($events, $weekStart, $weekEnd);

        $this->rotate($daysOfWeek, $this->offset);

        $out = '';
        if (!is_admin()) {
            $out .= $this->get_calendar_nav_links('week');
        }

        $out .= '<div class="mindevents-calendar-wrap">';
        $out .= '<h2 class="mindevents-calendar-title">' . esc_html($this->get_week_display_label($weekStart, $weekEnd)) . '</h2>';
        $out .= '<div id="mindEventCalendar" class="' . esc_attr($this->classes['calendar']) . ' mindevents-calendar--week" data-month="' . esc_attr($weekStart->format('n')) . '" data-year="' . esc_attr($weekStart->format('Y')) . '" data-view="week">';
        $out .= '<div class="mindevents-weekly-grid">';
        $out .= '<div class="mindevents-weekly-corner" aria-hidden="true"></div>';

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $weekStart->modify('+' . $dayOffset . ' days');
            $weekDates[] = $date;
            $headerClasses = array('mindevents-weekly-header');

            if ($this->is_today($date)) {
                $headerClasses[] = $this->classes['today'];
            }

            $out .= '<div class="' . esc_attr(implode(' ', $headerClasses)) . '">';
            $out .= '<span class="mindevents-weekly-header-day">' . esc_html($daysOfWeek[$dayOffset]) . '</span>';
            $out .= '<span class="mindevents-weekly-header-date">' . esc_html(wp_date('M j', $date->getTimestamp())) . '</span>';
            $out .= '</div>';
        }

        $out .= '<div class="mindevents-weekly-axis">';
        for ($hour = 0; $hour < 24; $hour++) {
            $top = ($hour / 24) * 100;
            $out .= '<span class="mindevents-weekly-axis-label" style="top:' . esc_attr(number_format((float) $top, 4, '.', '')) . '%">' . esc_html($this->format_hour_label($hour)) . '</span>';
        }
        $out .= '<span class="mindevents-weekly-axis-label mindevents-weekly-axis-label--end">' . esc_html($this->format_hour_label(24)) . '</span>';
        $out .= '</div>';

        foreach ($weekDates as $date) {
            $dateKey = $date->format('Y-m-d');
            $classes = array('mindevents-weekly-day');

            if ($this->is_today($date)) {
                $classes[] = $this->classes['today'];
            }

            if ($this->is_past_day($date)) {
                $classes[] = $this->classes['past'];
            }

            $out .= '<div class="' . esc_attr(implode(' ', $classes)) . '" data-date="' . esc_attr($dateKey) . '">';

            for ($hour = 0; $hour <= 24; $hour++) {
                $modifier = ($hour === 24) ? ' mindevents-weekly-hour-line--end' : '';
                $top      = ($hour / 24) * 100;
                $out     .= '<span class="mindevents-weekly-hour-line' . esc_attr($modifier) . '" aria-hidden="true" style="top:' . esc_attr(number_format((float) $top, 4, '.', '')) . '%"></span>';
            }

            if (!empty($eventsByDay[$dateKey])) {
                foreach ($eventsByDay[$dateKey] as $eventData) {
                    // Lane geometry arrives as percentages; the gutter keeps
                    // neighbouring occurrences from touching.
                    $style = sprintf(
                        'top:%1$s%%;height:%2$s%%;left:calc(%3$s%% + %5$s);width:calc(%4$s%% - %6$s);--mindevents-event-accent:%7$s;',
                        number_format((float) $eventData['top'], 4, '.', ''),
                        number_format((float) $eventData['height'], 4, '.', ''),
                        number_format((float) $eventData['left'], 4, '.', ''),
                        number_format((float) $eventData['width'], 4, '.', ''),
                        self::WEEK_EVENT_GUTTER,
                        self::WEEK_EVENT_GUTTER_TOTAL,
                        esc_attr($eventData['color'])
                    );

                    $out .= '<button type="button" class="mindevents-calendar-event-toggle mindevents-weekly-event" data-eventid="' . esc_attr($eventData['id']) . '" style="' . esc_attr($style) . '">';
                    $out .= '<span class="mindevents-weekly-event-time">' . esc_html($eventData['time']) . '</span>';
                    $out .= '<span class="mindevents-weekly-event-title">' . esc_html($eventData['title']) . '</span>';
                    $out .= '</button>';
                }
            }

            $out .= '</div>';
        }

        $out .= '</div></div></div>';

        return $out;
    }

    public function get_calendar_nav_links($view = null) {
        $view        = $view ?: $this->get_frontend_view();
        $currentDate = $this->get_calendar_reference_date();

        if ($view === 'week') {
            $prevDate  = $currentDate->modify('-7 days');
            $nextDate  = $currentDate->modify('+7 days');
            $prevLabel = __('Previous Week', 'simple-events');
            $nextLabel = __('Next Week', 'simple-events');
        } else {
            $prevDate  = $currentDate->modify('first day of previous month');
            $nextDate  = $currentDate->modify('first day of next month');
            $prevLabel = wp_date('F', $prevDate->getTimestamp());
            $nextLabel = wp_date('F', $nextDate->getTimestamp());
        }

        $currentUrl = mindevents_current_view_url();
        $prevArgs   = array('calendar_date' => $prevDate->format('Y-m-d'));
        $nextArgs   = array('calendar_date' => $nextDate->format('Y-m-d'));

        if (function_exists('mindevents_get_frontend_filter_query_args')) {
            $prevArgs = mindevents_get_frontend_filter_query_args(null, array('calendar_date' => $prevDate->format('Y-m-d')), array('paged'));
            $nextArgs = mindevents_get_frontend_filter_query_args(null, array('calendar_date' => $nextDate->format('Y-m-d')), array('paged'));
        }

        $out  = '<div class="mindevents-calendar-nav">';
        $out .= '<a class="mindevents-button mindevents-button--secondary" href="' . esc_url(add_query_arg($prevArgs, $currentUrl)) . '">&larr; ' . esc_html($prevLabel) . '</a>';
        $out .= '<a class="mindevents-button mindevents-button--secondary" href="' . esc_url(add_query_arg($nextArgs, $currentUrl)) . '">' . esc_html($nextLabel) . ' &rarr;</a>';
        $out .= '</div>';

        return $out;
    }

    public function get_sub_events($args = array()) {
        $passed_meta_query = array();
        if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
            $passed_meta_query = $args['meta_query'];
            unset($args['meta_query']);
        }

        $has_parent_context = (is_numeric($this->eventID) && (int) $this->eventID > 0);

        $defaults = array(
            'meta_query'       => array(
                array(
                    'key'     => 'mindevents_start_utc',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby'          => 'meta_value',
            'meta_key'         => 'mindevents_start_utc',
            'meta_type'        => 'DATETIME',
            'order'            => 'ASC',
            'post_type'        => 'mind_sub_event',
            'suppress_filters' => true,
            'posts_per_page'   => -1,
        );

        if ($has_parent_context) {
            $defaults['post_parent'] = (int) $this->eventID;
        }

        if ($this->show_past_events === false) {
            $defaults['meta_query'][] = array(
                'key'     => 'mindevents_end_utc',
                'value'   => mindevents_now_utc(),
                'compare' => '>',
                'type'    => 'DATETIME',
            );
        }

        if (!$has_parent_context && empty($args['post_parent']) && empty($args['post_parent__in'])) {
            unset($defaults['post_parent']);
        }

        $args = wp_parse_args($args, $defaults);

        if (!empty($passed_meta_query)) {
            foreach ($passed_meta_query as $meta_clause) {
                $args['meta_query'][] = $meta_clause;
            }
        }

        return get_posts($args);
    }

    public function get_front_calendar($args = array()) {
        $this->clearDailyHtml();
        $this->setStartOfWeek($this->calendar_start_day);

        $args = apply_filters('mindevents_front_calendar_query_args', $args, $this);
        $args = $this->apply_visible_period_to_query_args($args, $this->get_frontend_view());

        $eventDates = $this->get_sub_events($args);
        $view       = $this->get_frontend_view();

        if ($eventDates && $view !== 'week') {
            foreach ($eventDates as $event) {
                $times = mindevents_get_occurrence_times($event->ID);
                if (!$times) {
                    continue;
                }

                $title      = $this->get_occurrence_title($event->ID);
                $time_label = $this->format_time_range($times['start'], $times['end']);

                $html  = '<div class="mindevents-calendar-event-card">';
                $html .= $this->get_event_color_bar($event->ID);
                $html .= '<button type="button" class="mindevents-calendar-event-toggle" data-eventid="' . esc_attr($event->ID) . '">';
                $html .= '<span class="mindevents-calendar-event-title">' . esc_html($title) . '</span>';
                $html .= '<span class="mindevents-calendar-event-time">' . esc_html($time_label) . '</span>';
                $html .= '</button>';
                $html .= '</div>';

                $this->addDailyHtml($html, $times['start'], $times['end']);
            }
        }

        $html = ($view === 'week') ? $this->renderWeek($eventDates) : $this->render();

        if (empty($eventDates)) {
            $html = '<p class="mindevents-notice">' . esc_html__('No events matched the current filters.', 'simple-events') . '</p>' . $html;
        }

        return $html;
    }

    public function get_front_list($calDate = '', $args = array()) {
        $this->clearDailyHtml();
        $this->last_front_list_query = null;
        $this->setStartOfWeek($this->calendar_start_day);

        $now                 = mindevents_now_utc();
        $use_frontend_period = $this->should_use_frontend_visible_period();

        $default = array(
            'post_type'        => 'mind_sub_event',
            'orderby'          => 'meta_value',
            'meta_key'         => 'mindevents_start_utc',
            'meta_type'        => 'DATETIME',
            'order'            => 'ASC',
            'suppress_filters' => true,
            'posts_per_page'   => -1,
            'meta_query'       => array(),
        );

        if (!$use_frontend_period && $calDate === 'archive') {
            // Whatever is on now or starts in the next 30 days.
            $default['meta_query'][] = mindevents_overlapping_meta_query(new DateTimeImmutable('now'), new DateTimeImmutable('+30 days'));
        } elseif (!$use_frontend_period && is_tax('mind_event_category')) {
            $default['meta_query'][] = array(
                'key'     => 'mindevents_end_utc',
                'value'   => $now,
                'compare' => '>',
                'type'    => 'DATETIME',
            );
            $per_page                  = (int) get_option('posts_per_page');
            $default['posts_per_page'] = ($per_page > 0) ? $per_page : 10;
            $default['paged']          = max(1, absint(get_query_var('paged') ?: ($_GET['paged'] ?? 1))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only view state.
        }

        $args = wp_parse_args($args, $default);
        $args = apply_filters('mindevents_front_list_query_args', $args, $this, $calDate);
        $args = $this->apply_visible_period_to_query_args($args, 'list');

        if (!is_admin()) {
            $args['post_status'] = 'publish';
        }

        // Within the visible period, the list shows only what is still to come.
        if ($use_frontend_period && $this->get_frontend_view() === 'list') {
            $args['meta_query'][] = array(
                'key'     => 'mindevents_start_utc',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            );
        }

        $list_query = new WP_Query($args);
        $this->last_front_list_query = $list_query;

        foreach ($list_query->posts as $event) {
            $times = mindevents_get_occurrence_times($event->ID);
            if ($times) {
                $this->addDailyHtml($this->get_list_item_html($event->ID), $times['start']);
            }
        }

        if ($this->dailyHtml) {
            return $this->renderList();
        }

        if ($use_frontend_period) {
            return '<p class="mindevents-notice">' . esc_html__('No events matched the current view.', 'simple-events') . '</p>' . $this->renderList();
        }

        return '<p class="mindevents-notice">' . esc_html__('There are no upcoming events.', 'simple-events') . '</p>';
    }

    public function get_last_front_list_query() {
        return $this->last_front_list_query;
    }

    public function renderList() {
        $out = '';

        if ($this->should_use_frontend_visible_period() && $this->get_frontend_view() === 'list' && !is_admin()) {
            $out .= $this->get_calendar_nav_links('list');
        }

        $out .= '<div id="mindCalanderList" class="mindevents-list">';

        if (empty($this->dailyHtml) && $this->should_use_frontend_visible_period() && $this->get_frontend_view() === 'list') {
            $period = $this->get_visible_period('list');
            $out .= '<h2 class="mindevents-list-month">' . esc_html(wp_date('F Y', $period['start']->getTimestamp())) . '</h2>';
        }

        foreach ($this->dailyHtml as $year => $year_items) {
            foreach ($year_items as $month => $month_items) {
                $out .= '<h2 class="mindevents-list-month">' . esc_html(wp_date('F Y', $this->local_day($year, $month, 1)->getTimestamp())) . '</h2>';
                foreach ($month_items as $day => $daily_items) {
                    $date_label = wp_date($this->date_format, $this->local_day($year, $month, $day)->getTimestamp());
                    $out .= '<section class="mindevents-list-day">';
                    $out .= '<h3 class="mindevents-list-day-label">' . esc_html($date_label) . '</h3>';
                    foreach ($daily_items as $html) {
                        $out .= $html;
                    }
                    $out .= '</section>';
                }
            }
        }

        $out .= '</div>';

        return $out;
    }

    public function get_list_item_html($event = '', $display_link = true) {
        $event      = absint($event);
        $parent_id  = (int) wp_get_post_parent_id($event);
        $title      = $this->get_occurrence_title($event);
        $permalink  = $parent_id ? get_permalink($parent_id) : get_permalink($event);
        $times      = mindevents_get_occurrence_times($event);
        $date_label = $times ? $this->format_date_range($times['start'], $times['end']) : '';
        $excerpt    = mindevents_get_occurrence_excerpt($event);
        $location   = mindevents_get_occurrence_location($event);
        $organizer  = $this->get_organizer_block($event);
        $is_past    = $this->is_past_occurrence($event);

        $classes = array('mindevents-list-card');
        if ($is_past) {
            $classes[] = 'is-past';
        }

        $html  = '<article class="' . esc_attr(implode(' ', $classes)) . '">';
        $html .= $this->get_event_color_bar($event);
        $html .= '<div class="mindevents-list-card__body">';

        if ($display_link && $permalink) {
            $html .= '<a class="mindevents-list-card__title-link" href="' . esc_url($permalink) . '">';
        }

        $html .= '<h4 class="mindevents-list-card__title">' . esc_html($title) . '</h4>';

        if ($display_link && $permalink) {
            $html .= '</a>';
        }

        $html .= '<p class="mindevents-list-card__datetime">' . esc_html($date_label) . '</p>';

        if ($location !== '') {
            $html .= '<p class="mindevents-list-card__location">' . esc_html($location) . '</p>';
        }

        if ($excerpt !== '') {
            $html .= '<div class="mindevents-list-card__excerpt">' . wp_kses_post(wpautop($excerpt)) . '</div>';
        }

        $html .= $organizer;

        $html .= '<div class="mindevents-list-card__actions">';
        if ($display_link && $permalink) {
            $html .= '<a class="mindevents-button mindevents-button--secondary" href="' . esc_url($permalink) . '">' . esc_html__('View Event', 'simple-events') . '</a>';
        }
        if (function_exists('mindevents_get_event_add_to_calendar_links')) {
            $html .= mindevents_get_event_add_to_calendar_links($event);
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</article>';

        return $html;
    }

    public function get_cal_meta_html($event = '') {
        $event       = absint($event);
        $parent_id   = (int) wp_get_post_parent_id($event);
        $parent_link = $parent_id ? get_permalink($parent_id) : '';
        $image       = get_the_post_thumbnail($parent_id ?: $event, 'large', array('class' => 'mindevents-event-meta__image'));
        $title       = $this->get_occurrence_title($event);
        $times       = mindevents_get_occurrence_times($event);
        $date_label  = $times ? $this->format_date_range($times['start'], $times['end']) : '';
        $excerpt   = mindevents_get_occurrence_excerpt($event);
        $location  = mindevents_get_occurrence_location($event);
        $organizer = $this->get_organizer_block($event);

        $html  = '<article class="mindevents-event-meta">';
        $html .= $this->get_event_color_bar($event);

        if ($image) {
            $html .= '<div class="mindevents-event-meta__media">';
            if ($parent_link) {
                $html .= '<a href="' . esc_url($parent_link) . '">' . $image . '</a>';
            } else {
                $html .= $image;
            }
            $html .= '</div>';
        }

        $html .= '<div class="mindevents-event-meta__content">';
        $html .= '<div class="mindevents-event-meta__header">';
        $html .= '<div class="mindevents-event-meta__heading">';

        if ($parent_link) {
            $html .= '<a class="mindevents-event-meta__title-link" href="' . esc_url($parent_link) . '">';
        }
        $html .= '<h3 class="mindevents-event-meta__title">' . esc_html($title) . '</h3>';
        if ($parent_link) {
            $html .= '</a>';
        }

        $html .= '<p class="mindevents-event-meta__datetime">' . esc_html($date_label) . '</p>';

        if ($location !== '') {
            $html .= '<p class="mindevents-event-meta__location">' . esc_html($location) . '</p>';
        }

        $html .= '</div>';
        $html .= '<button type="button" class="event-meta-close" aria-label="' . esc_attr__('Close event details', 'simple-events') . '">&times;</button>';
        $html .= '</div>';

        if ($excerpt !== '') {
            $html .= '<div class="mindevents-event-meta__excerpt">' . wp_kses_post(wpautop($excerpt)) . '</div>';
        }

        $html .= $organizer;

        $html .= '<div class="mindevents-event-meta__actions">';
        if ($parent_link) {
            $html .= '<a class="mindevents-button mindevents-button--secondary" href="' . esc_url($parent_link) . '">' . esc_html__('Open Event Page', 'simple-events') . '</a>';
        }
        if (function_exists('mindevents_get_event_add_to_calendar_links')) {
            $html .= mindevents_get_event_add_to_calendar_links($event);
        }
        $html .= '</div>';

        $html .= '</div></article>';

        return $html;
    }

    public function get_calendar($calDate = '') {
        $this->clearDailyHtml();
        $this->setStartOfWeek($this->calendar_start_day);

        $eventDates = $this->get_sub_events(array(
            'post_type'      => 'mind_sub_event',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $this->eventID,
        ));

        if ($eventDates) {
            foreach ($eventDates as $event) {
                $times = mindevents_get_occurrence_times($event->ID);
                if (!$times) {
                    continue;
                }

                $html  = '<div class="mindevents-admin-occurrence">';
                $html .= $this->get_event_color_bar($event->ID);
                $html .= '<div class="mindevents-admin-occurrence__actions">';
                $html .= '<button type="button" class="mindevents-admin-occurrence__edit" data-subid="' . esc_attr($event->ID) . '">' . esc_html($this->format_time_range($times['start'], $times['end'])) . '</button>';
                $html .= '<button type="button" class="mindevents-admin-occurrence__delete" data-subid="' . esc_attr($event->ID) . '" aria-label="' . esc_attr__('Remove occurrence', 'simple-events') . '">&times;</button>';
                $html .= '</div>';
                $html .= '</div>';
                $this->addDailyHtml($html, $times['start'], $times['end']);
            }
        }

        return $this->render();
    }

    /**
     * @return true|WP_Error
     */
    public function update_sub_event($sub_event, $meta, $parentID) {
        $sub_event = absint($sub_event);
        $parentID  = absint($parentID);
        if (!$sub_event || !$parentID) {
            return new WP_Error('mindevents_invalid_occurrence', __('That occurrence does not exist.', 'simple-events'));
        }

        $meta = $this->sanitize_sub_event_meta($meta, $parentID);

        return $this->save_occurrence($sub_event, $parentID, $meta);
    }

    /**
     * Move an occurrence to another date, keeping its local start time and
     * its length.
     *
     * @return true|WP_Error
     */
    public function move_sub_event($occurrence_id, $new_date) {
        $times = mindevents_get_occurrence_times($occurrence_id);
        $start = $times ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $new_date . ' ' . $times['start']->format('H:i:s'), wp_timezone()) : false;

        if (!$start || $start->format('Y-m-d') !== $new_date) {
            return new WP_Error('mindevents_invalid_date', __('The new occurrence date is invalid.', 'simple-events'));
        }

        $length = $times['end']->getTimestamp() - $times['start']->getTimestamp();
        $end    = (new DateTimeImmutable('@' . ($start->getTimestamp() + $length)))->setTimezone(wp_timezone());

        return $this->save_occurrence($occurrence_id, (int) wp_get_post_parent_id($occurrence_id), array(
            'mindevents_start_utc' => mindevents_to_utc($start),
            'mindevents_end_utc'   => mindevents_to_utc($end),
        ));
    }

    /**
     * Write sanitized meta to an existing occurrence, refusing a change that
     * would duplicate another of the event's occurrences.
     */
    private function save_occurrence($occurrence_id, $event_id, array $meta) {
        if ($this->has_occurrence_at($event_id, $meta['mindevents_start_utc'], $meta['mindevents_end_utc'], $occurrence_id)) {
            return new WP_Error('mindevents_duplicate', __('An occurrence at that time already exists.', 'simple-events'));
        }

        // update_post_meta() and wp_update_post() unslash their input.
        foreach ($meta as $key => $value) {
            update_post_meta($occurrence_id, $key, wp_slash($value));
        }

        wp_update_post(wp_slash(array(
            'ID'         => $occurrence_id,
            'post_title' => $this->build_title($event_id, $meta),
        )));

        mindevents_sync_event_date_range($event_id);

        return true;
    }

    /**
     * @return int|false|WP_Error The new occurrence ID, false if the event
     *                            already has one at that time.
     */
    public function add_sub_event($date, $meta, $eventID, $args = array()) {
        $eventID = absint($eventID);
        if (!$eventID) {
            return false;
        }

        $meta = $this->sanitize_sub_event_meta($meta, $eventID, $date);

        if ($this->has_occurrence_at($eventID, $meta['mindevents_start_utc'], $meta['mindevents_end_utc'])) {
            return false;
        }

        // wp_insert_post() and the meta it writes unslash their input.
        $post_id = wp_insert_post(wp_slash(wp_parse_args($args, array(
            'post_author' => (int) get_post_field('post_author', $eventID),
            'post_title'  => $this->build_title($eventID, $meta),
            'post_status' => mindevents_occurrence_status(get_post_status($eventID)),
            'post_type'   => 'mind_sub_event',
            'post_parent' => $eventID,
            'meta_input'  => $meta,
        ))), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set directly: tax_input is skipped when the current user cannot
        // assign terms, and permission was already checked by the caller.
        wp_set_post_terms($post_id, wp_get_post_terms($eventID, 'mind_event_category', array('fields' => 'ids')), 'mind_event_category');
        mindevents_sync_event_date_range($eventID);

        return $post_id;
    }

    /**
     * Whether the event has an occurrence at exactly these times.
     */
    private function has_occurrence_at($event_id, $start_utc, $end_utc, $exclude_id = 0) {
        return (bool) get_posts(array(
            'post_type'      => 'mind_sub_event',
            'post_status'    => 'any',
            'post_parent'    => $event_id,
            'post__not_in'   => array_filter(array((int) $exclude_id)),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array('key' => 'mindevents_start_utc', 'value' => $start_utc),
                array('key' => 'mindevents_end_utc', 'value' => $end_utc),
            ),
        ));
    }

    public function delete_sub_events($parentID = '') {
        $sub_events = $this->get_sub_events(array(
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $parentID,
        ));

        if (!$sub_events) {
            return false;
        }

        foreach ($sub_events as $event) {
            wp_delete_post($event->ID, true);
        }

        mindevents_sync_event_date_range($parentID);

        return true;
    }

    public function generate_schema() {
        if (!$this->eventID || get_post_type($this->eventID) !== 'mind_events' || get_post_status($this->eventID) !== 'publish') {
            return '';
        }

        $sub_events = $this->get_sub_events();
        $location   = mindevents_get_occurrence_location($this->eventID);
        $organizer  = mindevents_get_organizer_data($this->eventID);

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => mindevents_get_plain_title($this->eventID),
            'startDate'   => mindevents_iso8601(get_post_meta($this->eventID, 'mindevents_first_start_utc', true)),
            'endDate'     => mindevents_iso8601(get_post_meta($this->eventID, 'mindevents_last_end_utc', true)),
            'description' => mindevents_plain_text(get_the_excerpt($this->eventID)),
            'url'         => get_permalink($this->eventID),
            'image'       => array_filter(array(get_the_post_thumbnail_url($this->eventID, 'large'))),
        );

        if ($location !== '') {
            $schema['location'] = array(
                '@type' => 'Place',
                'name'  => $location,
            );
        }

        if (!empty($organizer['name'])) {
            $schema['organizer'] = array(
                '@type' => 'Organization',
                'name'  => $organizer['name'],
            );
        }

        if ($sub_events) {
            $schema['subEvent'] = array();
            foreach ($sub_events as $event) {
                $sub_schema = array(
                    '@type'       => 'Event',
                    'name'        => mindevents_get_plain_title($this->eventID),
                    'startDate'   => mindevents_iso8601(get_post_meta($event->ID, 'mindevents_start_utc', true)),
                    'endDate'     => mindevents_iso8601(get_post_meta($event->ID, 'mindevents_end_utc', true)),
                    'description' => mindevents_plain_text(mindevents_get_occurrence_excerpt($event->ID)),
                    'url'         => get_permalink($this->eventID),
                );

                $sub_location = mindevents_get_occurrence_location($event->ID);
                if ($sub_location !== '') {
                    $sub_schema['location'] = array(
                        '@type' => 'Place',
                        'name'  => $sub_location,
                    );
                }

                $schema['subEvent'][] = $sub_schema;
            }
        }

        // JSON_HEX_TAG escapes < and >, so no value can close the <script>
        // block this is printed into.
        return wp_json_encode($schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A date from a timestamp, a DateTime, or a string read in the site
     * timezone. Null if it cannot be parsed.
     */
    private function parseDate($date = null) {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        if (is_int($date)) {
            return (new DateTimeImmutable('@' . $date))->setTimezone(wp_timezone());
        }

        if (is_string($date) && $date !== '') {
            try {
                return new DateTimeImmutable($date, wp_timezone());
            } catch (Exception $exception) {
                return null;
            }
        }

        return null;
    }

    private function get_calendar_reference_date() {
        if (!($this->now instanceof DateTimeImmutable)) {
            $this->setDate();
        }

        return $this->now;
    }

    private function get_frontend_filters_state() {
        if (function_exists('mindevents_get_frontend_filters')) {
            $filters = mindevents_get_frontend_filters();
            if (is_array($filters)) {
                return $filters;
            }
        }

        return array();
    }

    private function should_use_frontend_visible_period() {
        $filters = $this->get_frontend_filters_state();

        return !empty($filters['apply']);
    }

    private function get_frontend_view() {
        if (!$this->should_use_frontend_visible_period()) {
            return 'month';
        }

        $filters = $this->get_frontend_filters_state();
        $view    = isset($filters['event_view']) ? sanitize_key((string) $filters['event_view']) : 'month';

        return in_array($view, array('month', 'week', 'list'), true) ? $view : 'month';
    }

    private function get_visible_period($view = null) {
        $this->setStartOfWeek($this->calendar_start_day);

        $view    = $view ?: $this->get_frontend_view();
        $current = $this->get_calendar_reference_date();
        $current = new DateTimeImmutable($current->format('Y-m-d H:i:s'), $current->getTimezone());

        if ($view === 'week') {
            $period_start      = $current->setTime(0, 0, 0);
            $days_to_subtract  = (((int) $period_start->format('w')) - $this->offset + 7) % 7;
            if ($days_to_subtract > 0) {
                $period_start = $period_start->modify('-' . $days_to_subtract . ' days');
            }
            $period_end = $period_start->modify('+6 days')->setTime(23, 59, 59);
        } else {
            $period_start = $current->modify('first day of this month')->setTime(0, 0, 0);
            $period_end   = $period_start->modify('last day of this month')->setTime(23, 59, 59);
        }

        return array(
            'view'  => $view,
            'start' => $period_start,
            'end'   => $period_end,
        );
    }

    private function apply_visible_period_to_query_args($args, $view = null) {
        if (!$this->should_use_frontend_visible_period()) {
            return $args;
        }

        $period = $this->get_visible_period($view);

        if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
            $args['meta_query'] = array();
        }

        $args['meta_query'][] = mindevents_overlapping_meta_query($period['start'], $period['end']);

        return $args;
    }

    /**
     * Noon on a calendar day in the site timezone. Noon, because a daylight
     * saving change can make midnight not exist.
     */
    private function local_day($year, $month, $day) {
        return (new DateTimeImmutable('now', wp_timezone()))->setDate((int) $year, (int) $month, (int) $day)->setTime(12, 0, 0);
    }

    private function get_week_display_label(DateTimeInterface $period_start, DateTimeInterface $period_end) {
        $start = $period_start->getTimestamp();
        $end   = $period_end->getTimestamp();

        if ($period_start->format('Y-m') === $period_end->format('Y-m')) {
            return wp_date('F j', $start) . ' - ' . wp_date('j, Y', $end);
        }

        return wp_date('F j, Y', $start) . ' - ' . wp_date('F j, Y', $end);
    }

    private function rotate(array &$data, $steps) {
        $count = count($data);
        if ($count < 1) {
            return;
        }

        if ($steps < 0) {
            $steps = $count + $steps;
        }

        $steps %= $count;

        for ($i = 0; $i < $steps; $i++) {
            $data[] = array_shift($data);
        }
    }

    /**
     * Weekday names, Sunday first, in the site's language. Sunday first
     * because rotate() shifts them by the week's start offset, where
     * Sunday is 0.
     */
    private function weekdays() {
        global $wp_locale;

        $days = array();
        for ($index = 0; $index < 7; $index++) {
            $days[] = $wp_locale->get_weekday($index);
        }

        return $days;
    }

    private function is_today(DateTimeInterface $date) {
        if (!($this->today instanceof DateTimeInterface)) {
            return false;
        }

        return $this->today->format('Y-m-d') === $date->format('Y-m-d');
    }

    private function is_past_day(DateTimeInterface $date) {
        if (!($this->today instanceof DateTimeInterface)) {
            return false;
        }

        return $date->format('Y-m-d') < $this->today->format('Y-m-d');
    }

    private function is_past_occurrence($event_id) {
        $times = mindevents_get_occurrence_times($event_id);

        return $times && $this->today && $times['start'] < $this->today;
    }

    private function get_occurrence_title($event_id) {
        $parent_id = (int) wp_get_post_parent_id($event_id);

        return get_the_title($parent_id ?: $event_id);
    }

    private function get_event_color_bar($eventID = '') {
        $colors = $this->get_event_colors($eventID);

        if (!$colors) {
            $colors = array('#2d7ff9');
        }

        $html = '<div class="mindevents-color-bar">';
        foreach ($colors as $color) {
            $html .= '<span class="mindevents-color-bar__segment" style="background-color:' . esc_attr($color) . '; width:' . esc_attr((string) (100 / count($colors))) . '%"></span>';
        }
        $html .= '</div>';

        return $html;
    }

    private function get_event_colors($eventID) {
        $colors = mindevents_get_occurrence_colors($eventID);

        if (!$colors) {
            $colors[] = '#2d7ff9';
        }

        return $colors;
    }

    private function get_primary_event_color($eventID) {
        $colors = $this->get_event_colors($eventID);

        return !empty($colors[0]) ? $colors[0] : '#2d7ff9';
    }

    private function get_organizer_block($event_id) {
        $organizer = mindevents_get_organizer_data($event_id);

        if (empty($organizer['name'])) {
            return '';
        }

        $html = '<div class="mindevents-organizer">';
        if (!empty($organizer['image_url'])) {
            $html .= '<div class="mindevents-organizer__image"><img src="' . esc_url($organizer['image_url']) . '" alt="' . esc_attr($organizer['name']) . '"></div>';
        }
        $html .= '<div class="mindevents-organizer__content">';
        $html .= '<span class="mindevents-organizer__label">' . esc_html__('Organizer', 'simple-events') . '</span>';
        $html .= '<strong class="mindevents-organizer__name">' . esc_html($organizer['name']) . '</strong>';
        if (!empty($organizer['title'])) {
            $html .= '<span class="mindevents-organizer__title">' . esc_html($organizer['title']) . '</span>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private function format_time_range(DateTimeInterface $start, ?DateTimeInterface $end = null) {
        $label = wp_date($this->time_format, $start->getTimestamp());

        if ($end) {
            $label .= ' - ' . wp_date($this->time_format, $end->getTimestamp());
        }

        return $label;
    }

    private function format_hour_label($hour) {
        // A fixed UTC date, so a daylight saving change cannot skip or repeat an hour.
        return wp_date('g a', gmmktime(((int) $hour) % 24, 0, 0, 1, 1, 2000), new DateTimeZone('UTC'));
    }

    private function build_week_events_by_day($events, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd) {
        $eventsByDay = array();

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $weekStart->modify('+' . $dayOffset . ' days');
            $eventsByDay[$date->format('Y-m-d')] = array();
        }

        foreach ((array) $events as $event) {
            $event_id = (int) ($event->ID ?? 0);
            $times    = $event_id ? mindevents_get_occurrence_times($event_id) : null;

            if (!$times) {
                continue;
            }

            $start = $times['start'];
            $end   = $times['end'];

            if ($end <= $weekStart || $start >= $weekEnd->modify('+1 second')) {
                continue;
            }

            $segmentStart = ($start < $weekStart) ? $weekStart : $start;
            $segmentEnd   = ($end > $weekEnd) ? $weekEnd : $end;
            $cursor       = $segmentStart->setTime(0, 0, 0);

            while ($cursor <= $segmentEnd) {
                $dayKey   = $cursor->format('Y-m-d');
                $dayStart = $cursor->setTime(0, 0, 0);
                $dayEnd   = $cursor->setTime(23, 59, 59);

                $daySegmentStart = ($start > $dayStart) ? $start : $dayStart;
                $daySegmentEnd   = ($end < $dayEnd) ? $end : $dayEnd;

                if ($daySegmentEnd > $daySegmentStart && isset($eventsByDay[$dayKey])) {
                    $startMinutes = ((int) $daySegmentStart->format('G') * 60) + (int) $daySegmentStart->format('i');
                    $endMinutes   = ((int) $daySegmentEnd->format('G') * 60) + (int) $daySegmentEnd->format('i');

                    if ($daySegmentEnd->format('Y-m-d') !== $daySegmentStart->format('Y-m-d')) {
                        $endMinutes = 1440;
                    }

                    if ($endMinutes <= $startMinutes) {
                        $endMinutes = min(1440, $startMinutes + 30);
                    }

                    $eventsByDay[$dayKey][] = array(
                        'id'            => $event_id,
                        'title'         => $this->get_occurrence_title($event_id),
                        'time'          => $this->format_time_range($daySegmentStart, $daySegmentEnd),
                        'color'         => $this->get_primary_event_color($event_id),
                        'start_minutes' => $startMinutes,
                        'end_minutes'   => $endMinutes,
                    );
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($eventsByDay as $dayKey => $dayEvents) {
            $eventsByDay[$dayKey] = $this->position_day_occurrences($dayEvents);
        }

        return apply_filters('mindevents_calendar_week_events', $eventsByDay, $this);
    }

    /**
     * Work out where each occurrence sits within a single day column.
     *
     * Occurrences that share time are grouped into a cluster and placed
     * side by side, so two occurrences at the same hour take half the
     * width each rather than covering one another. Clusters are
     * independent: a busy morning does not narrow a quiet afternoon.
     */
    private function position_day_occurrences(array $dayEvents) {
        usort($dayEvents, function($left, $right) {
            if ($left['start_minutes'] === $right['start_minutes']) {
                return $right['end_minutes'] <=> $left['end_minutes'];
            }

            return $left['start_minutes'] <=> $right['start_minutes'];
        });

        $positioned  = array();
        $cluster     = array();
        $cluster_end = null;

        foreach ($dayEvents as $event) {
            // A gap with nothing running closes the cluster.
            if ($cluster_end !== null && $event['start_minutes'] >= $cluster_end) {
                $positioned  = array_merge($positioned, $this->spread_cluster($cluster));
                $cluster     = array();
                $cluster_end = null;
            }

            $cluster[]   = $event;
            $cluster_end = ($cluster_end === null)
                ? $event['end_minutes']
                : max($cluster_end, $event['end_minutes']);
        }

        if (!empty($cluster)) {
            $positioned = array_merge($positioned, $this->spread_cluster($cluster));
        }

        return $positioned;
    }

    /**
     * Give every occurrence in one overlapping cluster a lane, then convert
     * lane and time into the percentages the template positions with.
     */
    private function spread_cluster(array $cluster) {
        $lane_ends = array();

        foreach ($cluster as $index => $event) {
            $lane = null;

            foreach ($lane_ends as $candidate => $ends_at) {
                if ($event['start_minutes'] >= $ends_at) {
                    $lane = $candidate;
                    break;
                }
            }

            if ($lane === null) {
                $lane_ends[] = $event['end_minutes'];
                $lane        = count($lane_ends) - 1;
            } else {
                $lane_ends[$lane] = $event['end_minutes'];
            }

            $cluster[$index]['lane'] = $lane;
        }

        $lane_count = max(1, count($lane_ends));

        foreach ($cluster as $index => $event) {
            $minutes = max($event['end_minutes'] - $event['start_minutes'], self::MIN_EVENT_MINUTES);

            $cluster[$index]['top']    = ($event['start_minutes'] / self::MINUTES_PER_DAY) * 100;
            $cluster[$index]['height'] = ($minutes / self::MINUTES_PER_DAY) * 100;
            $cluster[$index]['width']  = 100 / $lane_count;
            $cluster[$index]['left']   = ($event['lane'] / $lane_count) * 100;
            $cluster[$index]['lanes']  = $lane_count;
        }

        return $cluster;
    }

    private function format_date_range(DateTimeInterface $start, DateTimeInterface $end) {
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return wp_date($this->date_format, $start->getTimestamp()) . ' · ' . $this->format_time_range($start, $end);
        }

        $format = $this->date_format . ' ' . $this->time_format;

        return wp_date($format, $start->getTimestamp()) . ' - ' . wp_date($format, $end->getTimestamp());
    }

    /**
     * Occurrence meta from admin input, ready to store.
     *
     * Only known keys are returned, each sanitized. The date and times are
     * read in the site timezone and stored as UTC.
     *
     * @throws InvalidArgumentException When the date or times are not valid.
     */
    private function sanitize_sub_event_meta($meta, $parentID, $date = '') {
        $meta = is_array($meta) ? $meta : array();
        $date = sanitize_text_field((string) ($date ?: ($meta['event_date'] ?? '')));

        $times = mindevents_local_times(
            $date,
            mindevents_normalize_time_value($meta['starttime'] ?? '', $this->default_time_from_parent($parentID, 'starttime')),
            mindevents_normalize_time_value($meta['endtime'] ?? '', $this->default_time_from_parent($parentID, 'endtime'))
        );

        if (!$times) {
            throw new InvalidArgumentException('Invalid occurrence date or time.');
        }

        return array(
            'mindevents_start_utc'          => mindevents_to_utc($times['start']),
            'mindevents_end_utc'            => mindevents_to_utc($times['end']),
            'eventColor'                    => sanitize_hex_color($meta['eventColor'] ?? '') ?: '',
            'eventDescription'              => wp_kses_post($meta['eventDescription'] ?? ''),
            'mindevents_location'           => sanitize_text_field((string) ($meta['mindevents_location'] ?? '')),
            'mindevents_organizer_name'     => sanitize_text_field((string) ($meta['mindevents_organizer_name'] ?? '')),
            'mindevents_organizer_title'    => sanitize_text_field((string) ($meta['mindevents_organizer_title'] ?? '')),
            'mindevents_organizer_image_id' => absint($meta['mindevents_organizer_image_id'] ?? 0),
        );
    }

    private function default_time_from_parent($parentID, $key) {
        $defaults = get_post_meta($parentID, 'event_defaults', true);
        $defaults = is_array($defaults) ? $defaults : array();

        if (!empty($defaults[$key])) {
            return mindevents_normalize_time_value($defaults[$key]);
        }

        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());
        $option_key = MINDEVENTS_PREPEND . (($key === 'endtime') ? 'end_time' : 'start_time');

        return $options[$option_key] ?? (($key === 'endtime') ? '21:00' : '19:00');
    }

    /**
     * The occurrence's admin title, e.g. "Pottery | 2030-07-01 | 19:00-21:00".
     */
    private function build_title($parentID, array $meta) {
        $start = mindevents_from_utc($meta['mindevents_start_utc']);
        $end   = mindevents_from_utc($meta['mindevents_end_utc']);
        $title = mindevents_get_plain_title($parentID) . ' | ' . $start->format('Y-m-d') . ' | ' . $start->format('H:i') . '-' . $end->format('H:i');

        return apply_filters('mindevents_occurrence_title', $title, $start, $end, $parentID);
    }
}
