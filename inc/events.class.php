<?php


class mindEvent {

  private $eventID = '';
  private $wp_post = '';

  private $displayType = '';

  function __construct($id) {
    $this->eventID = $id;
    $this->wp_post = get_post($id);
  }


  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }


  public function get_sub_events_list() {
    $eventDates = $this->get_sub_events();
    if($eventDates) :

    endif;
  }







  public function get_front_calendar($calDate = '') {
    $calendar = new SimpleCalendar($calDate);
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      $html = '<div class="events">';
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'event_start', true);
        $endtime = get_post_meta($event->ID, 'event_end', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $color = get_post_meta($event->ID, 'event_color', true);
        if(!$color){
          $color = '#858585';
        }
        $inside = '<span
          data-title-backcolor="' . $color . '"
          data-title-textcolor="' . $this->getContrastColor($color) . '"
          style="background:' . $color .'"
          data-toggle="popover"
          data-placement="auto"
          title=""
          data-content="' . $starttime . ' - ' . $endtime . '"
          data-original-title="' . get_the_title(get_the_id()) . '">' . $starttime . '</span>';
        $html = $calendar->get_daily_event_html($inside);
        $eventDates = $calendar->addDailyHtml($html, $date);
      endforeach;
      $html = '</div>';
    endif;
    return $calendar->show(false);
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
    $eventDates = $this->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'event_start', true);
        $endtime = get_post_meta($event->ID, 'event_end', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $html = $calendar->get_daily_event_html('<span data-subid= ' . $event->ID . '>' . $starttime . ' - ' . $endtime . '</span>');
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
    $my_query = new WP_Query( $args );
    if( empty($my_query->have_posts()) ) :
      $defaults = array(
        'post_author'           => get_current_user_id(),
        'post_content'          => '',
        'post_title'            => $this->build_title($date, $meta),
        'post_excerpt'          => '',
        'post_status'           => 'publish',
        'post_type'             => 'sub_event',
        'post_parent'           => $this->eventID,
        'context'               => '',
        'meta_input' => array(
          'event_date' => $date,
          'event_start' => $meta['starttime'],
          'event_end' => $meta['endtime'],
          'event_color' => $meta['eventColor'],
          'event_meta' => $meta,
          'unique_event_key' => $unique
        )
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



    public function get_sub_events($args = array()) {
      $defaults = array(
        'meta_query' => array(
          // 'relation' => 'AND',
          'start_clause' => array(
            'key' => 'event_start',
            'compare' => 'EXISTS',
          ),
          'date_clause' => array(
            'key' => 'event_date',
            'compare' => 'EXISTS',
          ),
        ),
        'orderby' => array(
          'start_clause' => 'ASC',
          'date_clause' => 'DESC',
        ),

        // 'meta_value'       => '',
        'order'            => 'DESC',
        'post_type'        => 'sub_event',
        'post_parent'      => $this->eventID,
        'suppress_filters' => true,
        'posts_per_page' => -1
      );
      $args = wp_parse_args($args, $defaults);
      return get_posts($args);

    }



  }
