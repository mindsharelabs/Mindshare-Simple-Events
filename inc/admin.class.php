<?php
class mindeventsAdmin {
  private $options = '';
  private $token = '';
  private $default_start_time = '';
  private $default_end_time = '';
  private $default_event_color = '';

  protected static $instance = NULL;

  public function __construct() {
    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

    $this->default_start_time = (isset($this->options['mindevents_start_time']) ? $this->options['mindevents_start_time'] : '7:00 PM');
    $this->default_end_time = (isset($this->options['mindevents_end_time']) ? $this->options['mindevents_end_time'] : '10:00 PM');
    $this->default_event_color = (isset($this->options['mindevents_event_color']) ? $this->options['mindevents_event_color'] : '#43A0D9');

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
      echo '<div id="errorBox"></div>';
      $this->get_time_form();
      $events = new mindEvent($post->ID);

  		echo '<div class="calendar-nav">';
  			echo '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
  			echo '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
  		echo '</div>';
  		echo '<div id="eventsCalendar">';
        echo $events->get_calendar();
      echo '</div>';


      echo '<button class="clear-occurances button button-danger">Clear All Occurances</button>';
    echo '</div>';
  }




  private function get_time_form() {
    echo '<div id="defaultEventMeta" class="event-times mindeventsPage">';
      echo '<div class="time-block">';
        echo '<div class="form-section">';
          echo '<p class="label"><label for="starttime_">Event Occurence Start</label></p>';
          echo '<input type="text" class="timepicker required" name="starttime_" id="starttime_" value="' . $this->default_start_time . '" placeholder="">';
        echo '</div>';
        echo '<div class="form-section">';
          echo '<p class="label"><label for="endtime_">Event Occurence End</label></p>';
          echo '<input type="text" class="timepicker required" name="endtime_" id="endtime_" value="' . $this->default_end_time . '" placeholder="">';
        echo '</div>';
        echo '<div class="form-section">';
          echo '<p class="label"><label for="eventLink_">Event Link</label></p>';
          echo '<input type="text" name="eventLink_" id="eventLink_" value="" placeholder="">';
        echo '</div>';

        echo '<div class="form-section">';
          echo '<p class="label"><label for="eventLinkLabel_">Link Label</label></p>';
          echo '<input type="text" name="eventLinkLabel_" id="eventLinkLabel_" value="" placeholder="">';
        echo '</div>';

        echo '<div class="form-section">';
          echo '<p class="label"><label for="eventCost_">Event Cost</label></p>';
          echo '<input type="text" name="eventCost_" id="eventCost_" value="" placeholder="">';
        echo '</div>';

        echo '<div class="form-section">';
          echo '<p class="label"><label for="eventColor_">Occurence Color</label></p>';
          echo '<input type="text" name="eventColor_" id="eventColor_" value="' . $this->default_event_color . '" placeholder="">';
        echo '</div>';

        echo '<div class="form-section full">';
          echo '<p class="label"><label for="eventDescription_">Short Description</label></p>';
          echo '<textarea type="text" name="eventDescription_" id="eventDescription_" value="" placeholder=""></textarea>';
        echo '</div>';

      echo '</div>';
      echo '<button class="plus add-event-occurrence">Add Occurence</button>';

    echo '</div>';
  }


}//end of class

new mindeventsAdmin();
