<?php


class mindEventCalendar
{

  private $eventID;
  private $wp_post;

  private $displayType;
  private $calendar_start_day;
  private $currency_symbol;
  private $options;
  private $show_past_events;
  private $next_month;

  private $weekDayNames;
  private $now;
  private $today;
  private $all_events;

  private $event_categories = false;

  private $is_archive = false;

  private $date_format;
  private $time_format;


  private $classes = [
    'calendar' => 'mindEventCalendar',
    'leading_day' => 'SCprefix d-none d-md-block day-container',
    'trailing_day' => 'SCsuffix d-none d-md-block day-container',
    'today' => 'today',
    'event' => 'event',
    'events' => 'events',
    'past' => 'past-date opacity-50',
  ];

  private $dailyHtml = [];
  private $offset = 0;
  private $last_front_list_query = null;

  function __construct($id = '', $calendarDate = null, $today = null)
  {

    $this->setToday($today);
    $this->setCalendarClasses();

    if ($id == 'archive'):
      $this->all_events = $this->get_all_events();
    endif;

    // Allow calendar_date from query string if present
    if (isset($_GET['calendar_date'])) {
      $calendarDate = sanitize_text_field($_GET['calendar_date']);
    }

    if ($calendarDate):
      $this->setDate($calendarDate);
    elseif (get_post($id)):
      $this->setDate(get_post_meta($id, 'first_event_date', true));
    endif;

    $this->options = get_option(MINDEVENTS_PREPEND . 'support_settings');
    $this->eventID = $id;
    $this->wp_post = get_post($id);

    $this->show_past_events = true;

    $date = new DateTime('now');
    $date->modify('first day of next month');
    $this->next_month = $date->format('m');
    $this->time_format = get_option('time_format');
    $this->date_format = get_option('date_format');

    $this->currency_symbol = (isset($this->options[MINDEVENTS_PREPEND . 'currency_symbol']) ? $this->options[MINDEVENTS_PREPEND . 'currency_symbol'] : '$');
    $this->calendar_start_day = (isset($this->options[MINDEVENTS_PREPEND . 'start_day']) ? $this->options[MINDEVENTS_PREPEND . 'start_day'] : 'Monday');
  }
  private function define($name, $value)
  {
    if (!defined($name)) {
      define($name, $value);
    }
  }

  /**
   * Sets the date for the calendar.
   *
   * @param \DateTimeInterface|int|string|null $date DateTimeInterface or Date string parsed by strtotime for the
   *     calendar date. If null set to current timestamp.
   */
  public function setDate($date = null)
  {
    $this->now = $this->parseDate($date) ?: new \DateTimeImmutable();
  }

  public function getDate()
  {
    return $this->now;
  }

  /**
   * @param \DateTimeInterface|int|string|null $date
   * @return \DateTimeInterface|null
   */
  private function parseDate($date = null)
  {
    if ($date instanceof \DateTimeInterface) {
      return $date;
    }
    if (is_int($date)) {
      return (new \DateTimeImmutable())->setTimestamp($date);
    }
    if (is_string($date)) {
      return new \DateTimeImmutable($date);
    }

    return null;
  }

  /**
   * Sets the class names used in the calendar
   *
   * ```php
   * [
   *    'calendar'     => 'mindEventsCalendar',
   *    'leading_day'  => 'SCprefix',
   *    'trailing_day' => 'SCsuffix',
   *    'today'        => 'today',
   *    'event'        => 'event',
   *    'events'       => 'events',
   * ]
   * ```
   *
   * @param array $classes Map of element to class names used by the calendar.
   */
  public function setCalendarClasses(array $classes = [])
  {
    foreach ($classes as $key => $value) {
      if (!isset($this->classes[$key])) {
        throw new \InvalidArgumentException("class '{$key}' not supported");
      }

      $this->classes[$key] = $value;
    }
  }


  public function setEventCategories($array = array())
  {
    $this->event_categories = $array;
  }

  /**
   * Sets "today"'s date. Defaults to today.
   *
   * @param \DateTimeInterface|false|string|null $today `null` will default to today, `false` will disable the
   *     rendering of Today.
   */
  public function setToday($today = null)
  {
    
    if ($today === false) {
      $this->today = null;
    } elseif ($today === null) {
      // Use WordPress timezone for 'today' (no double shifting)
      if (function_exists('wp_timezone')) {
        $timezone = wp_timezone();
      } else {
        $timezone = new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
      }
      $mysql_now = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
      $this->today = new \DateTimeImmutable($mysql_now, $timezone);
    } else {
      $this->today = $this->parseDate($today);
    }
  }

  /**
   * @param string[]|null $weekDayNames
   */
  public function setWeekDayNames(array $weekDayNames = null)
  {
    if (is_array($weekDayNames) && count($weekDayNames) !== 7) {
      throw new \InvalidArgumentException('week array must have exactly 7 values');
    }

    $this->weekDayNames = $weekDayNames ? array_values($weekDayNames) : null;
  }

  /**
   * Add a daily event to the calendar
   *
   * @param string                             $html The raw HTML to place on the calendar for this event
   * @param \DateTimeInterface|int|string      $startDate Date string for when the event starts
   * @param \DateTimeInterface|int|string|null $endDate Date string for when the event ends. Defaults to start date
   */
  public function addDailyHtml($html, $startDate, $endDate = null)
  {
    static $htmlCount = 0;

    $start = $this->parseDate($startDate);
    if (!$start) {
      throw new \InvalidArgumentException('invalid start time');
    }

    $end = $start;
    if ($endDate) {
      $end = $this->parseDate($endDate);
    }
    if (!$end) {
      throw new \InvalidArgumentException('invalid end time');
    }

    if ($end->getTimestamp() < $start->getTimestamp()) {
      throw new \InvalidArgumentException('end must come after start');
    }

    $working = (new \DateTimeImmutable())->setTimestamp($start->getTimestamp());
    do {
      $tDate = getdate($working->getTimestamp());

      $this->dailyHtml[$tDate['year']][$tDate['mon']][$tDate['mday']][$htmlCount] = $html;

      $working = $working->add(new \DateInterval('P1D'));
    } while ($working->getTimestamp() < $end->getTimestamp() + 1);

    $htmlCount++;
  }

  /**
   * Clear all daily events for the calendar
   */
  public function clearDailyHtml()
  {
    $this->dailyHtml = [];
  }


  /**
   * Sets the first day of the week
   *
   * @param int|string $offset Day the week starts on. ex: "Monday" or 0-6 where 0 is Sunday
   */
  public function setStartOfWeek($offset)
  {
    if (is_int($offset)) {
      $this->offset = $offset % 7;
    } elseif ($this->weekDayNames !== null && ($weekOffset = array_search($offset, $this->weekDayNames, true)) !== false) {
      $this->offset = $weekOffset;
    } else {
      $weekTime = strtotime($offset);
      if ($weekTime === 0) {
        throw new \InvalidArgumentException('invalid offset');
      }

      $this->offset = date('N', $weekTime) % 7;
    }
  }

  /**
   * Summary of inject_event_html
   * @param mixed $year
   * @param mixed $month
   * @param mixed $day
   * @param mixed $html
   * @return void
   */
  public function inject_event_html($year, $month, $day, $html) {
      $htmlCount = count($this->dailyHtml[$year][$month][$day] ?? []);
      $this->dailyHtml[$year][$month][$day][$htmlCount] = $html;
  }

  private function get_calendar_reference_date()
  {
    if (!($this->now instanceof \DateTimeInterface)) {
      $fallback = function_exists('current_time') ? current_time('mysql') : 'now';
      $this->setDate($fallback);
    }

    if ($this->now instanceof \DateTimeImmutable) {
      return $this->now;
    }

    return new \DateTimeImmutable($this->now->format('Y-m-d H:i:s'), $this->now->getTimezone());
  }

  private function get_frontend_filters_state()
  {
    if (function_exists('mindevents_get_frontend_filters')) {
      $filters = mindevents_get_frontend_filters();
      if (is_array($filters)) {
        return $filters;
      }
    }

    return array();
  }

  private function should_use_frontend_visible_period()
  {
    $filters = $this->get_frontend_filters_state();
    return !empty($filters['apply']);
  }

  private function get_frontend_view()
  {
    if (!$this->should_use_frontend_visible_period()) {
      return 'month';
    }

    $filters = $this->get_frontend_filters_state();
    $view = isset($filters['event_view']) ? sanitize_key((string) $filters['event_view']) : 'month';

    return in_array($view, array('month', 'week', 'list'), true) ? $view : 'month';
  }

  private function get_visible_period($view = null)
  {
    $this->setStartOfWeek($this->calendar_start_day);

    $view = $view ?: $this->get_frontend_view();
    $current = $this->get_calendar_reference_date();
    $current = new \DateTimeImmutable($current->format('Y-m-d H:i:s'), $current->getTimezone());

    if ($view === 'week') {
      $period_start = $current->setTime(0, 0, 0);
      $days_to_subtract = (((int) $period_start->format('w')) - $this->offset + 7) % 7;
      if ($days_to_subtract > 0) {
        $period_start = $period_start->modify('-' . $days_to_subtract . ' days');
      }
      $period_end = $period_start->modify('+6 days')->setTime(23, 59, 59);
    } else {
      $period_start = $current->modify('first day of this month')->setTime(0, 0, 0);
      $period_end = $period_start->modify('last day of this month')->setTime(23, 59, 59);
    }

    return array(
      'view'  => $view,
      'start' => $period_start,
      'end'   => $period_end,
    );
  }

  private function apply_visible_period_to_query_args($args, $view = null)
  {
    if (!$this->should_use_frontend_visible_period()) {
      return $args;
    }

    $period = $this->get_visible_period($view);
    $period_start = $period['start']->format('Y-m-d H:i:s');
    $period_end = $period['end']->format('Y-m-d H:i:s');

    if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
      $args['meta_query'] = array();
    }

    if (!isset($args['meta_query']['relation'])) {
      $args['meta_query']['relation'] = 'AND';
    }

    if ($period['view'] === 'list') {
      $args['meta_query'][] = array(
        'key'     => 'event_start_time_stamp',
        'value'   => $period_start,
        'compare' => '>=',
        'type'    => 'DATETIME',
      );
      $args['meta_query'][] = array(
        'key'     => 'event_start_time_stamp',
        'value'   => $period_end,
        'compare' => '<=',
        'type'    => 'DATETIME',
      );
    } else {
      $args['meta_query'][] = array(
        'key'     => 'event_start_time_stamp',
        'value'   => $period_end,
        'compare' => '<=',
        'type'    => 'DATETIME',
      );
      $args['meta_query'][] = array(
        'key'     => 'event_end_time_stamp',
        'value'   => $period_start,
        'compare' => '>=',
        'type'    => 'DATETIME',
      );
    }

    return $args;
  }

  private function get_week_display_label(\DateTimeInterface $period_start, \DateTimeInterface $period_end)
  {
    if ($period_start->format('F Y') === $period_end->format('F Y')) {
      return $period_start->format('F j') . ' - ' . $period_end->format('j, Y');
    }

    if ($period_start->format('Y') === $period_end->format('Y')) {
      return $period_start->format('F j') . ' - ' . $period_end->format('F j, Y');
    }

    return $period_start->format('F j, Y') . ' - ' . $period_end->format('F j, Y');
  }

  /**
   * Returns the generated Calendar
   *
   * @return string
   */
  public function render(){
    // Allow injection of additional events into the dailyHtml array
    $this->dailyHtml = apply_filters(
        'mindevents_calendar_daily_html',
        $this->dailyHtml,
        $this
    );
    $out = '';
    if (!is_admin()):
      $out .= $this->get_calendar_nav_links();
    endif;
    $referenceDate = $this->get_calendar_reference_date();
    $now = getdate($referenceDate->getTimestamp());
    $today = ['mday' => -1, 'mon' => -1, 'year' => -1];
    if ($this->today !== null) {
      $today = getdate($this->today->getTimestamp());
    }

    $daysOfWeek = $this->weekdays();
    $this->rotate($daysOfWeek, $this->offset);

    $weekDayIndex = date('N', mktime(0, 0, 1, $now['mon'], 1, $now['year'])) - $this->offset;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $now['mon'], $now['year']);

    // Bootstrap 5 flexbox-based calendar rendering
    $out .= '<h4 class="month-display">' . $now['month'] . ' ' . $now['year'] . '</h4>';
    $out .= '<div id="mindEventCalendar" class="container-fluid ' . $this->classes['calendar'] . '" data-month="' . $now['mon'] . '" data-year="' . $now['year'] . '">';
    $out .= '<div class="row text-center calendar-header fw-bold border-bottom pb-2 d-none d-md-flex">';
    foreach ($daysOfWeek as $dayName) {
      $out .= '<div class="col day-name">' . $dayName . '</div>';
    }
    $out .= '</div>';

    $weekDayIndex = ($weekDayIndex + 7) % 7;
    $count = $weekDayIndex;
    $out .= '<div class="row justify-content-center week-row">';
    for ($i = 0; $i < $weekDayIndex; $i++) {
      $out .= '<div class="col border ' . $this->classes['leading_day'] . '">&nbsp;</div>';
    }
    for ($i = 1; $i <= $daysInMonth; $i++) {
      $date = (new \DateTimeImmutable($referenceDate->format('Y-m-d H:i:s'), $referenceDate->getTimezone()))->setDate($now['year'], $now['mon'], $i)->setTime(0, 0, 0);
      $isToday = $i == $today['mday'] && $today['mon'] == $date->format('n') && $today['year'] == $date->format('Y');
      // Compare to the minute for past status
      $nowMinute = $this->today ? $this->today->format('Y-m-d H:i') : null;
      $dateMinute = $date->format('Y-m-d H:i');
      $isPast = $this->today && $nowMinute > $dateMinute;

      // Responsive column classes: col-12 col-sm border p-2
      $classes = 'col-12 col-md border p-1 day-container ';
      if ($isToday)
        $classes .= $this->classes['today'] . ' ';
      if ($isPast)
        $classes .= $this->classes['past'] . ' ';

      $out .= '<div class="' . trim($classes) . '" data-date="' . $date->format('Y-m-d') . '">';
      // Add mobile weekday+date display
      $dayName = $date->format('l'); // Full weekday name
      $monthName = $date->format('F'); // Full month name


      if (isset($this->dailyHtml[$now['year']][$now['mon']][$i])):
        $out .= '<div class="mobile-day-name d-block d-md-none small fw-bold mb-1">' . $dayName . ', ' . $monthName . ' ' . $i . '</div>';
      endif;
      $out .= sprintf('<time class="d-none d-md-block calendar-day " datetime="%s">%d</time>', $date->format('Y-m-d'), $i);

      if (isset($this->dailyHtml[$now['year']][$now['mon']][$i])) {
        $out .= '<div class="events">';
        foreach ($this->dailyHtml[$now['year']][$now['mon']][$i] as $dHtml) {
          $out .= $dHtml;
        }
        $out .= '</div>';
      }

      $out .= '</div>';
      $count++;
      if ($count % 7 === 0 && $i !== $daysInMonth) {
        $out .= '</div><div class="row justify-content-center week-row">';
      }
    }
    for ($i = $count % 7; $i < 7 && $i !== 0; $i++) {
      $out .= '<div class="col border ' . $this->classes['trailing_day'] . '">&nbsp;</div>';
    }
    $out .= '</div></div>';


    return $out;
  }

  public function renderWeek()
  {
    $this->dailyHtml = apply_filters(
        'mindevents_calendar_daily_html',
        $this->dailyHtml,
        $this
    );

    $period = $this->get_visible_period('week');
    $weekStart = $period['start'];
    $weekEnd = $period['end'];
    $today = array('mday' => -1, 'mon' => -1, 'year' => -1);
    if ($this->today !== null) {
      $today = getdate($this->today->getTimestamp());
    }

    $daysOfWeek = $this->weekdays();
    $this->rotate($daysOfWeek, $this->offset);

    $out = '';
    if (!is_admin()) {
      $out .= $this->get_calendar_nav_links('week');
    }

    $out .= '<h4 class="month-display">' . esc_html($this->get_week_display_label($weekStart, $weekEnd)) . '</h4>';
    $out .= '<div id="mindEventCalendar" class="container-fluid ' . $this->classes['calendar'] . ' is-week-view" data-month="' . esc_attr($weekStart->format('n')) . '" data-year="' . esc_attr($weekStart->format('Y')) . '" data-view="week">';
    $out .= '<div class="row text-center calendar-header fw-bold border-bottom pb-2 d-none d-md-flex">';
    foreach ($daysOfWeek as $dayName) {
      $out .= '<div class="col day-name">' . esc_html($dayName) . '</div>';
    }
    $out .= '</div>';

    $out .= '<div class="row justify-content-center week-row">';
    for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
      $date = $weekStart->modify('+' . $dayOffset . ' days');
      $year = (int) $date->format('Y');
      $month = (int) $date->format('n');
      $day = (int) $date->format('j');
      $isToday = $day === (int) $today['mday'] && $month === (int) $today['mon'] && $year === (int) $today['year'];
      $nowMinute = $this->today ? $this->today->format('Y-m-d H:i') : null;
      $dateMinute = $date->format('Y-m-d H:i');
      $isPast = $this->today && $nowMinute > $dateMinute;

      $classes = 'col-12 col-md border p-1 day-container ';
      if ($isToday) {
        $classes .= $this->classes['today'] . ' ';
      }
      if ($isPast) {
        $classes .= $this->classes['past'] . ' ';
      }

      $out .= '<div class="' . trim($classes) . '" data-date="' . esc_attr($date->format('Y-m-d')) . '">';
      if (isset($this->dailyHtml[$year][$month][$day])) {
        $out .= '<div class="mobile-day-name d-block d-md-none small fw-bold mb-1">' . esc_html($date->format('l, F j')) . '</div>';
      }
      $out .= '<time class="d-none d-md-block calendar-day week-calendar-day" datetime="' . esc_attr($date->format('Y-m-d')) . '">' . esc_html($date->format('M j')) . '</time>';

      if (isset($this->dailyHtml[$year][$month][$day])) {
        $out .= '<div class="events">';
        foreach ($this->dailyHtml[$year][$month][$day] as $dHtml) {
          $out .= $dHtml;
        }
        $out .= '</div>';
      }

      $out .= '</div>';
    }
    $out .= '</div></div>';

    return $out;
  }

  /**
   * Generate Previous/Next month navigation links that update the query string.
   */
  public function get_calendar_nav_links($view = null)
  {
    $view = $view ?: $this->get_frontend_view();
    $currentDate = $this->get_calendar_reference_date();

    if ($view === 'week') {
      $prevDate = $currentDate->modify('-7 days');
      $nextDate = $currentDate->modify('+7 days');
      $prevLabel = 'Prev Week';
      $nextLabel = 'Next Week';
    } else {
      $prevDate = $currentDate->modify('first day of previous month');
      $nextDate = $currentDate->modify('first day of next month');
      $prevLabel = date('F', strtotime($prevDate->format('Y-m-d')));
      $nextLabel = date('F', strtotime($nextDate->format('Y-m-d')));
    }

    $currentUrl = home_url(strtok($_SERVER["REQUEST_URI"], '?'));
    $prevArgs   = array('calendar_date' => $prevDate->format('Y-m-d'));
    $nextArgs   = array('calendar_date' => $nextDate->format('Y-m-d'));
    if (function_exists('mindevents_get_frontend_filter_query_args')) {
      $prevArgs = mindevents_get_frontend_filter_query_args(null, array('calendar_date' => $prevDate->format('Y-m-d')), array('paged'));
      $nextArgs = mindevents_get_frontend_filter_query_args(null, array('calendar_date' => $nextDate->format('Y-m-d')), array('paged'));
    }

    $out = '<div class="calendar-nav d-flex justify-content-between mb-3 w-100">';
    $out .= '<a href="' . esc_url(add_query_arg($prevArgs, $currentUrl)) . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-chevron-left"></i> ' . esc_html($prevLabel) . '</a>';
    $out .= '<a href="' . esc_url(add_query_arg($nextArgs, $currentUrl)) . '" class="btn btn-sm btn-outline-primary">' . esc_html($nextLabel) . ' <i class="fas fa-chevron-right"></i></a>';
    $out .= '</div>';

    return $out;
  }


  public function get_calendar_start_date() {
    return $this->now;
  }
  /**
   * @param int $steps
   */
  private function rotate(array &$data, $steps) {
    $count = count($data);
    if ($steps < 0) {
      $steps = $count + $steps;
    }
    $steps %= $count;
    for ($i = 0; $i < $steps; $i++) {
      $data[] = array_shift($data);
    }
  }

  /**
   * @return string[]
   */
  private function weekdays(){
    if ($this->weekDayNames !== null) {
      $wDays = $this->weekDayNames;
    } else {
      $today = (86400 * (date('N')));
      $wDays = [];
      for ($n = 0; $n < 7; $n++) {
        $wDays[] = date('l', time() - $today + ($n * 86400));
      }
    }

    return $wDays;
  }


  public function set_past_events_display($display){
    if (is_string($display)):
      if ($display === '1') {
        $display = true;
      }
      if ($display === '0') {
        $display = false;
      }
    endif;

    if ($display === false) {
      $this->show_past_events = false;
    } else {
      $this->show_past_events = true;
    }
  }


  public function get_all_events($args = array()){

    $defaults = array(
      'meta_query' => array(
        // 'relation' => 'AND',
        'start_clause' => array(
          'key' => 'event_start_time_stamp',
          'compare' => 'EXISTS',
        ),
      ),
      'orderby' => 'meta_value',
      'meta_key' => 'event_start_time_stamp',
      'meta_type' => 'DATETIME',
      'order' => 'ASC',
      'post_type' => 'sub_event',
      'suppress_filters' => true,
      'posts_per_page' => -1
    );
    if ($this->show_past_events == false) {
      $args['meta_query'][] = array(
        'key' => 'event_start_time_stamp', // Check the start date field
        'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
        'compare' => '>=', // Return the ones greater than today's date
        'type' => 'DATETIME' // Let WordPress know we're working with date
      );
    }

    if ($this->event_categories) {
      $args['tax_query'] = array(
        array(
          'taxonomy' => 'event_category',
          'field' => 'slug',
          'terms' => $this->event_categories,
        ),
      );
    }

    $args = wp_parse_args($args, $defaults);
    return get_posts($args);

  }

  public function get_sub_events($args = array()){
    $passed_meta_query = array();
    if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
      $passed_meta_query = $args['meta_query'];
      unset($args['meta_query']);
    }

    $defaults = array(
      'meta_query' => array(
          'relation' => 'AND',
          array(
              'key' => 'event_start_time_stamp',
              'compare' => 'EXISTS',
          ),
          array(
              'relation' => 'OR',
              array(
                  'key' => '_members_only',
                  'compare' => 'NOT EXISTS'
              ),
              array(
                  'key' => '_members_only',
                  'value' => '1',
                  'compare' => '!='
              ),
          ),
      ),
      'orderby' => 'meta_value',
      'meta_key' => 'event_start_time_stamp',
      'meta_type' => 'DATETIME',
      'order' => 'ASC',
      'post_type' => 'sub_event',
      'post_parent' => $this->eventID,
      'suppress_filters' => true,
      'posts_per_page' => -1
    );


    if ($this->show_past_events === false) {
      $passed_meta_query[] = array(
        'key' => 'event_start_time_stamp', // Check the start date field
        'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
        'compare' => '>=', // Return the ones greater than today's date
        'type' => 'DATETIME' // Let WordPress know we're working with date
      );
    }
    if ($this->event_categories) {
      $args['tax_query'] = array(
        array(
          'taxonomy' => 'event_category',
          'field' => 'slug',
          'terms' => $this->event_categories,
        ),
      );
    }

    if (is_admin()):
      unset($defaults['post_parent']);
    endif;

    $args = wp_parse_args($args, $defaults);
    if (!empty($passed_meta_query)) {
      $meta_query = $defaults['meta_query'];
      if (isset($passed_meta_query['relation'])) {
        $meta_query['relation'] = $passed_meta_query['relation'];
      }

      foreach ($passed_meta_query as $meta_key => $meta_value) {
        if ($meta_key === 'relation') {
          continue;
        }
        $meta_query[] = $meta_value;
      }

      $args['meta_query'] = $meta_query;
    }

    return get_posts($args);

  }

  private function get_event_color_bar($eventID = '') {

    $colors = $this->get_event_colors($eventID);
    $html = '';
    if (!$colors) {
      $colors[] = '#000000'; // Default color if none is set
    }

    $html .= '<div class="event-color-bar mb-1 px-0" style="width:100%; height:5px;">';
      foreach ($colors as $color) {
        $html .= '<div class="event-color-segment" style="background-color:' . esc_attr($color) . '; width:' . (100 / count($colors)) . '%; height:100%; float:left;"></div>';
      }
    $html .= '</div>';

    return $html;
  }

  public function get_front_calendar($args = array()){
    $this->clearDailyHtml();

    $this->setStartOfWeek($this->calendar_start_day);
    
    // Allow filtering of the query args
    $args = apply_filters('mindevents_front_calendar_query_args', $args, $this);
    $args = $this->apply_visible_period_to_query_args($args, $this->get_frontend_view());

    $eventDates = $this->get_sub_events($args);
    $matched_events = $eventDates;
    $view = $this->get_frontend_view();

    if ($eventDates):
      foreach ($eventDates as $key => $event):

        $event_start = get_post_meta($event->ID, 'event_start_time_stamp', true);
        $event_dt = new \DateTimeImmutable($event_start, $this->today->getTimezone());
        $is_past = $event_dt < $this->today ? true : false;



        $is_members_only = get_post_meta($event->ID, '_members_only', true) == '1' ? true : false;
        $date = get_post_meta($event->ID, 'event_start_time_stamp', true);

        $insideHTML = '<div class="event ' . ($is_past ? 'past-event' : '') . ' ' . ($is_members_only ? 'members-only-event' : '') . '">';
            
        $insideHTML .= $this->get_event_color_bar($event->ID);

          if(!$is_members_only) {
              $insideHTML .= '<div class="sub-event-toggle" data-eventid="' . $event->ID . '">';
          }
              
          $starttime = date($this->time_format, strtotime(get_post_meta($event->ID, 'event_start_time_stamp', true)));
          $endtime = date($this->time_format, strtotime(get_post_meta($event->ID, 'event_end_time_stamp', true)));
          $is_featured = get_post_meta($event->post_parent, 'is_featured', true);
          //if in past add class
          $event_start = get_post_meta($event->ID, 'event_start_time_stamp', true);
          $event_dt = new \DateTimeImmutable($event_start, $this->today->getTimezone());
          $is_past = $event_dt < $this->today ? true : false;
          
          
          
            $insideHTML .= '<div class="event-label-container mb-2 small ' . ($is_past ? 'past-event opacity-50' : '') . ' ' . ($is_featured ? 'featured-event' : '') . '">';
              
              if ($is_featured) :
                $thumb = get_the_post_thumbnail(get_post_parent($event->ID), 'medium');
                if($thumb) :
                  $insideHTML .= '<div class="event-thumb mb-2">' . $thumb . '</div>';
                endif;
              endif;

              $insideHTML .= '<div class="event-meta">';
                $insideHTML .= '<div class="event-title fw-bold">' . get_the_title($event->post_parent) . '</div>';
                $insideHTML .= '<div class="event-time fw-light">' . $starttime . ' - ' . $endtime . '</div>';
              $insideHTML .= '</div>';
            $insideHTML .= '</div>';

          if(!$is_members_only) {
            $insideHTML .= '</div>';
          }

        $insideHTML .= '</div>';

        $event_end = get_post_meta($event->ID, 'event_end_time_stamp', true);
        $eventDates = $this->addDailyHtml($insideHTML, $date, $event_end ?: null);
      endforeach;
    endif;

    $html = ($view === 'week') ? $this->renderWeek() : $this->render();
    if (empty($matched_events)) {
      $html = '<p class="no-events">No events matched the current filters.</p>' . $html;
    }

    return $html;
  }



  public function get_front_list($calDate = '', $args = array()){
    $this->clearDailyHtml();
    $this->last_front_list_query = null;
    $this->setStartOfWeek($this->calendar_start_day);
    $current_time = current_time('mysql');
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
    $current_time_dt = new \DateTimeImmutable($current_time, $timezone);
    $current_plus_thirty = $current_time_dt->modify('+30 days')->format('Y-m-d H:i:s');
    $use_frontend_period = $this->should_use_frontend_visible_period();

    if ($use_frontend_period):
      $this->is_archive = true;
      $default = array(
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'suppress_filters' => true,
        'posts_per_page' => -1
      );
    elseif ($calDate == 'archive'):
      $this->is_archive = true;
      $this->show_past_events = false;
      $default = array(
        'meta_query' => array(
          'relation' => 'AND',
          array(
            'key' => 'event_end_time_stamp',
            'value' => $current_time,
            'compare' => '>=',
            'type' => 'DATETIME'
          ),
          array(
            'key' => 'event_start_time_stamp',
            'value' => $current_plus_thirty,
            'compare' => '<=',
            'type' => 'DATETIME' // Let WordPress know we're working with date
          ),
        ),
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'suppress_filters' => true,
        'posts_per_page' => -1
      );
    elseif(is_tax('event_category')):  
      $this->is_archive = true;
      $this->show_past_events = false;
      $per_page = (int) get_option('posts_per_page');
      if ($per_page < 1) {
        $per_page = 10;
      }
      $paged = max(1, absint(get_query_var('paged') ?: ($_GET['paged'] ?? 1)));
      $default = array(
        'meta_query' => array(
          'relation' => 'AND',
          array(
            'key' => 'event_end_time_stamp',
            'value' => $current_time,
            'compare' => '>=',
            'type' => 'DATETIME' // Let WordPress know we're working with date
          ),
        ),
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'suppress_filters' => true,
        'posts_per_page' => $per_page,
        'paged' => $paged
      );
    else:
      $default = array(
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'suppress_filters' => true,
        'posts_per_page' => -1
      );
    endif;

    $args = wp_parse_args($args, $default);
    $args = apply_filters('mindevents_front_list_query_args', $args, $this, $calDate);
    $args = $this->apply_visible_period_to_query_args($args, 'list');
    if ($use_frontend_period && $this->get_frontend_view() === 'list') {
      if (!isset($args['meta_query']) || !is_array($args['meta_query'])) {
        $args['meta_query'] = array();
      }
      if (!isset($args['meta_query']['relation'])) {
        $args['meta_query']['relation'] = 'AND';
      }
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
    $event_type = get_post_meta(get_the_id(), 'event_type', true);

    $i = 0;

    if (count($eventDates) > 0):

      foreach ($eventDates as $key => $event):
        $display_link = ($event_type == 'single-event' && $i < 1) ? false : true;
        $startDate = get_post_meta($event->ID, 'event_start_time_stamp', true);
        $this->addDailyHtml($this->get_list_item_html($event->ID, $display_link), $startDate);
        $i++;
      endforeach;
      $html = $this->renderList();
    else:
      if ($use_frontend_period) {
        $html = '<p class="no-events">No events matched the current view.</p>' . $this->renderList();
      } else {
        $html = '<p class="no-events">There are no ' . ($this->show_past_events ? 'events.' : 'upcoming events.') . '</p>';
      }
    endif;

    return $html;
  }

  public function get_last_front_list_query() {
    return $this->last_front_list_query;
  }





  /**
   * Returns a list of sub events
   *
   * @return string
   */
  public function renderList(){

    $out = '';
    if ($this->should_use_frontend_visible_period() && $this->get_frontend_view() === 'list' && !is_admin()) {
      $out .= $this->get_calendar_nav_links('list');
    }

    $out .= '<div id="mindCalanderList" class="event-list mt-4 ' . $this->classes['calendar'] . '">';
    if (is_array($this->dailyHtml)):
      if (empty($this->dailyHtml) && $this->should_use_frontend_visible_period() && $this->get_frontend_view() === 'list') {
        $period = $this->get_visible_period('list');
        $out .= '<h2 class="month-display text-center">' . esc_html($period['start']->format('F Y')) . '</h2>';
      }

      foreach ($this->dailyHtml as $year => $year_items):
        foreach ($year_items as $month => $month_items):
          $out .= '<h2 class="month-display text-center">' . date('F', mktime(0, 0, 0, $month, 1, $year)) . '</h2>';
          foreach ($month_items as $day => $daily_items):
            $out .= '<div class="list_day_container row">';
            foreach ($daily_items as $key => $dHTML):
              $out .= $dHTML;
            endforeach;
            $out .= '</div>';

          endforeach;
        endforeach;
      endforeach;
    endif;

    $out .= '</div>';

    return $out;
  }


  public function get_list_item_html($event = '', $display_link = true){


    $meta = get_post_meta($event);
    $dateformat = get_option('date_format');
    $timeformat = get_option('time_format');

    // Compare full datetime for accurate past event detection
    $event_start = isset($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : null;
    if ($event_start) {
      $event_dt = new \DateTimeImmutable($event_start, $this->today ? $this->today->getTimezone() : null);
      $is_past = $this->today && $event_dt < $this->today ? true : false;
    } else {
      $is_past = false;
    }
    $parentID = wp_get_post_parent_id($event);
    $sub_event_obj = get_post($event);

    $instructorID = get_post_meta($event, 'instructorID', true);
    $has_tickets = $parentID ? get_post_meta($parentID, 'has_tickets', true) : false;

    $parent_event_type = get_post_meta($parentID, 'event_type', true);
    if ($parent_event_type == 'single-event'):
      $series_start_date = get_post_meta($parentID, 'first_event_date', true);

      $series_end_date = get_post_meta($parentID, 'last_event_date', true);

      $series_started = $this->today->format('Y-m-d') > $series_start_date ? true : false;
      $series_ended = $this->today->format('Y-m-d') > $series_end_date ? true : false;
    endif;


    if ($meta):
      $description = ($meta['eventDescription'][0] ? $meta['eventDescription'][0] : get_the_excerpt(get_post_parent($event)));
      $ticket_html = '';
      if (!$is_past && $has_tickets && function_exists('wc_get_product')) {
        $ticket_product_id = !empty($meta['linked_product'][0]) ? absint($meta['linked_product'][0]) : 0;
        $ticket_meta = $meta;
        $ticket_start = !empty($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : '';

        if (!$ticket_product_id && $parentID) {
          $fallback_sub_events = get_posts(array(
            'post_type'        => 'sub_event',
            'post_parent'      => $parentID,
            'posts_per_page'   => 1,
            'orderby'          => 'meta_value',
            'meta_key'         => 'event_start_time_stamp',
            'meta_type'        => 'DATETIME',
            'order'            => 'ASC',
            'suppress_filters' => true,
            'meta_query'       => array(
              'relation' => 'AND',
              array(
                'key'     => 'event_start_time_stamp',
                'value'   => current_time('mysql'),
                'compare' => '>=',
                'type'    => 'DATETIME',
              ),
              array(
                'key'     => 'linked_product',
                'compare' => 'EXISTS',
              ),
            ),
          ));

          if (!empty($fallback_sub_events[0])) {
            $ticket_meta = get_post_meta($fallback_sub_events[0]->ID);
            $ticket_product_id = !empty($ticket_meta['linked_product'][0]) ? absint($ticket_meta['linked_product'][0]) : 0;
            $ticket_start = !empty($ticket_meta['event_start_time_stamp'][0]) ? $ticket_meta['event_start_time_stamp'][0] : '';
          }
        }

        if ($ticket_product_id && $ticket_start !== '') {
          $product = wc_get_product($ticket_product_id);
          if ($product) {
            $ticket_event_start = new DateTimeImmutable($ticket_start);
            $ticket_html = $this->build_offer_link(array(
              'label' => !empty($ticket_meta['wooLabel'][0]) ? $ticket_meta['wooLabel'][0] : '',
              'price' => $product->get_price(),
              'link' => $product->get_permalink(),
              'product_id' => $ticket_product_id,
              'event_date' => $ticket_event_start->format('D, M d Y @ H:i'),
              'quantity' => 1
            ));
          }
        }
      }

      $container_classes = array('item_meta_container', 'row');
      $container_classes[] = ($ticket_html !== '') ? 'has-right-content' : 'has-no-right-content';
      if ($is_past) {
        $container_classes[] = 'is-past';
      }

      $left_classes = array('left-content', 'col-12');
      if ($ticket_html !== '') {
        $left_classes[] = 'col-lg-8';
      }

      $html = '<div class="' . esc_attr(implode(' ', $container_classes)) . '">';

        $html .= '<div class="' . esc_attr(implode(' ', $left_classes)) . '">';
          if ($is_past):
            $html .= '<div class="past-event alert alert-info">This event has passed.</div>';
          endif;

          if ($parent_event_type == 'single-event'):
            if ($series_started && !$series_ended):
              $html .= '<div class="series-started alert alert-info"><strong>This multiday event has started.</strong></div>';
            endif;
            if ($series_ended):
              $html .= '<div class="series-ended alert alert-info"><strong>This series has ended.</strong></div>';
            endif;
          endif;

          if ($this->is_archive && $sub_event_obj->post_parent):
            $html .= '<div class="meta-item col-12 list-item-title">';
            if ($display_link) {
              $html .= '<a class="event-title-link" href="' . esc_url(get_permalink($sub_event_obj->post_parent)) . '" title="' . esc_attr(get_the_title($sub_event_obj->post_parent)) . '">';
            }
            $html .= '<h3 class="event-title">' . esc_html(get_the_title($sub_event_obj->post_parent)) . '</h3>';
            if ($display_link) {
              $html .= '</a>';
            }
            $html .= '</div>';
          endif;

          if ($meta['event_start_time_stamp'][0] && $meta['event_end_time_stamp'][0]):
            $start_date = strtotime($meta['event_start_time_stamp'][0]);
            $end_date = strtotime($meta['event_end_time_stamp'][0]);
            $start_date_str = date('Y-m-d', $start_date);
            $end_date_str = date('Y-m-d', $end_date);
            $endtime = date($this->time_format, $end_date);
            $date_display = '';
            if ($start_date_str == $end_date_str) {
              $date_display = date($dateformat, $start_date) . ' · ' . date($timeformat, $start_date) . ' - ' . $endtime;
            } else {
              $date_display = date($dateformat . ' ' . $timeformat, $start_date) . ' - ' . date($dateformat . ' ' . $timeformat, $end_date);
            }
            $html .= '<div class="meta-item col-12 list-item-date">';
              $html .= '<div class="value eventdate"><i class="fas fa-clock" aria-hidden="true"></i><span>' . esc_html($date_display) . '</span></div>';
            $html .= '</div>';
          endif;



          if ($description):
            $html .= '<div class="meta-item col-12 description">';
              $html .= '<span class="value eventdescription d-block mb-2">' . $description . '</span>';
              // $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" style="' . $style_str['color'] . '" class="event-info-link"> Read More</span></a>';
            $html .= '</div>';
          endif;
        
          if($instructorID) :
            $maker = get_user_by('id', $instructorID);
            $name = (get_field('display_name', 'user_' . $maker->ID ) ? get_field('display_name', 'user_' . $maker->ID ) : $maker->display_name);
            $title = get_field('title', 'user_' . $maker->ID);
            $photo = get_field('photo', 'user_' . $maker->ID);
            $author_url = get_author_posts_url($maker->ID);
            $html .= '<div class="instructor-info mt-3 w-100">';
              $html .= '<h5 class="mb-2">Your Instructor</h5>';
              if($photo):
                $html .= '<div class="instructor-photo d-inline-block me-2 lh-1 align-top">';
                  $html .= '<a href="' . esc_url($author_url) . '" class="text-decoration-none" target="_blank">';
                    $html .= '<img src="' . esc_url($photo['sizes']['very-small-square']) . '" alt="' . esc_attr($name) . '" class="rounded-circle" width="50" height="50"/>';
                  $html .= '</a>';
                $html .= '</div>';
              endif;
              $html .= '<div class="instructor-details d-inline-block align-top lh-1 my-auto mt-2">';
                $html .= '<span class="instructor-name fw-bold"><a href="' . esc_url($author_url) . '" class="text-decoration-none" target="_blank">' . esc_html($name) . '</a></span>';
                if($title):
                  $html .= '<br/>';
                  $html .= '<span class="instructor-title small text-muted">' . esc_html($title) . '</span>';
                endif;
              $html .= '</div>';
            $html .= '</div>';
            
          endif;
        
        
          $html .= '</div>'; //end left content

        if ($ticket_html !== '') {
          $html .= '<div class="right-content col-12 col-lg-4 d-flex h-100 flex-column align-items-stretch justify-content-center mt-2 mt-lg-0">';
            $html .= $ticket_html;
          $html .= '</div>';
        }



      $html .= '</div>';
    endif;
    return $html;

  }


    private function get_instructor_block($instructorID) {
       if($instructorID) :
          $maker = get_user_by('id', $instructorID);
          $name = (get_field('display_name', 'user_' . $maker->ID ) ? get_field('display_name', 'user_' . $maker->ID ) : $maker->display_name);
          $title = get_field('title', 'user_' . $maker->ID);
          $photo = get_field('photo', 'user_' . $maker->ID);
          $author_url = get_author_posts_url($maker->ID);
          $html = '';
          $html .= '<div class="instructor-info mt-3 w-100">';
            $html .= '<h5 class="mb-2">Your Instructor</h5>';
            if($photo):
              $html .= '<div class="instructor-photo d-inline-block me-2 lh-1 align-top">';
                $html .= '<a href="' . esc_url($author_url) . '" class="text-decoration-none" target="_blank">';
                  $html .= '<img src="' . esc_url($photo['sizes']['cal-thumb']) . '" alt="' . esc_attr($name) . '" class="rounded-circle" width="50" height="50"/>';
                $html .= '</a>';
              $html .= '</div>';
            endif;
            $html .= '<div class="instructor-details d-inline-block align-top lh-1 my-auto mt-2">';
              $html .= '<span class="instructor-name fw-bold"><a href="' . esc_url($author_url) . '" class="text-decoration-none" target="_blank">' . esc_html($name) . '</a></span>';
              if($title):
                $html .= '<br/>';
                $html .= '<span class="instructor-title small text-muted">' . esc_html($title) . '</span>';
              endif;
            $html .= '</div>';
          $html .= '</div>';
          
        endif;
        return $html;
    }


  public function build_offer_link($offer){
    if (!$offer['label']):
      $offer['label'] = __('Add to Cart', 'makesantafe');
    endif;

    $options = get_option(MINDEVENTS_PREPEND . 'support_settings');
    $html = '<div class="meta-item link w-100">';
    $html .= '<div class="offer-link">';
    // $html .= '<span class="label">' . apply_filters(MINDEVENTS_PREPEND . 'cost_label', $offer['label']) . '</span>';


    if ($options[MINDEVENTS_PREPEND . 'enable_woocommerce']):

      //check if product is in stock
      $product = wc_get_product($offer['product_id']);
      $in_cart = in_array($offer['product_id'], array_column(WC()->cart->get_cart(), 'product_id'));
      if (!$product->is_in_stock()):
        $stock = false;
      else:
        $stock = $product->get_stock_quantity();
      endif;

      // Build price display with sale information
      $price_display = '';
      if ($product && $product->is_on_sale()) {
        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();
        $price_display = $this->currency_symbol . $sale_price . ' <span class="sale-original-price">(was ' . $this->currency_symbol . $regular_price . ')</span>';
      } else {
        $price_display = ($offer['price'] ? $this->currency_symbol . $offer['price'] : '');
      }

      $html .= '<button
              data-product_id="' . $offer['product_id'] . '"
              data-quantity="' . $offer['quantity'] . '"
              data-event_date="' . $offer['event_date'] . '"
              class="btn btn-primary w-100 d-block mb-3 mindevents-add-to-cart"
              ' . ($stock ? '' : 'disabled') . '
              >';


      if ($in_cart):
        $html .= '<span class="in-cart fw-bold d-block">Item In Cart</span><span class="small d-block">Add more (+1)</span>';
      else:
        if ($stock):
          $html .= '<span class="d-inline-block fw-bold">' . $offer['label'] . ' - ' . $price_display . '</span>';
          if ($stock > 0):
            $html .= '<span class="small d-block"> (' . $stock . ' Available)</span>';
          endif;
        else:
          $html .= 'Out of Stock';
        endif;
      endif;

      $html .= '</button>';

      if ($in_cart):
        $html .= '<a href="' . wc_get_cart_url() . '"  class="btn-sm btn btn-info w-100 go-to-cart">Go to cart.</a>';
      endif;
    else:

      $html .= '<a href="' . $offer['link'] . '" class="button" target="_blank" >';

      $html .= ($offer['price'] ? $this->currency_symbol . $offer['price'] : $offer['label']);

      $html .= '</a>';

    endif;




    $html .= '</div>';
    $html .= '</div>';
    return $html;
  }





  private function get_variation_sku_from_date($eventID, $start_date){
    return sanitize_title($eventID . '_' . $start_date);
  }


  private function get_event_colors($eventID){
    $meta = get_post_meta($eventID);
    $colors = [];
    $event_categories = get_the_terms($eventID, 'event_category');
    if (!$event_categories || is_wp_error($event_categories)) {
        $event_categories = get_the_terms(get_post($eventID)->post_parent, 'event_category');
    }

    // 1. Try category colors first
    if ($event_categories && !is_wp_error($event_categories)) {
        foreach ($event_categories as $term) {
            $cat_color = get_field('event_color', $term->taxonomy . '_' . $term->term_id);
            if ($cat_color) {
                $colors[] = $cat_color;
            }
        }
    }

    // 2. If no category colors, try eventColor meta
    if (empty($colors) && isset($meta['eventColor'][0]) && $meta['eventColor'][0]) {
        $colors[] = $meta['eventColor'][0];
    }

    // 3. If still empty, use default
    if (empty($colors)) {
        $colors[] = '#858585'; // Default gray color
    }
    return $colors;
  }


  public function get_cal_meta_html($event = ''){
    $meta = get_post_meta($event);
    $parentID = wp_get_post_parent_id($event);
    $sub_event_obj = get_post($event);

    // Fetch product to check for sale
    $linked_product_id = get_post_meta($event, 'linked_product', true);
    $product = wc_get_product($linked_product_id);

    //add a filter for event image
    $image = apply_filters(MINDEVENTS_PREPEND . 'event_image', get_the_post_thumbnail(get_post_parent($event), 'medium', array('class' => 'event-image')), $event);
    $event_start = isset($meta['event_start_time_stamp'][0]) ? $meta['event_start_time_stamp'][0] : null;
    if ($event_start) {
      $event_dt = new \DateTimeImmutable($event_start, $this->today ? $this->today->getTimezone() : null);
      $is_past = $this->today && $event_dt < $this->today ? true : false;
    } else {
      $is_past = false;
    }

    $is_featured = get_post_meta($parentID, 'is_featured', true);

    $parent_event_type = get_post_meta($parentID, 'event_type', true);
    if ($parent_event_type == 'single-event'):
      $series_start_date = get_post_meta($parentID, 'first_event_date', true);
      $series_end_date = get_post_meta($parentID, 'last_event_date', true);
      $series_started = $this->today->format('Y-m-d') > $series_start_date ? true : false;
      $series_ended = $this->today->format('Y-m-d') > $series_end_date ? true : false;
    endif;

    if ($meta):
      $style_str = array();
      
      $description = ($meta['eventDescription'][0] ? $meta['eventDescription'][0] : get_the_excerpt(get_post_parent($event)));


      $html = '';
      

      $html .= '<div class="meta_inner_container ' . ($is_past ? 'past-event' : '') . '" style="' . implode(' ', $style_str) . '">';
      $html .= $this->get_event_color_bar($event);
      // Add sale banner if product is on sale
      if ($product && $product->is_on_sale()) {
        $html .= '<div class="sale-banner">Sale!</div>';
      }

      
      $html .= '<button class="event-meta-close"><i class="fas fa-times"></i></button>';


      if ($image):
        $html .= '<div class="featured-image pe-3">';
          $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
            $html .= $image;
          $html .= '</a>';
        $html .= '</div>';
      endif;

      $html .= '<div class="left-content">';
      if ($is_past):
        $html .= '<div class="past-event event-notice alert alert-warning">This event has passed.</div>';
      endif;

      if ($parent_event_type == 'single-event'):
        if ($series_started && !$series_ended):
          $html .= '<div class="series-started event-notice alert alert-warning"><strong>This multiday event has started.</strong></div>';
        endif;
        if ($series_ended):
          $html .= '<div class="series-ended event-notice alert alert-warning"><strong>This event has passed.</strong></div>';
        endif;
      endif;


      if ($sub_event_obj->post_parent):
        $html .= '<div class="meta-item">';
          $html .= '<a style="' . implode(' ', $style_str) . '" href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
            $html .= '<h3 class="event-title mt-0">' . get_the_title($sub_event_obj->post_parent) . '</h3>';
          $html .= '</a>';
        $html .= '</div>';
      endif;
      if ($meta['event_start_time_stamp'][0]):
        $start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
        $end_date = new DateTimeImmutable($meta['event_end_time_stamp'][0]);
        $html .= '<div class="meta-item">';
        if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
          // Same day: show date and start time, then just end time
          $html .= '<span class="value eventdate"><strong>' . $start_date->format('F j, Y') . ' @ ' . $start_date->format($this->time_format) . ' - ' . $end_date->format($this->time_format) . '</strong></span>';
        } else {
          // Different days: show full start and end
          $html .= '<span class="value eventdate"><strong>' . $start_date->format($this->date_format . ' ' . $this->time_format) . ' - ' . $end_date->format($this->date_format . ' ' . $this->time_format) . '</strong></span>';
        }
        $html .= '</div>';
      endif;



      if ($description):
        $html .= '<div class="meta-item">';
        $html .= '<span class="value eventdescription">' . $description . '</span></br>';
        $html .= '</div>';
      endif;
      if ($is_featured):
        $html .= '<div class="meta-item link mt-4">';
        $html .= '<a href="' . get_permalink($sub_event_obj->post_parent) . '" class="btn btn-light" style="' . (isset($style_str['border-color']) ? $style_str['border-color'] : '') . '">Learn More</a>';
        $html .= '</div>';
      endif;


      $html .= $this->get_instructor_block($meta['instructorID'][0]);


      $html .= '</div>';
      if(isset($meta['offers'])):
        $offers = unserialize($meta['offers'][0]);
      else:
        $offers = false;
      endif;
      $has_tickets = get_post_meta($sub_event_obj->post_parent, 'has_tickets', true);
      if (!$is_past && ($offers || $has_tickets)):

        $html .= '<div class="right-content mt-4 mt-md-0">';
        if ($has_tickets && $meta['linked_product'][0]):
          $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
          $product = wc_get_product($meta['linked_product'][0]);

          if ($product):

            $html .= $this->build_offer_link(array(
              'label' => $meta['wooLabel'][0],
              'price' => $product->get_price(),
              'link' => $product->get_permalink(),
              'product_id' => $meta['linked_product'][0],
              'event_date' => $event_start_date->format('D, M d Y @ H:i'),
              'quantity' => 1
            ));

          endif;
        elseif ($offers):

          foreach ($offers as $key => $offer):
            $html .= $this->build_offer_link($offer);
          endforeach;


        else:

          //get first sub event
          $sub_events = $this->get_sub_events(array('posts_per_page' => 1));
          if ($sub_events):
            $sub_event = $sub_events[0];
            $meta = get_post_meta($sub_event->ID);
            $event_start_date = new DateTimeImmutable($meta['event_start_time_stamp'][0]);
            $product = wc_get_product($meta['linked_product'][0]);
            if ($product):

              $html .= $this->build_offer_link(array(
                'label' => $meta['wooLabel'][0],
                'price' => $product->get_price(),
                'link' => $product->get_permalink(),
                'product_id' => $meta['linked_product'][0],
                'event_date' => $event_start_date->format('D, M d Y @ H:i'),
                'quantity' => 1
              ));

            endif;
          endif;

        endif;

        $html .= '</div>';
      endif; //end if offers



      $html .= '</div>';
    endif;
    return $html;

  }



  private function getContrastColor($hexcolor){
    $r = hexdec(substr($hexcolor, 1, 2));
    $g = hexdec(substr($hexcolor, 3, 2));
    $b = hexdec(substr($hexcolor, 5, 2));
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return ($yiq >= 150) ? '#333' : '#fff';
  }


  public function get_calendar($calDate = ''){

    $this->setStartOfWeek($this->calendar_start_day);

    $eventDates = $this->get_sub_events();
    if ($eventDates):
      foreach ($eventDates as $key => $event):

        //check if this is a sub_event of this event
        $parentID = wp_get_post_parent_id($event->ID);
        if ($parentID == $this->eventID):
          $child_event = true;
        else:
          $child_event = false;
        endif;


        $starttime = get_post_meta($event->ID, 'event_start_time_stamp', true);


        $starttime = date($this->time_format, strtotime(get_post_meta($event->ID, 'event_start_time_stamp', true)));
        $endtime = date($this->time_format, strtotime(get_post_meta($event->ID, 'event_end_time_stamp', true)));


        $date = get_post_meta($event->ID, 'event_start_time_stamp', true);



        $insideHTML = '<div class="event ' . ($child_event ? '' : 'disable') . '" data-startdate="' . $starttime . '" data-enddate="' . $endtime . '">';
        //add color border
        $insideHTML .= $this->get_event_color_bar($event->ID);
        

        $insideHTML .= '<div class="event-label">';
          $insideHTML .= '<span class="edit" data-subid="' . $event->ID . '">';
          if ($child_event):
            $insideHTML .= $starttime . ' - ' . $endtime;
          else:
            $insideHTML .= '<a href="' . get_edit_post_link($parentID) . '" target="_blank">';
            $insideHTML .= get_the_title($parentID);
            $insideHTML .= '</a>';
          endif;

          $insideHTML .= '</span>';

          if (is_admin() && $child_event):
            $insideHTML .= '<span data-subid="' . $event->ID . '" class="delete">&#10005;</span>';
          endif;

          $insideHTML .= '</div>';
        $insideHTML .= '</div>';
        $eventDates = $this->addDailyHtml($insideHTML, $date);
      endforeach;
    endif;
    return $this->render();
  }


  public function update_sub_event($sub_event, $meta, $parentID){
    $unique = $this->build_unique_key($parentID, $meta['event_start_time_stamp'], $meta);
    $meta['unique_event_key'] = $unique;

    foreach ($meta as $key => $value):
      update_post_meta($sub_event, $key, $value);
    endforeach;
  }




  public function add_sub_event($date, $meta, $eventID, $args = array()){
    $unique = $this->build_unique_key($eventID, $date, $meta);
    $return = array();

    //check to see if the event already exists
    $args = array(
      'fields' => 'ids',
      'post_type' => 'sub_event',
      'post_status' => 'publish',
      'meta_query' => array(
        array(
          'key' => 'unique_event_key',
          'value' => $unique
        )
      )
    );
    $check_query = new WP_Query($args);

    //if it doesnt exist, add it
    if (empty($check_query->have_posts())):
      $terms = wp_get_post_terms($eventID, 'event_category', array('fields' => 'ids'));

      $meta['unique_event_key'] = $unique;
      $parent_author = get_post_field('post_author', $eventID);

      do_action(MINDEVENTS_PREPEND . 'before_add_sub_event', $eventID, $meta);

      $defaults = array(
        'post_author' => $parent_author,
        'post_content' => '',
        'post_title' => $this->build_title($eventID, $date, $meta),
        'post_excerpt' => '',
        'post_status' => 'publish',
        'post_type' => 'sub_event',
        'post_parent' => $eventID,
        'context' => '',
        'meta_input' => $meta,
        'tax_input' => array(
          'event_category' => $terms
        )
      );
      $args = wp_parse_args($args, $defaults);
      $return = wp_insert_post($args);
      do_action(MINDEVENTS_PREPEND . 'after_add_sub_event', $eventID, $return, $meta);
    else:
      $return = false;
    endif;

    return $return;

  }


  private function build_unique_key($eventID, $date = '', $times = '')
  {
    return sanitize_title($eventID . '_' . $date . '_' . $times['event_start_time_stamp'] . '-' . $times['event_end_time_stamp']);
  }

  private function build_title($parentID, $date = '', $times = '')
  {
    $title = get_the_title($parentID) . ' | ' . $date . ' | ' . $times['event_start_time_stamp'] . '-' . $times['event_end_time_stamp'];
    return apply_filters('mind_events_title', $title, $date, $times, $this);
  }


  public function delete_sub_events($parentID = '')
  {
    $sub_events = $this->get_sub_events(array(
      'post_type' => 'sub_event',
      'post_status' => 'any',
      'posts_per_page' => -1,
      'post_parent' => $parentID
    ));
    if (is_array($sub_events) && count($sub_events) > 0) {
      foreach ($sub_events as $event) {
        do_action(MINDEVENTS_PREPEND . 'before_delete_sub_event', $event->ID);
        $return = wp_delete_post($event->ID);
        do_action(MINDEVENTS_PREPEND . 'after_delete_sub_event', $event->ID);
      }
      return true;
    }
    return false;
  }


  public function get_archive_url()
  {
    return get_post_type_archive_link('events');
  }



  public function generate_schema()
  {
    $sub_events = $this->get_sub_events();
    $schema = array(
      '@context' => 'https://schema.org',
      'type' => 'Event',
      'name' => get_the_title($this->eventID),
      'startDate' => get_post_meta($this->eventID, 'first_event_date', true),
      'endDate' => get_post_meta($this->eventID, 'last_event_date', true),
      'location' => array(
        '@type' => 'Place',
        'name' => get_bloginfo('name'),
        'address' => array(
          '@type' => 'PostalAddress',
          'name' => 'Make Santa Fe',
          'addressLocality' => 'Santa Fe',
          'postalCode' => '87507',
          'addressRegion' => 'NM',
          'addressCountry' => 'US',
          'streetAddress' => '2879 All Trades Rd',
        ),
      ),
      'image' => array(
        get_the_post_thumbnail_url($this->eventID, 'medium')
      ),
      'description' => get_the_excerpt($this->eventID),
    );
    if ($sub_events):
      foreach ($sub_events as $key => $event):
        $offer_array = array();
        $offers = get_post_meta($event->ID, 'offers', true);
        if ($offers):

          foreach ($offers as $key => $offer):
            $offer_array[] = array(
              '@type' => 'Offer',
              'url' => $offer['link'],
              'price' => $offer['price'],
              'priceCurrency' => 'USD',
              'availability' => 'https://schema.org/InStock', //TODO: add this to sub event options
              'validFrom' => get_the_date('Y-m-d H:i:s', $this->eventID)
            );
          endforeach;
        endif;

        $schema['subEvent'][] = array(
          '@context' => 'https://schema.org',
          'type' => 'Event',
          'name' => get_the_title($this->eventID),
          'doorTime' => get_post_meta($event->ID, 'event_start_time_stamp', true),
          'startDate' => get_post_meta($event->ID, 'event_start_time_stamp', true),
          'endDate' => get_post_meta($event->ID, 'event_end_time_stamp', true),
          'location' => array(
            '@type' => 'Place',
            'name' => get_bloginfo('name'),
            'address' => array( //TODO: Add all this to event options
              '@type' => 'PostalAddress',
              'name' => get_bloginfo('name'),
              'addressLocality' => '2879 All Trades Rd',
              'postalCode' => '87507',
              'addressRegion' => 'New Mexico',
              'addressCountry' => 'US',
            ),
          ),
          'image' => array(
            get_the_post_thumbnail_url($this->eventID, 'medium')
          ),
          'description' => get_post_meta($event->ID, 'eventDescription', true),
          'offers' => $offer_array,
          'performer' => array(
            '@type' => 'Organization',
            'url' => get_site_url(),
            'name' => get_bloginfo('name')
          )
        );
      endforeach;
    endif;

    return json_encode($schema);
  }
}
