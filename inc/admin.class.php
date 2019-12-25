<?php
class mindeventsDisplay {
  private $options = '';
  private $token = '';
  private $default_start_time = '';
  private $default_end_time = '';

  protected static $instance = NULL;

  public function __construct() {
    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

    $this->default_start_time = (isset($this->options['mindevents_start_time']) ? $this->options['mindevents_start_time'] : '7:00 PM');
    $this->default_end_time = (isset($this->options['mindevents_end_time']) ? $this->options['mindevents_end_time'] : '10:00 PM');

    add_action( 'add_meta_boxes', array($this, 'add_events_metaboxes' ));

	}
  static function add_events_metaboxes() {
    // add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
  	add_meta_box(
  		'mindevents_calendar',
  		'Calendar',
  		array($this, 'display_event_metabox' ),
  		'events',
  		'normal',
  		'default'
  	);

  }
  static function display_event_metabox($post) {
    echo '<div id="mindevents_meta_box">';
      $this->get_time_form();
      echo '<div class="calendar-nav">';
        echo '<button class="calnav prev">PREV</button>';
        echo '<button class="calnav next">PREV</button>';
      echo '</div>';

      echo '<div id="eventsCalendar">';
        echo $this->get_calendar($post);
      echo '</div>';
      echo '<div id="errorBox"></div>';
      echo '<button class="clear-occurances button button-danger">Clear All Occurances</button>';
    echo '</div>';
  }


  public function get_calendar($post) {
    $calendar = new SimpleCalendar();
    $event = new mindEvent($post->ID);
    $eventDates = $event->get_sub_events();
    if($eventDates) :
      foreach ($eventDates as $key => $event) :
        $starttime = get_post_meta($event->ID, 'event_start', true);
        $endtime = get_post_meta($event->ID, 'event_end', true);
        $date = get_post_meta($event->ID, 'event_date', true);
        $html = $calendar->get_daily_event_html('<span>' . $starttime . '-' . $endtime . '</span>');
        $eventDates = $calendar->addDailyHtml($html, $date);
      endforeach;
    endif;

    return $calendar->show(false);
  }

  private function get_time_form() {
    echo '<div id="defaultEventTimes" class="event-times mindeventsPage">';
      echo '<div class="time-block">';
        echo '<div class="form-section form-left">';
          echo '<p class="label"><label for="starttime_">Event Occurence Start</label></p>';
          echo '<input type="text" class="timepicker" name="starttime_" id="starttime_" value="' . $this->default_start_time . '" placeholder="">';
        echo '</div>';
        echo '<div class="form-section form-right">';
          echo '<p class="label"><label for="endtime_">Event Occurence End</label></p>';
          echo '<input type="text" class="timepicker" name="endtime_" id="endtime_" value="' . $this->default_end_time . '" placeholder="">';
        echo '</div>';
      echo '</div>';
      echo '<button class="plus add-event-occurrence">Add Occurence</button>';

    echo '</div>';
  }


}//end of class

new mindeventsDisplay();
