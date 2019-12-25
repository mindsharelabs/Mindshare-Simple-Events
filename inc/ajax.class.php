<?php


class mindeventsAjax {
  private $options = '';
  private $token = '';

  function __construct() {
    $this->define( 'MINDRETURNS_PREPEND', 'mindevents_' );

    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

    // add_action( 'wp_ajax_nopriv_mindevents_generate_label', array( $this, 'accept_review' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'selectday', array( $this, 'selectday' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'clearevents', array( $this, 'clearevents' ) );

  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  static function selectday() {

    if($_POST['action'] == MINDRETURNS_PREPEND . 'selectday'){

      $date = $_POST['date'];
      $eventID = $_POST['eventid'];
      $times = $_POST['times'];
      $times = $this->make_time_array($times);
      $event = new mindEvent($eventID);
      $calendar = new SimpleCalendar();


      $added_events = array();
      $errors = array();
      $html = '';

      foreach ($times as $time) {
        $added_event_id = $event->add_sub_event('', $date, $time);
        if($added_event_id == false) :
          $errors[] = 'An event at that time already exists';
        elseif(is_wp_error($added_event_id)) :
          $errors[] = $added_event_id->get_error_message();
        else :
          $added_events[] = $added_event_id;
          $html .= $calendar->get_daily_event_html('<span class="new">' . $time['starttime'] . '-' . $time['endtime'] . '</span>');
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
      $display = new mindeventsDisplay();
      $return = $event->delete_sub_events();
      $return = array(
        'html' => $display->get_calendar($eventID),
        'success' => $return
      );
      wp_send_json($return);
    }
  }

  private function make_time_array($times) {
    $return = array();
    foreach($times as $key => $time) {
      $occuranceNum = explode ('_', $key);
      $start_or_end = $occuranceNum[0]; //this is either 'starttime' or 'endtime'
      $occurance_number = $occuranceNum[1]; //this is null or a number
      if($occurance_number == ''){$occurance_number = 1;}
      $return[$occurance_number][$start_or_end] = $time;
    }
    return $return;
  }


}



new mindeventsAjax();
