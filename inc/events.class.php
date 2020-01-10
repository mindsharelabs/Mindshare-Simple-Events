<?php


class mindEventCalendar {

  private $eventID;
  private $wp_post;

  private $displayType;
  private $calendar_start_day;
  private $options;
  private $show_past_events;
  private $next_month;

  private $weekDayNames;
	private $now;
	private $today;
  private $all_events;

  private $event_categories = false;


  private $classes = [
		'calendar'     => 'mindEventCalendar',
		'leading_day'  => 'SCprefix',
		'trailing_day' => 'SCsuffix',
		'today'        => 'today',
		'event'        => 'event',
		'events'       => 'events',
	];

  private $dailyHtml = [];
	private $offset = 0;

  function __construct($id = '', $calendarDate = null, $today = null ) {


    $this->setToday($today);
		$this->setCalendarClasses();


    if($id == 'archive') :
      $this->all_events = $this->get_all_events();
    endif;

    if($calendarDate) :
      $this->setDate($calendarDate);
    elseif(get_post($id)) :
      $this->setDate(get_post_meta($id, 'first_event_date', true));
    endif;

    $this->options = get_option( 'mindevents_support_settings' );
    $this->eventID = $id;
    $this->wp_post = get_post($id);

    $this->show_past_events = true;


    $date = new DateTime('now');
    $date->modify('first day of next month');
    $this->next_month = $date->format('m');


    $this->calendar_start_day = (isset($this->options['mindevents_start_day']) ? $this->options['mindevents_start_day'] : 'Monday');

  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  /**
	 * Sets the date for the calendar.
	 *
	 * @param \DateTimeInterface|int|string|null $date DateTimeInterface or Date string parsed by strtotime for the
	 *     calendar date. If null set to current timestamp.
	 */
	public function setDate( $date = null ) {
		$this->now = $this->parseDate($date) ?: new \DateTimeImmutable();
	}


  /**
   * @param \DateTimeInterface|int|string|null $date
   * @return \DateTimeInterface|null
   */
  private function parseDate( $date = null ) {
    if( $date instanceof \DateTimeInterface ) {
      return $date;
    }
    if( is_int($date) ) {
      return (new \DateTimeImmutable())->setTimestamp($date);
    }
    if( is_string($date) ) {
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
	public function setCalendarClasses( array $classes = [] ) {
		foreach( $classes as $key => $value ) {
			if( !isset($this->classes[$key]) ) {
				throw new \InvalidArgumentException("class '{$key}' not supported");
			}

			$this->classes[$key] = $value;
		}
	}



  public function setEventCategories($array = array()) {
    $this->event_categories = $array;
  }

  /**
	 * Sets "today"'s date. Defaults to today.
	 *
	 * @param \DateTimeInterface|false|string|null $today `null` will default to today, `false` will disable the
	 *     rendering of Today.
	 */
	public function setToday( $today = null ) {
		if( $today === false ) {
			$this->today = null;
		} elseif( $today === null ) {
			$this->today = new \DateTimeImmutable();
		} else {
			$this->today = $this->parseDate($today);
		}
	}

  /**
	 * @param string[]|null $weekDayNames
	 */
	public function setWeekDayNames( array $weekDayNames = null ) {
		if( is_array($weekDayNames) && count($weekDayNames) !== 7 ) {
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
	public function addDailyHtml( $html, $startDate, $endDate = null ) {
		static $htmlCount = 0;

		$start = $this->parseDate($startDate);
		if( !$start ) {
			throw new \InvalidArgumentException('invalid start time');
		}

		$end = $start;
		if( $endDate ) {
			$end = $this->parseDate($endDate);
		}
		if( !$end ) {
			throw new \InvalidArgumentException('invalid end time');
		}

		if( $end->getTimestamp() < $start->getTimestamp() ) {
			throw new \InvalidArgumentException('end must come after start');
		}

		$working = (new \DateTimeImmutable())->setTimestamp($start->getTimestamp());
		do {
			$tDate = getdate($working->getTimestamp());

			$this->dailyHtml[$tDate['year']][$tDate['mon']][$tDate['mday']][$htmlCount] = $html;

			$working = $working->add(new \DateInterval('P1D'));
		} while( $working->getTimestamp() < $end->getTimestamp() + 1 );

		$htmlCount++;
	}

  /**
	 * Clear all daily events for the calendar
	 */
	public function clearDailyHtml() { $this->dailyHtml = []; }


  /**
   * Sets the first day of the week
   *
   * @param int|string $offset Day the week starts on. ex: "Monday" or 0-6 where 0 is Sunday
   */
  public function setStartOfWeek( $offset ) {
    if( is_int($offset) ) {
      $this->offset = $offset % 7;
    } elseif( $this->weekDayNames !== null && ($weekOffset = array_search($offset, $this->weekDayNames, true)) !== false ) {
      $this->offset = $weekOffset;
    } else {
      $weekTime = strtotime($offset);
      if( $weekTime === 0 ) {
        throw new \InvalidArgumentException('invalid offset');
      }

      $this->offset = date('N', $weekTime) % 7;
    }
  }



  /**
   * Returns the generated Calendar
   *
   * @return string
   */
  public function render() {
    $out = '';
    $now   = getdate($this->now->getTimestamp());
    $today = [ 'mday' => -1, 'mon' => -1, 'year' => -1 ];
    if( $this->today !== null ) {
      $today = getdate($this->today->getTimestamp());
    }

    $daysOfWeek = $this->weekdays();
    $this->rotate($daysOfWeek, $this->offset);

    $weekDayIndex = date('N', mktime(0, 0, 1, $now['mon'], 1, $now['year'])) - $this->offset;
    $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $now['mon'], $now['year']);


      $out .= '<h3 class="month-display">' . $now['month'] . ' ' . $now['year'] . '</h3>';
      $out .= '<table id="mindEventCalendar" data-month="' . $now['mon'] . '" data-year="' . $now['year'] . '" cellpadding="0" cellspacing="0" class=" ' . $this->classes['calendar'] . '"><thead><tr>';
        foreach( $daysOfWeek as $dayName ) {
          $out .= '<th>' . $dayName . '</th>';
        }
      $out .= '</tr></thead><tbody><tr>';

      $weekDayIndex = ($weekDayIndex + 7) % 7;
      if( $weekDayIndex === 7 ) {
        $weekDayIndex = 0;
      } else {
        $out .= str_repeat('<td class="' . $this->classes['leading_day'] . '">&nbsp;</td>', $weekDayIndex);
      }

      $count = $weekDayIndex + 1;
      for( $i = 1; $i <= $daysInMonth; $i++ ) {
        $date = (new \DateTimeImmutable())->setDate($now['year'], $now['mon'], $i);

        $isToday = false;
        if( $this->today !== null ) {
          $isToday = $i == $today['mday']
            && $today['mon'] == $date->format('n')
            && $today['year'] == $date->format('Y');
        }

        $out .= '<td' . ($isToday ? ' class="' . $this->classes['today'] . '"' : '') . '>';

        $out .= sprintf('<time class="calendar-day" datetime="%s">%d</time>', $date->format('Y-m-d'), $i);

        $dailyHTML = null;
        if( isset($this->dailyHtml[$now['year']][$now['mon']][$i]) ) {
          $dailyHTML = $this->dailyHtml[$now['year']][$now['mon']][$i];
        }

        if( is_array($dailyHTML) ) {
          $out .= '<div class="events">';
          foreach( $dailyHTML as $dHtml ) {
            $out .= $dHtml;
          }
          $out .= '</div>';
        }

        $out .= '</td>';

        if( $count > 6 ) {
          $out   .= '</tr><tr class="meta-container"><td class="eventMeta" colspan="7"></td></tr>' . ($i < $daysInMonth ? '<tr>' : '');
          $count = 0;
        }
        $count++;
      }

      if( $count !== 1 ) {
        $out .= str_repeat('<td class="' . $this->classes['trailing_day'] . '">&nbsp;</td>', 8 - $count) . '</tr>';
      }

      $out .= '<tr class="meta-container"><td class="eventMeta" colspan="7"></tbody></table>';

    return $out;
  }



  /**
   * @param int $steps
   */
  private function rotate( array &$data, $steps ) {
    $count = count($data);
    if( $steps < 0 ) {
      $steps = $count + $steps;
    }
    $steps %= $count;
    for( $i = 0; $i < $steps; $i++ ) {
      $data[] = array_shift($data);
    }
  }

  /**
   * @return string[]
   */
  private function weekdays() {
    if( $this->weekDayNames !== null ) {
      $wDays = $this->weekDayNames;
    } else {
      $today = (86400 * (date('N')));
      $wDays = [];
      for( $n = 0; $n < 7; $n++ ) {
        $wDays[] = strftime('%a', time() - $today + ($n * 86400));
      }
    }

    return $wDays;
  }


  public function set_past_events_display($display) {
    if(is_string($display)) :
      if($display === '1') {$display = true;}
      if($display === '0') {$display = false;}
    endif;

    if($display === false) {
      $this->show_past_events = false;
    } else {
      $this->show_past_events = true;
    }
  }


    public function get_all_events($args = array()) {

      $defaults = array(
        'meta_query' => array(
          // 'relation' => 'AND',
          'start_clause' => array(
            'key' => 'starttime',
            'compare' => 'EXISTS',
          ),
          'date_clause' => array(
            'key' => 'event_date',
            'compare' => 'EXISTS',
          ),
        ),
        'orderby' => 'meta_value',
        'meta_key' => 'event_time_stamp',
        'meta_type' => 'DATETIME',

        'order'            => 'ASC',
        'post_type'        => 'sub_event',
        'suppress_filters' => true,
        'posts_per_page'   => -1
      );
      if($this->show_past_events == false) {
        $args['meta_query'][] = array(
          'key' => 'event_time_stamp', // Check the start date field
          'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
          'compare' => '>=', // Return the ones greater than today's date
          'type' => 'DATETIME' // Let WordPress know we're working with date
        );
      }

      if($this->event_categories) {
        $args['tax_query'] = array(
          array(
            'taxonomy' => 'event_category',
            'field'    => 'slug',
            'terms'    => $this->event_categories,
          ),
        );
      }

      $args = wp_parse_args($args, $defaults);
      return get_posts($args);

    }

  public function get_sub_events($args = array()) {
    $defaults = array(
      'meta_query' => array(
        // 'relation' => 'AND',
        'start_clause' => array(
          'key' => 'starttime',
          'compare' => 'EXISTS',
        ),
        'date_clause' => array(
          'key' => 'event_date',
          'compare' => 'EXISTS',
        ),
      ),
      'orderby' => 'meta_value',
      'meta_key' => 'event_time_stamp',
      'meta_type' => 'DATETIME',
      'order'            => 'ASC',
      'post_type'        => 'sub_event',
      'post_parent'      => $this->eventID,
      'suppress_filters' => true,
      'posts_per_page'   => -1
    );

    if($this->show_past_events === false) {
      $args['meta_query'][] = array(
        'key' => 'event_time_stamp', // Check the start date field
        'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
        'compare' => '>=', // Return the ones greater than today's date
        'type' => 'DATETIME' // Let WordPress know we're working with date
      );
    }
    if($this->event_categories) {
      $args['tax_query'] = array(
        array(
          'taxonomy' => 'event_category',
          'field'    => 'slug',
          'terms'    => $this->event_categories,
        ),
      );
    }

    $args = wp_parse_args($args, $defaults);
    return get_posts($args);

  }



  public function get_front_calendar($place = '') {

    $this->setStartOfWeek($this->calendar_start_day);
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'starttime', true);
        $endtime = get_post_meta($event->ID, 'endtime', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $color = get_post_meta($event->ID, 'eventColor', true);


        if($place == 'archive') {
          $label = get_the_title($event->post_parent);
        } else {
          $label = $starttime;
        }

        if(!$color){
          $color = '#858585';
        }

        if (strlen($label) > 15) :
          $label = substr($label, 0, 12) . '...';
        endif;


        $text_color = $this->getContrastColor($color);

        $insideHTML = '<div class="event ' . (MINDRETURNS_IS_MOBILE ? 'mobile' : '') . '">';
          $insideHTML .= '<span class="sub-event-toggle" data-eventid="' . $event->ID . '" style="color:' . $text_color . '; background:' . $color .'" >';
            $insideHTML .= $label;
          $insideHTML .= '</span>';
        $insideHTML .= '</div>';


        $eventDates = $this->addDailyHtml($insideHTML, $date);
      endforeach;
    endif;

    return $this->render();;
  }



  public function get_front_list($calDate = '') {
    $eventDates = $this->get_sub_events();
    if(count($eventDates) > 0) :

      foreach ($eventDates as $key => $event) :
        $startDate = get_post_meta($event->ID, 'event_date', true);
        $this->addDailyHtml($this->get_list_item_html($event->ID), $startDate);
      endforeach;
      $html = $this->renderList();
    else :
      $html = '<p class="no-events">There are no ' . ($this->show_past_events  ? 'events' : 'upcoming events.');
    endif;

    return $html;
  }


  /**
   * Returns a list of sub events
   *
   * @return string
   */
  public function renderList() {
    $now   = getdate($this->now->getTimestamp());
    $out = '<div id="mindCalanderList" class="event-list ' . $this->classes['calendar'] . '">';
      if(is_array($this->dailyHtml)) :
        $number_of_years = count($this->dailyHtml);
        foreach ($this->dailyHtml as $year => $year_items) :
          foreach ($year_items as $month => $month_items) :
            foreach ($month_items as $day => $daily_items) :
              $date = (new DateTime())->setDate($year, $month, $day);
              $date_format = ($number_of_years > 1) ? 'l, M j Y' : 'l, M j';
                $out .= '<div class="list_day_container">';
                  $out .= '<div class="day-label"><time class="calendar-day" datetime="' . $date->format('Y-m-d') .'">' . $date->format($date_format) . '</time></div>';
                  foreach ($daily_items as $key => $dHTML) :
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

  public function get_list_item_html($event = '') {
    $meta = get_post_meta($event);
    $sub_event_obj = get_post($event);
    mapi_write_log($meta);
    if($meta) :
      $style_str = array();
      if($meta['eventColor']) :
        $style_str['border-color'] = 'border-color:' . $meta['eventColor'][0] . ';';
        $style_str['color'] = 'color:' . $meta['eventColor'][0] . ';';
      endif;

      $html = '<div class="item_meta_container">';

        if($meta['event_date'][0]) :
          $html .= '<div class="meta_item starttime">';
            $html .= '<span class="label">' . apply_filters('mindevents_start_time_label', 'Start Time') . '</span>';
            $html .= '<span class="value eventstarttime">' . $meta['starttime'][0] . '</span>';
          $html .= '</div>';
        endif;



        if($meta['eventDescription'][0]) :
          $html .= '<div class="meta_item description">';
            $html .= '<span class="value eventdescription">' . $meta['eventDescription'][0] . '</span>';
          $html .= '</div>';
        endif;

        if($meta['offers'][0]) :
          $offers = unserialize($meta['offers'][0]);
          $html .= '<div class="offers meta_item">';
          foreach ($offers as $key => $offer) :

              $html .= '<div class="cost">';
                $html .= '<span class="label">' . apply_filters('mindevents_cost_label', $offer['label']) . '</span>';
                $html .= '<span class="value eventcost"><a href="' . $offer['link'] . '" target="_blank">' . ($offer['price'] ? $offer['price'] : $offer['label']) . '</a></span>';
              $html .= '</div>';

          endforeach;
          $html .= '</div>';
        endif;

      $html .= '</div>';
    endif;
    return $html;

  }


  public function get_cal_meta_html($event = '') {
    $meta = get_post_meta($event);
    $sub_event_obj = get_post($event);

    if($meta) :
      $style_str = array();
      if($meta['eventColor']) :
        $style_str['background'] = 'background:' . $meta['eventColor'][0] . ';';
        $style_str['color'] = 'color:' . $this->getContrastColor($meta['eventColor'][0]) . ';';
      endif;

      $html = '<div class="meta_inner_container" style="' . implode(' ', $style_str) . '">';
        $html .= '<div class="left-content">';

          if($sub_event_obj->post_parent) :
            $html .= '<div class="meta-item">';
              $html .= '<a style="' . implode(' ', $style_str) .'" href="' . get_permalink($sub_event_obj->post_parent) . '" title="' . get_the_title($sub_event_obj->post_parent) . '">';
                $html .= '<h3 class="event-title">' . get_the_title($sub_event_obj->post_parent) . '</h3>';
              $html .= '</a>';
            $html .= '</div>';
          endif;

          if($meta['event_date'][0]) :
            $date = new DateTime($meta['event_date'][0]);
            $html .= '<div class="meta-item">';
              $html .= '<span class="value eventdate"><strong>' . $date->format('F j, Y') . ' @ ' . $meta['starttime'][0] . ($meta['endtime'][0] ? ' - ' . $meta['endtime'][0] : '') . '</strong></span>';
            $html .= '</div>';
          endif;


          if($meta['eventCost'][0]) :
            $html .= '<div class="meta-item">';
              $html .= '<span class="value eventcost">' . $meta['eventCost'][0] . '</span>';
            $html .= '</div>';
          endif;

          if($meta['eventDescription'][0]) :
            $html .= '<div class="meta-item">';
              $html .= '<span class="value eventdescription">' . $meta['eventDescription'][0] . '</span>';
            $html .= '</div>';
          endif;
        $html .= '</div>';

        if($meta['offers']) :
          $offers = unserialize ($meta['offers'][0]);
          $html .= '<div class="right-content">';
            unset($style_str['background']);
            $style_str['border-color'] = 'border-color:' . $this->getContrastColor($meta['eventColor'][0]) . ';';
            $html .= '<div class="meta-item">';

              foreach ($offers as $key => $offer) :
                $html .= '<span class="value eventlink">';
                  $html .= '<span style="' . implode(' ', $style_str) . '" class="price">$' . $offer['price'] . '</span>';
                  $html .= '<a style="' . implode(' ', $style_str) . '" class="button button-link" href="' . $offer['link'] . '" target="_blank">' . $offer['label'] . '</a>';
                $html .= '</span>';
              endforeach;

            $html .= '</div>';
          $html .= '</div>';
        endif;

      $html .= '</div>';
    endif;
    return $html;

  }



  private function getContrastColor($hexcolor) {
      $r = hexdec(substr($hexcolor, 1, 2));
      $g = hexdec(substr($hexcolor, 3, 2));
      $b = hexdec(substr($hexcolor, 5, 2));
      $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      return ($yiq >= 150) ? '#333' : '#fff';
  }


  public function get_calendar($calDate = '') {

    $this->setStartOfWeek($this->calendar_start_day);
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'starttime', true);
        $endtime = get_post_meta($event->ID, 'endtime', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $color = get_post_meta($event->ID, 'eventColor', true);
        $text_color = $this->getContrastColor($color);

        $insideHTML = '<div class="event ' . (MINDRETURNS_IS_MOBILE ? 'mobile' : '') . '">';
          $insideHTML .= '<span class="edit" style="color:' . $text_color . '; background:' . $color .'" data-subid="' . $event->ID . '">';
            $insideHTML .= $starttime . ' - ' . $endtime;
          $insideHTML .= '</span>';
          if(is_admin()) :
            $insideHTML .= '<span data-subid="' . $event->ID . '" class="delete">&#10005;</span>';
          endif;
        $insideHTML .= '</div>';
        $eventDates = $this->addDailyHtml($insideHTML, $date);
      endforeach;
    endif;
    return $this->render();
  }


  public function update_sub_event($sub_event, $meta, $parentID) {
    $unique = $this->build_unique_key($meta['event_date'], $meta, $parentID);
    $meta['event_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['starttime']) );
    $meta['event_start_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['starttime']) );
    $meta['event_end_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($meta['event_date'] . ' ' . $meta['endtime']) );
    $meta['unique_event_key'] = $unique;

    foreach ($meta as $key => $value) :
      update_post_meta($sub_event, $key, $value);
    endforeach;
  }




  public function add_sub_event($args = array(), $date, $meta, $eventID) {
    $unique = $this->build_unique_key($date, $meta, $eventID);
    $return = array();
    $args = array(
      'fields' => 'ids',
      'post_type'   => 'sub_event',
      'post_status' => 'publish',
      'meta_query'  => array(
        array(
          'key' => 'unique_event_key',
          'value' => $unique
        )
      )
    );


    $check_query = new WP_Query( $args );
    if( empty($check_query->have_posts()) ) :
      $terms = wp_get_post_terms( $eventID, 'event_category',  array('fields' => 'ids'));
      $meta['event_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['starttime']) );
      $meta['event_start_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['starttime']) );
      $meta['event_end_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['endtime']) );
      $meta['unique_event_key'] = $unique;
      $meta['event_date'] = $date;
      $defaults = array(
        'post_author'           => get_current_user_id(),
        'post_content'          => '',
        'post_title'            => $this->build_title($date, $meta, $eventID),
        'post_excerpt'          => '',
        'post_status'           => 'publish',
        'post_type'             => 'sub_event',
        'post_parent'           => $this->eventID,
        'context'               => '',
        'meta_input'            => $meta,
        'tax_input'             => array(
          'event_category' => $terms
        )
      );
      $args = wp_parse_args($args, $defaults);
      $return = wp_insert_post($args);
      else :
        $return = false;
      endif;

      return $return;

    }


    private function build_unique_key($date = '', $times = '', $eventID) {
      return sanitize_title($eventID . '_' . $date . '_' . $times['starttime'] . '-' . $times['endtime']);
    }

    private function build_title($date = '', $times = '', $parentID) {
      $title = get_the_title($this->eventID) . ' | ' . $date . ' | ' . $times['starttime'] . '-' . $times['endtime'];
      return apply_filters('mind_events_title', $title, $date, $times, $this);
    }


    public function delete_sub_events() {
      $sub_events = $this->get_sub_events();
      if (is_array($sub_events) && count($sub_events) > 0) {
        foreach($sub_events as $event){
          $return = wp_delete_post($event->ID);
        }
        return true;
      }
      return false;
    }



    public function generate_schema() {
      $sub_events = $this->get_sub_events();
      $schema = array(
        '@context' => 'https://schema.org',
        'type' => 'TheaterEvent',
        'name' => get_the_title($this->eventID),
        'startDate' => get_post_meta($this->eventID, 'first_event_date', true),
        'endDate' => get_post_meta($this->eventID, 'last_event_date', true),
        'location' => array(
          '@type' => 'Place',
          'name' => get_bloginfo('name'), //TODO: Add this to event options
          'address' => array( //TODO: Add all this to event options
            '@type' => 'PostalAddress',
            'name' => '',
            'addressLocality' => '',
            'postalCode' => '',
            'addressRegion' => '',
            'addressCountry' => '',
          ),
        ),
        'image' => array(
          get_the_post_thumbnail_url($this->eventID, 'medium')
        ),
        'description' => get_the_content('', false, $this->eventID),
        'performer' => array(
          '@type' => 'Organization',
          'url' => get_site_url(),
          'name' => get_bloginfo('name')
        )
      );
      if($sub_events) :
        foreach ($sub_events as $key => $event) :
          $schema['subEvent'][] = array(
            '@context' => 'https://schema.org',
            'type' => 'TheaterEvent',
            'name' => get_the_title($this->eventID),
            'doorTime' => get_post_meta($event->ID, 'event_start_time_stamp', true),
            'startDate' => get_post_meta($event->ID, 'event_start_time_stamp', true),
            'endDate' => get_post_meta($event->ID, 'event_end_time_stamp', true),
            'location' => array(
              '@type' => 'Place',
              'name' => get_bloginfo('name'), //TODO: Add this to event options
              'address' => array( //TODO: Add all this to event options
                '@type' => 'PostalAddress',
                'name' => '',
                'addressLocality' => '',
                'postalCode' => '',
                'addressRegion' => '',
                'addressCountry' => '',
              ),
            ),
            'image' => array(
              get_the_post_thumbnail_url($this->eventID, 'medium')
            ),
            'description' => get_post_meta($event->ID, 'eventDescription', true),
            'offers' => array(
              '@type' => 'Offer',
              'url' => get_permalink($this->eventID),
              'price' => str_replace('$', '', get_post_meta($event->ID, 'eventCost', true)),
              'priceCurrency' => 'USD',
              'availability' => 'https://schema.org/InStock', //TODO: add this to sub event options
              'validFrom' => get_the_date( 'Y-m-d H:i:s', $this->eventID)
            ),
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
