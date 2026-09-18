<?php

if (!defined('ABSPATH')) {
    exit;
}

class mindEventCalendar {
    private $eventID = '';
    private $wp_post = null;
    private $calendar_start_day = 'Monday';
    private $show_past_events = true;
    private $event_categories = false;
    private $weekDayNames = null;
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
        $this->wp_post = is_numeric($id) ? get_post($id) : null;
        $this->setToday($today);
        $this->setCalendarClasses();

        if (isset($_GET['calendar_date'])) {
            $calendarDate = sanitize_text_field(wp_unslash($_GET['calendar_date']));
        }

        if ($calendarDate) {
            $this->setDate($calendarDate);
        } elseif (get_post($id)) {
            $first_date = get_post_meta($id, 'first_event_date', true);
            $this->setDate($first_date ? $first_date : current_time('mysql'));
        } else {
            $this->setDate(current_time('mysql'));
        }

        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());

        $this->calendar_start_day = $options[MINDEVENTS_PREPEND . 'start_day'] ?? 'Monday';
        $this->date_format        = get_option('date_format') ?: 'F j, Y';
        $this->time_format        = get_option('time_format') ?: 'g:i a';
    }

    public function setDate($date = null) {
        $this->now = $this->parseDate($date) ?: new DateTimeImmutable(current_time('mysql'), mindevents_wp_timezone());
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
            $this->today = new DateTimeImmutable(current_time('mysql'), mindevents_wp_timezone());
            return;
        }

        $this->today = $this->parseDate($today);
    }

    public function setWeekDayNames(?array $weekDayNames = null) {
        if (is_array($weekDayNames) && count($weekDayNames) !== 7) {
            throw new InvalidArgumentException('week array must have exactly 7 values');
        }

        $this->weekDayNames = $weekDayNames ? array_values($weekDayNames) : null;
    }

    public function setCalendarClasses(array $classes = array()) {
        foreach ($classes as $key => $value) {
            if (isset($this->classes[$key])) {
                $this->classes[$key] = $value;
            }
        }
    }

    public function setEventCategories($array = array()) {
        $this->event_categories = $array;
    }

    public function set_past_events_display($display) {
        if (is_string($display)) {
            $display = ($display === '1');
        }

        $this->show_past_events = ($display !== false);
    }

    public function addDailyHtml($html, $startDate, $endDate = null) {
        static $htmlCount = 0;

        $start = $this->parseDate($startDate);
        $end   = $endDate ? $this->parseDate($endDate) : $start;

        if (!($start instanceof DateTimeInterface) || !($end instanceof DateTimeInterface)) {
            throw new InvalidArgumentException('invalid event date');
        }

        if ($end->getTimestamp() < $start->getTimestamp()) {
            throw new InvalidArgumentException('end must come after start');
        }

        $working = new DateTimeImmutable($start->format('Y-m-d H:i:s'), $start->getTimezone());

        do {
            $key_year  = (int) $working->format('Y');
            $key_month = (int) $working->format('n');
            $key_day   = (int) $working->format('j');

            if (!isset($this->dailyHtml[$key_year])) {
                $this->dailyHtml[$key_year] = array();
            }
            if (!isset($this->dailyHtml[$key_year][$key_month])) {
                $this->dailyHtml[$key_year][$key_month] = array();
            }
            if (!isset($this->dailyHtml[$key_year][$key_month][$key_day])) {
                $this->dailyHtml[$key_year][$key_month][$key_day] = array();
            }

            $this->dailyHtml[$key_year][$key_month][$key_day][$htmlCount] = $html;
            $working = $working->add(new DateInterval('P1D'));
        } while ($working->getTimestamp() < ($end->getTimestamp() + 1));

        $htmlCount++;
    }

    public function clearDailyHtml() {
        $this->dailyHtml = array();
    }

    public function setStartOfWeek($offset) {
        if (is_int($offset)) {
            $this->offset = $offset % 7;
            return;
        }

        if ($this->weekDayNames !== null) {
            $weekOffset = array_search($offset, $this->weekDayNames, true);
            if ($weekOffset !== false) {
                $this->offset = $weekOffset;
                return;
            }
        }

        $weekTime = strtotime((string) $offset);
        if ($weekTime === false) {
            throw new InvalidArgumentException('invalid offset');
        }

        $this->offset = (int) date('N', $weekTime) % 7;
    }

    public function inject_event_html($year, $month, $day, $html) {
        $year  = (int) $year;
        $month = (int) $month;
        $day   = (int) $day;

        if (!isset($this->dailyHtml[$year])) {
            $this->dailyHtml[$year] = array();
        }
        if (!isset($this->dailyHtml[$year][$month])) {
            $this->dailyHtml[$year][$month] = array();
        }
        if (!isset($this->dailyHtml[$year][$month][$day])) {
            $this->dailyHtml[$year][$month][$day] = array();
        }

        $this->dailyHtml[$year][$month][$day][] = $html;
    }

    public function render() {
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
        $out .= '<h2 class="mindevents-calendar-title">' . esc_html($referenceDate->format('F Y')) . '</h2>';
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
            $out .= '<span class="mindevents-weekly-header-date">' . esc_html($date->format('M j')) . '</span>';
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
                    $style = sprintf(
                        'top:%1$s%%;height:%2$s%%;--mindevents-event-accent:%3$s;',
                        number_format((float) $eventData['top'], 4, '.', ''),
                        number_format((float) $eventData['height'], 4, '.', ''),
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
            $prevLabel = $prevDate->format('F');
            $nextLabel = $nextDate->format('F');
        }

        $currentUrl = home_url(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'));
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

    public function get_all_events($args = array()) {
        $defaults = array(
            'meta_query'       => array(
                array(
                    'key'     => 'event_start_time_stamp',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby'          => 'meta_value',
            'meta_key'         => 'event_start_time_stamp',
            'meta_type'        => 'DATETIME',
            'order'            => 'ASC',
            'post_type'        => 'sub_event',
            'suppress_filters' => true,
            'posts_per_page'   => -1,
        );

        $args = wp_parse_args($args, $defaults);

        return get_posts($args);
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
                    'key'     => 'event_start_time_stamp',
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby'          => 'meta_value',
            'meta_key'         => 'event_start_time_stamp',
            'meta_type'        => 'DATETIME',
            'order'            => 'ASC',
            'post_type'        => 'sub_event',
            'suppress_filters' => true,
            'posts_per_page'   => -1,
        );

        if ($has_parent_context) {
            $defaults['post_parent'] = (int) $this->eventID;
        }

        if (!is_admin()) {
            $defaults['meta_query'][] = mindevents_public_visibility_meta_query();
        }

        if ($this->show_past_events === false) {
            $defaults['meta_query'][] = array(
                'key'     => 'event_end_time_stamp',
                'value'   => current_time('mysql'),
                'compare' => '>=',
                'type'    => 'DATETIME',
            );
        }

        if ($this->event_categories) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'event_category',
                    'field'    => 'slug',
                    'terms'    => $this->event_categories,
                ),
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
                $event_start = get_post_meta($event->ID, 'event_start_time_stamp', true);
                $event_end   = get_post_meta($event->ID, 'event_end_time_stamp', true);
                $title       = $this->get_occurrence_title($event->ID);
                $time_label  = $this->format_time_range($event_start, $event_end);

                $html  = '<div class="mindevents-calendar-event-card">';
                $html .= $this->get_event_color_bar($event->ID);
                $html .= '<button type="button" class="mindevents-calendar-event-toggle" data-eventid="' . esc_attr($event->ID) . '">';
                $html .= '<span class="mindevents-calendar-event-title">' . esc_html($title) . '</span>';
                $html .= '<span class="mindevents-calendar-event-time">' . esc_html($time_label) . '</span>';
                $html .= '</button>';
                $html .= '</div>';

                $this->addDailyHtml($html, $event_start, $event_end);
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

        $current_time        = current_time('mysql');
        $current_time_dt     = new DateTimeImmutable($current_time, mindevents_wp_timezone());
        $current_plus_thirty = $current_time_dt->modify('+30 days')->format('Y-m-d H:i:s');
        $use_frontend_period = $this->should_use_frontend_visible_period();

        if ($use_frontend_period) {
            $default = array(
                'orderby'          => 'meta_value',
                'meta_key'         => 'event_start_time_stamp',
                'meta_type'        => 'DATETIME',
                'order'            => 'ASC',
                'post_type'        => 'sub_event',
                'suppress_filters' => true,
                'posts_per_page'   => -1,
            );
        } elseif ($calDate === 'archive') {
            $default = array(
                'meta_query'       => array(
                    array(
                        'key'     => 'event_end_time_stamp',
                        'value'   => $current_time,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => 'event_start_time_stamp',
                        'value'   => $current_plus_thirty,
                        'compare' => '<=',
                        'type'    => 'DATETIME',
                    ),
                ),
                'orderby'          => 'meta_value',
                'meta_key'         => 'event_start_time_stamp',
                'meta_type'        => 'DATETIME',
                'order'            => 'ASC',
                'post_type'        => 'sub_event',
                'suppress_filters' => true,
                'posts_per_page'   => -1,
            );
        } elseif (is_tax('event_category')) {
            $per_page = (int) get_option('posts_per_page');
            if ($per_page < 1) {
                $per_page = 10;
            }

            $default = array(
                'meta_query'       => array(
                    array(
                        'key'     => 'event_end_time_stamp',
                        'value'   => $current_time,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
                'orderby'          => 'meta_value',
                'meta_key'         => 'event_start_time_stamp',
                'meta_type'        => 'DATETIME',
                'order'            => 'ASC',
                'post_type'        => 'sub_event',
                'suppress_filters' => true,
                'posts_per_page'   => $per_page,
                'paged'            => max(1, absint(get_query_var('paged') ?: ($_GET['paged'] ?? 1))),
            );
        } else {
            $default = array(
                'orderby'          => 'meta_value',
                'meta_key'         => 'event_start_time_stamp',
                'meta_type'        => 'DATETIME',
                'order'            => 'ASC',
                'post_type'        => 'sub_event',
                'suppress_filters' => true,
                'posts_per_page'   => -1,
            );
        }

        $args = wp_parse_args($args, $default);
        $args = apply_filters('mindevents_front_list_query_args', $args, $this, $calDate);
        $args = $this->apply_visible_period_to_query_args($args, 'list');

        if ($use_frontend_period && $this->get_frontend_view() === 'list') {
            $args['meta_query'][] = array(
                'key'     => 'event_start_time_stamp',
                'value'   => $current_time,
                'compare' => '>=',
                'type'    => 'DATETIME',
            );
        }

        $list_query = new WP_Query($args);
        $this->last_front_list_query = $list_query;
        $eventDates = $list_query->posts;

        if ($eventDates) {
            foreach ($eventDates as $index => $event) {
                $display_link = true;
                $startDate    = get_post_meta($event->ID, 'event_start_time_stamp', true);
                $this->addDailyHtml($this->get_list_item_html($event->ID, $display_link), $startDate);
            }

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
            $out .= '<h2 class="mindevents-list-month">' . esc_html($period['start']->format('F Y')) . '</h2>';
        }

        foreach ($this->dailyHtml as $year => $year_items) {
            foreach ($year_items as $month => $month_items) {
                $out .= '<h2 class="mindevents-list-month">' . esc_html(date_i18n('F Y', mktime(0, 0, 0, (int) $month, 1, (int) $year))) . '</h2>';
                foreach ($month_items as $day => $daily_items) {
                    $date_label = date_i18n($this->date_format, mktime(0, 0, 0, (int) $month, (int) $day, (int) $year));
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
        $meta       = get_post_meta($event);
        $parent_id  = (int) wp_get_post_parent_id($event);
        $title      = $this->get_occurrence_title($event);
        $permalink  = $parent_id ? get_permalink($parent_id) : get_permalink($event);
        $start      = $meta['event_start_time_stamp'][0] ?? '';
        $end        = $meta['event_end_time_stamp'][0] ?? '';
        $date_label = $this->format_date_range($start, $end);
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
        $date_label  = $this->format_date_range(
            get_post_meta($event, 'event_start_time_stamp', true),
            get_post_meta($event, 'event_end_time_stamp', true)
        );
        $excerpt   = mindevents_get_occurrence_excerpt($event);
        $location  = mindevents_get_occurrence_location($event);
        $organizer = $this->get_organizer_block($event);

        $html  = '<article class="mindevents-event-meta">';
        $html .= $this->get_event_color_bar($event);
        $html .= '<button type="button" class="event-meta-close" aria-label="' . esc_attr__('Close event details', 'simple-events') . '">&times;</button>';

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
            'post_type'      => 'sub_event',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post_parent'    => $this->eventID,
        ));

        if ($eventDates) {
            foreach ($eventDates as $event) {
                $start = get_post_meta($event->ID, 'event_start_time_stamp', true);
                $end   = get_post_meta($event->ID, 'event_end_time_stamp', true);
                $html  = '<div class="mindevents-admin-occurrence" data-startdate="' . esc_attr($start) . '" data-enddate="' . esc_attr($end) . '">';
                $html .= $this->get_event_color_bar($event->ID);
                $html .= '<div class="mindevents-admin-occurrence__actions">';
                $html .= '<button type="button" class="mindevents-admin-occurrence__edit" data-subid="' . esc_attr($event->ID) . '">' . esc_html($this->format_time_range($start, $end)) . '</button>';
                $html .= '<button type="button" class="mindevents-admin-occurrence__delete" data-subid="' . esc_attr($event->ID) . '" aria-label="' . esc_attr__('Remove occurrence', 'simple-events') . '">&times;</button>';
                $html .= '</div>';
                $html .= '</div>';
                $this->addDailyHtml($html, $start, $end);
            }
        }

        return $this->render();
    }

    public function update_sub_event($sub_event, $meta, $parentID) {
        $sub_event = absint($sub_event);
        $parentID  = absint($parentID);
        if (!$sub_event || !$parentID) {
            return;
        }

        $meta = $this->sanitize_sub_event_meta($meta, $parentID);
        $meta['unique_event_key'] = $this->build_unique_key($parentID, $meta['event_start_time_stamp'], $meta);

        foreach ($meta as $key => $value) {
            update_post_meta($sub_event, $key, $value);
        }

        mindevents_apply_visibility_meta($sub_event, $meta['mindevents_visibility']);

        wp_update_post(array(
            'ID'         => $sub_event,
            'post_title' => $this->build_title($parentID, $meta['event_date'], $meta),
        ));

        mindevents_sync_event_date_range($parentID);
    }

    public function add_sub_event($date, $meta, $eventID, $args = array()) {
        $eventID = absint($eventID);
        if (!$eventID) {
            return false;
        }

        $meta   = $this->sanitize_sub_event_meta($meta, $eventID, $date);
        $unique = $this->build_unique_key($eventID, $date, $meta);

        $check_query = new WP_Query(array(
            'fields'         => 'ids',
            'post_type'      => 'sub_event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'   => 'unique_event_key',
                    'value' => $unique,
                ),
            ),
        ));

        if ($check_query->have_posts()) {
            return false;
        }

        $terms                = wp_get_post_terms($eventID, 'event_category', array('fields' => 'ids'));
        $meta['unique_event_key'] = $unique;

        $defaults = array(
            'post_author'  => (int) get_post_field('post_author', $eventID),
            'post_title'   => $this->build_title($eventID, $date, $meta),
            'post_status'  => 'publish',
            'post_type'    => 'sub_event',
            'post_parent'  => $eventID,
            'meta_input'   => $meta,
            'tax_input'    => array(
                'event_category' => $terms,
            ),
        );

        $post_args = wp_parse_args($args, $defaults);
        $post_id   = wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        mindevents_apply_visibility_meta($post_id, $meta['mindevents_visibility']);
        mindevents_sync_event_date_range($eventID);

        return $post_id;
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

    public function get_archive_url() {
        return get_post_type_archive_link('events');
    }

    public function generate_schema() {
        if (!$this->eventID || get_post_type($this->eventID) !== 'events' || mindevents_is_internal_post($this->eventID)) {
            return '';
        }

        $sub_events = $this->get_sub_events();
        $location   = mindevents_get_occurrence_location($this->eventID);
        $organizer  = mindevents_get_organizer_data($this->eventID);

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => get_the_title($this->eventID),
            'startDate'   => get_post_meta($this->eventID, 'first_event_date', true),
            'endDate'     => get_post_meta($this->eventID, 'last_event_date', true),
            'description' => wp_strip_all_tags(get_the_excerpt($this->eventID)),
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
                    'name'        => $this->get_occurrence_title($event->ID),
                    'startDate'   => get_post_meta($event->ID, 'event_start_time_stamp', true),
                    'endDate'     => get_post_meta($event->ID, 'event_end_time_stamp', true),
                    'description' => wp_strip_all_tags(mindevents_get_occurrence_excerpt($event->ID)),
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

        return wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function parseDate($date = null) {
        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        if (is_int($date)) {
            return (new DateTimeImmutable('now', mindevents_wp_timezone()))->setTimestamp($date);
        }

        if (is_string($date) && $date !== '') {
            return new DateTimeImmutable($date, mindevents_wp_timezone());
        }

        return null;
    }

    private function get_calendar_reference_date() {
        if (!($this->now instanceof DateTimeInterface)) {
            $this->setDate(current_time('mysql'));
        }

        return ($this->now instanceof DateTimeImmutable)
            ? $this->now
            : new DateTimeImmutable($this->now->format('Y-m-d H:i:s'), $this->now->getTimezone());
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

        $period       = $this->get_visible_period($view);
        $period_start = $period['start']->format('Y-m-d H:i:s');
        $period_end   = $period['end']->format('Y-m-d H:i:s');

        if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
            $args['meta_query'] = array();
        }

        $args['meta_query'][] = array(
            'key'     => 'event_start_time_stamp',
            'value'   => array($period_start, $period_end),
            'compare' => 'BETWEEN',
            'type'    => 'DATETIME',
        );

        return $args;
    }

    private function get_week_display_label(DateTimeInterface $period_start, DateTimeInterface $period_end) {
        if ($period_start->format('F Y') === $period_end->format('F Y')) {
            return $period_start->format('F j') . ' - ' . $period_end->format('j, Y');
        }

        return $period_start->format('F j, Y') . ' - ' . $period_end->format('F j, Y');
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

    private function weekdays() {
        if ($this->weekDayNames !== null) {
            return $this->weekDayNames;
        }

        $days = array();
        $base = new DateTimeImmutable('monday this week', mindevents_wp_timezone());

        for ($index = 0; $index < 7; $index++) {
            $days[] = $base->modify('+' . $index . ' days')->format('l');
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
        $start = get_post_meta($event_id, 'event_start_time_stamp', true);
        if (!$start || !($this->today instanceof DateTimeInterface)) {
            return false;
        }

        $start_dt = new DateTimeImmutable($start, $this->today->getTimezone());

        return $start_dt < $this->today;
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
        $colors = mindevents_get_post_category_colors($eventID);

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

    private function format_time_range($start, $end) {
        if (!$start) {
            return '';
        }

        $start_dt = new DateTimeImmutable($start, mindevents_wp_timezone());

        if (!$end) {
            return $start_dt->format($this->time_format);
        }

        $end_dt = new DateTimeImmutable($end, mindevents_wp_timezone());

        return $start_dt->format($this->time_format) . ' - ' . $end_dt->format($this->time_format);
    }

    private function format_hour_label($hour) {
        $normalized = ((int) $hour) % 24;
        $date       = new DateTimeImmutable(sprintf('2000-01-01 %02d:00:00', $normalized), mindevents_wp_timezone());

        return $date->format('g a');
    }

    private function build_week_events_by_day($events, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd) {
        $eventsByDay = array();

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $weekStart->modify('+' . $dayOffset . ' days');
            $eventsByDay[$date->format('Y-m-d')] = array();
        }

        foreach ((array) $events as $event) {
            $event_id  = (int) ($event->ID ?? 0);
            $start_raw = get_post_meta($event_id, 'event_start_time_stamp', true);
            $end_raw   = get_post_meta($event_id, 'event_end_time_stamp', true);

            if (!$event_id || !$start_raw || !$end_raw) {
                continue;
            }

            try {
                $start = new DateTimeImmutable($start_raw, mindevents_wp_timezone());
                $end   = new DateTimeImmutable($end_raw, mindevents_wp_timezone());
            } catch (Throwable $exception) {
                continue;
            }

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
                        'id'     => $event_id,
                        'title'  => $this->get_occurrence_title($event_id),
                        'time'   => $this->format_time_range($daySegmentStart->format('Y-m-d H:i:s'), $daySegmentEnd->format('Y-m-d H:i:s')),
                        'color'  => $this->get_primary_event_color($event_id),
                        'top'    => ($startMinutes / 1440) * 100,
                        'height' => max((($endMinutes - $startMinutes) / 1440) * 100, (30 / 1440) * 100),
                    );
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($eventsByDay as $dayKey => $dayEvents) {
            usort($dayEvents, function($left, $right) {
                if ($left['top'] === $right['top']) {
                    return $right['height'] <=> $left['height'];
                }

                return $left['top'] <=> $right['top'];
            });

            $eventsByDay[$dayKey] = $dayEvents;
        }

        return $eventsByDay;
    }

    private function format_date_range($start, $end) {
        if (!$start) {
            return '';
        }

        $start_dt = new DateTimeImmutable($start, mindevents_wp_timezone());

        if (!$end) {
            return $start_dt->format($this->date_format . ' ' . $this->time_format);
        }

        $end_dt = new DateTimeImmutable($end, mindevents_wp_timezone());

        if ($start_dt->format('Y-m-d') === $end_dt->format('Y-m-d')) {
            return $start_dt->format($this->date_format) . ' · ' . $start_dt->format($this->time_format) . ' - ' . $end_dt->format($this->time_format);
        }

        return $start_dt->format($this->date_format . ' ' . $this->time_format) . ' - ' . $end_dt->format($this->date_format . ' ' . $this->time_format);
    }

    private function sanitize_sub_event_meta($meta, $parentID, $date = '') {
        $meta = is_array($meta) ? $meta : array();

        $event_date = $date ? sanitize_text_field((string) $date) : sanitize_text_field((string) ($meta['event_date'] ?? ''));
        if ($event_date === '') {
            $existing_start = $meta['event_start_time_stamp'] ?? '';
            if ($existing_start) {
                $event_date = (new DateTimeImmutable($existing_start, mindevents_wp_timezone()))->format('Y-m-d');
            }
        }

        $starttime = mindevents_normalize_time_value($meta['starttime'] ?? '', $this->default_time_from_parent($parentID, 'starttime'));
        $endtime   = mindevents_normalize_time_value($meta['endtime'] ?? '', $this->default_time_from_parent($parentID, 'endtime'));

        $start_dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $event_date . ' ' . $starttime, mindevents_wp_timezone());
        $end_dt   = DateTimeImmutable::createFromFormat('Y-m-d H:i', $event_date . ' ' . $endtime, mindevents_wp_timezone());

        if (!($start_dt instanceof DateTimeImmutable)) {
            throw new InvalidArgumentException('Invalid occurrence start date.');
        }

        if (!($end_dt instanceof DateTimeImmutable)) {
            $end_dt = $start_dt;
        }

        return array(
            'event_date'                 => $event_date,
            'starttime'                  => $starttime,
            'endtime'                    => $endtime,
            'event_start_time_stamp'     => $start_dt->format('Y-m-d H:i:s'),
            'event_end_time_stamp'       => $end_dt->format('Y-m-d H:i:s'),
            'eventColor'                 => sanitize_hex_color($meta['eventColor'] ?? '') ?: '',
            'eventDescription'           => wp_kses_post($meta['eventDescription'] ?? ''),
            'mindevents_location'        => sanitize_text_field((string) ($meta['mindevents_location'] ?? '')),
            'mindevents_visibility'      => mindevents_sanitize_visibility($meta['mindevents_visibility'] ?? mindevents_get_post_visibility($parentID)),
            'mindevents_organizer_name'  => sanitize_text_field((string) ($meta['mindevents_organizer_name'] ?? '')),
            'mindevents_organizer_title' => sanitize_text_field((string) ($meta['mindevents_organizer_title'] ?? '')),
            'mindevents_organizer_image_id' => absint($meta['mindevents_organizer_image_id'] ?? 0),
        );
    }

    private function default_time_from_parent($parentID, $key) {
        $defaults = get_post_meta($parentID, 'event_defaults', true);
        if (!is_array($defaults) || empty($defaults)) {
            $defaults = get_post_meta($parentID, 'defaults', true);
        }
        $defaults = is_array($defaults) ? $defaults : array();

        if (!empty($defaults[$key])) {
            return mindevents_normalize_time_value($defaults[$key]);
        }

        $options = get_option(MINDEVENTS_PREPEND . 'support_settings', array());
        $option_key = MINDEVENTS_PREPEND . (($key === 'endtime') ? 'end_time' : 'start_time');

        return $options[$option_key] ?? (($key === 'endtime') ? '21:00' : '19:00');
    }

    private function build_unique_key($eventID, $date = '', $times = array()) {
        return sanitize_title($eventID . '_' . $date . '_' . ($times['event_start_time_stamp'] ?? '') . '-' . ($times['event_end_time_stamp'] ?? ''));
    }

    private function build_title($parentID, $date = '', $times = array()) {
        $title = get_the_title($parentID) . ' | ' . $date . ' | ' . ($times['starttime'] ?? '') . '-' . ($times['endtime'] ?? '');

        return apply_filters('mind_events_title', $title, $date, $times, $this);
    }
}
