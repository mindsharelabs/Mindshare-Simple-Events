<?php


class mindEvent {

  private $eventID;
  private $wp_post;

  private $displayType;
  private $calendar_start_day;
  private $options;
  private $show_past_events;
  private $next_month;

  function __construct($id) {
    $this->options = get_option( 'mindevents_support_settings' );
    $this->eventID = $id;
    $this->wp_post = get_post($id);

    $this->show_past_events = true;


    $date = new DateTime('now');
    $date->modify('first day of next month');
    $this->next_month = $date->format('m');

    // mapi_write_log($)
    $this->calendar_start_day = (isset($this->options['mindevents_start_day']) ? $this->options['mindevents_start_day'] : 'Monday');

  }


  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  public function set_past_events_display($display) {
    if($display == 'false') {
      $this->show_past_events = false;
    } else {
      $this->show_past_events = true;
    }
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
    if($this->show_past_events == false) {
      $args['meta_query'][] = array(
        'key' => 'event_time_stamp', // Check the start date field
        'value' => date('Y-m-d H:i:s'), // Set today's date (note the similar format)
        'compare' => '>=', // Return the ones greater than today's date
        'type' => 'DATETIME' // Let WordPress know we're working with date
      );
    }

    $args = wp_parse_args($args, $defaults);
    return get_posts($args);

  }

  public function get_front_calendar($calDate = '') {
    $calendar = new SimpleCalendar($calDate);
    $calendar->setStartOfWeek($this->calendar_start_day);
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'starttime', true);
        $endtime = get_post_meta($event->ID, 'endtime', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $color = get_post_meta($event->ID, 'eventColor', true);
        if(!$color){
          $color = '#858585';
        }
        $inside = '<span class="sub-event-toggle" data-eventid="' . $event->ID . '" style="background:' . $color .'" >' . $starttime . '</span>';
        $html = $calendar->get_daily_event_html($inside);
        $eventDates = $calendar->addDailyHtml($html, $date);
      endforeach;
    endif;

    return $calendar->show(false);;
  }



  public function get_front_list($calDate = '') {
    $eventDates = $this->get_sub_events();
    $calendar = new SimpleCalendar();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $startDate = get_post_meta($event->ID, 'event_date', true);
        $calendar->addDailyHtml($this->get_list_item_html($event->ID), $startDate);
      endforeach;
      $html = $calendar->renderList();
    else :
      $html = ($this->show_past_events) ? '' : '<p class="no-events">There are no upcoming events.<p>';
    endif;

    return $html;
  }



  public function get_list_item_html($id) {
    $meta = get_post_meta($id);
    if($meta) :
      $style_str = array();
      if($meta['eventColor']) :
        $style_str['background'] = 'background:' . $meta['eventColor'][0] . ';';
        $style_str['color'] = 'color:' . $this->getContrastColor($meta['eventColor'][0]) . ';';
      endif;

      $html = '<div class="meta_inner_container" style="' . implode(' ', $style_str) . '">';
        $html .= '<div class="left-content">';
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

        if($meta['eventLink'][0] && $meta['eventLinkLabel'][0]) :
          $html .= '<div class="right-content">';
            unset($style_str['background']);
            $style_str['border-color'] = 'border-color:' . $this->getContrastColor($meta['eventColor'][0]) . ';';
            $html .= '<div class="meta-item">';
              $html .= '<span class="value eventlink"><a style="' . implode(' ', $style_str) . '" class="button button-link" href="' . $meta['eventLink'][0] . '" target="_blank">' . $meta['eventLinkLabel'][0] . '</a></span>';
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
    $calendar = new SimpleCalendar($calDate);
    $calendar->setStartOfWeek($this->calendar_start_day);
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'starttime', true);
        $endtime = get_post_meta($event->ID, 'endtime', true);
        $date = get_post_meta($event->ID, 'event_date', true);
          $color = get_post_meta($event->ID, 'eventColor', true);
        $html = $calendar->get_daily_event_html('<span style="background:' . $color .'" data-subid= ' . $event->ID . '>' . $starttime . ' - ' . $endtime . '</span>');
        $eventDates = $calendar->addDailyHtml($html, $date);
      endforeach;
    endif;
    return $calendar->show(false);
  }

  public function add_sub_event($args = array(), $date, $meta) {
    $unique = $this->build_unique_key($date, $meta);
    $return = array();
    $args = array(
      'fields' => 'ids',
      'post_type'   => 'sub_event',
      'meta_query'  => array(
        array(
          'key' => 'unique_event_key',
          'value' => $unique
        )
      )
    );

    $meta['event_time_stamp'] = date ( 'Y-m-d H:i:s', strtotime ($date . ' ' . $meta['starttime']) );
    $meta['unique_event_key'] = $unique;
    $meta['event_date'] = $date;
    $check_query = new WP_Query( $args );
    if( empty($check_query->have_posts()) ) :
      $defaults = array(
        'post_author'           => get_current_user_id(),
        'post_content'          => '',
        'post_title'            => $this->build_title($date, $meta),
        'post_excerpt'          => '',
        'post_status'           => 'publish',
        'post_type'             => 'sub_event',
        'post_parent'           => $this->eventID,
        'context'               => '',
        'meta_input'            => $meta,

      );
      $args = wp_parse_args($args, $defaults);
      $return = wp_insert_post($args);
      else :
        $return = false;
      endif;
      return $return;
    }


    private function build_unique_key($date = '', $times = '') {
      return sanitize_title($date . '_' . $times['starttime'] . '-' . $times['endtime']);
    }

    private function build_title($date = '', $times = '') {
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







  }
