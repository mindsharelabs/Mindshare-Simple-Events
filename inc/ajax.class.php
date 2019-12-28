<?php


class mindeventsAjax {
  private $options = '';
  private $token = '';

  function __construct() {

    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

    // add_action( 'wp_ajax_nopriv_mindevents_generate_label', array( $this, 'accept_review' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'selectday', array( $this, 'selectday' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'clearevents', array( $this, 'clearevents' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'deleteevent', array( $this, 'deleteevent' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'movecalendar', array( $this, 'movecalendar' ) );


    add_action( 'wp_ajax_nopriv_' . MINDRETURNS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );


    add_action( 'wp_ajax_nopriv_' . MINDRETURNS_PREPEND . 'get_event_meta_html', array( $this, 'get_event_meta_html' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'get_event_meta_html', array( $this, 'get_event_meta_html' ) );

  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  static function get_event_meta_html() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'get_event_meta_html'){
      $id = $_POST['eventid'];
      $event = new mindEvent(get_the_ID());
      $html = $event->get_sub_event_list_html($id);

      $return = array(
        'success' => (bool)$meta,
        'html' => $html
      );
      wp_send_json_success($return);
    }
  }


  static function deleteevent() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'deleteevent'){
      $eventID = $_POST['eventid'];
      wp_delete_post($eventID);
    }
    wp_send_json_success();
  }

  private function getContrastColor($hexcolor) {
      $r = hexdec(substr($hexcolor, 1, 2));
      $g = hexdec(substr($hexcolor, 3, 2));
      $b = hexdec(substr($hexcolor, 5, 2));
      $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      return ($yiq >= 150) ? '#333' : '#fff';
  }

  static function selectday() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'selectday'){

      $date = $_POST['date'];
      $eventID = $_POST['eventid'];
      $metas = $_POST['meta'];
      $metas = $this->make_meta_array($metas);
      $event = new mindEvent($eventID);
      $calendar = new SimpleCalendar();


      $added_events = array();
      $errors = array();
      $html = '';

      foreach ($metas as $meta) {
        $added_event_id = $event->add_sub_event('', $date, $meta);
        if($added_event_id == false) :
          $errors[] = 'An event at that time already exists';
        elseif(is_wp_error($added_event_id)) :
          $errors[] = $added_event_id->get_error_message();
        else :
          $added_events[] = $added_event_id;
          $html .= $calendar->get_daily_event_html('<span style="background:' . $meta['eventColor'] . '" data-subid = ' . $added_event_id . ' class="new">' . $meta['starttime'] . '-' . $meta['endtime'] . '</span>');
        endif;
      }

      $return = array(
        'html' => $html,
        'events' => $added_events,
        'errors' => $errors
      );
      wp_send_json($return);

    }
  }

  static function clearevents() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'clearevents'){
      $eventID = $_POST['eventid'];
      $event = new mindEvent($eventID);
      $return = $event->delete_sub_events();
      $return = array(
        'html' => $event->get_calendar(),
        'success' => $return
      );
      wp_send_json($return);
    }
  }



  static function move_pub_calendar() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'move_pub_calendar'){
      $direction = $_POST['direction'];
      $month = $_POST['month'];
      $year = $_POST['year'];
      $eventID = $_POST['eventid'];
      $event = get_post($eventID);
      $date = new DateTime();
      $date->setDate($year, $month, 1);

      if($direction == 'prev') {
        $date->modify('first day of last month');
      } else {
        $date->modify('first day of next month');
      }
      $new_date = $date->format('Y-m-d');
      $event = new mindEvent($eventID);
      $return = array(
        'new_date' => $new_date,
        'html' => $event->get_front_calendar($new_date),
      );

      wp_send_json($return);
    }

  }



  static function movecalendar() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'movecalendar'){
      $direction = $_POST['direction'];
      $month = $_POST['month'];
      $year = $_POST['year'];
      $eventID = $_POST['eventid'];
      $event = get_post($eventID);
      $date = new DateTime();
      $date->setDate($year, $month, 1);

      if($direction == 'prev') {
        $date->modify('first day of last month');
      } else {
        $date->modify('first day of next month');
      }
      $new_date = $date->format('Y-m-d');
      $event = new mindEvent($eventID);
      $return = array(
        'new_date' => $new_date,
        'html' => $event->get_calendar($new_date),
      );

      wp_send_json($return);
    }

  }




  private function make_meta_array($times) {
    $return = array();
    foreach($times as $key => $time) {
      $occuranceNum = explode ('_', $key);
      $meta_item = $occuranceNum[0]; //this is 'starttime' or 'endtime' or eventColor
      $occurance_number = $occuranceNum[1]; //this is null or a number
      if($occurance_number == ''){$occurance_number = 1;}
      $return[$occurance_number][$meta_item] = $time;
    }

    return $return;
  }


}



new mindeventsAjax();
