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

    $this->default_start_time = (isset($this->options['mindevents_start_time']) ? $this->options['mindevents_start_time'] : '2:00 PM');
    $this->default_end_time = (isset($this->options['mindevents_end_time']) ? $this->options['mindevents_end_time'] : '6:00 PM');
    $this->default_event_color = (isset($this->options['mindevents_event_color']) ? $this->options['mindevents_event_color'] : '#43A0D9');
    $this->default_event_cost = (isset($this->options['mindevents_event_cost']) ? $this->options['mindevents_event_cost'] : '$25');

    add_action( 'add_meta_boxes', array($this, 'add_events_metaboxes' ));

    add_action( 'save_post', array($this, 'save_meta_info'), 10, 2 );



	}
  static function add_events_metaboxes() {
    // add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
  	add_meta_box(
  		'mindevents_calendar',
  		'Calendar',
  		array($this, 'display_calendar_metabox' ),
  		'events',
  		'normal',
  		'default'
  	);

    add_meta_box(
  		'mindevents_event_options',
  		'Calendar Options',
  		array($this, 'display_event_options_metabox' ),
  		'events',
  		'side',
  		'default'
  	);

  }


  function save_meta_info( $post_id, $post ) {

    /* Make sure this is our post type. */
    if($post->post_type != 'events')
      return $post_id;

    /* Verify the nonce before proceeding. */
    if ( !isset( $_POST['mindevents_event_meta_nonce'] ) || !wp_verify_nonce( $_POST['mindevents_event_meta_nonce'], basename( __FILE__ ) ) )
      return $post_id;



    $field_key = 'event_meta';
    /* Get the posted data and sanitize it for use as an HTML class. */
    $new_meta_values = (isset( $_POST[$field_key]) ? $_POST[$field_key]  : '' );
    if($new_meta_values) :
      foreach ($new_meta_values as $key => $value) :
        update_post_meta( $post_id, $key, $value);
      endforeach;
    endif;

    return $post_id;
  }





  static function display_event_options_metabox() {
    $cal = get_post_meta(get_the_ID(), 'cal_display', true);
    $show_past_events = get_post_meta(get_the_ID(), 'show_past_events', true);
    wp_nonce_field( basename( __FILE__ ), 'mindevents_event_meta_nonce' );
    echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';
      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta[cal_display]">Calendar Display</label></p>';
        echo '<div class="select-wrap">';
          echo '<select name="event_meta[cal_display]" id="event_meta[cal_display]">';
            echo '<option value="calendar" ' . selected($cal, 'calendar', false) . '>Calendar</option>';
            echo '<option value="list" ' . selected($cal, 'list', false) . '>List</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';

      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta[show_past_events]">Show Past Events?</label></p>';
        echo '<div class="select-wrap">';
          echo '<select name="event_meta[show_past_events]" id="event_meta[show_past_events]">';
            echo '<option value="1" ' . selected($show_past_events, '1', false) . '>Show all events</option>';
            echo '<option value="0" ' . selected($show_past_events, '0', false) . '>Show only future events</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';
    echo '</div>';
  }







  static function display_calendar_metabox($post) {
    echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';
      echo '<h3>Select Occurance Options</h3>';
      echo '<small>These fields do not save, they simply set the information for occurances added to the calendar.</small>';
      $this->get_time_form();
      $events = new mindEventCalendar($post->ID);

  		echo '<div class="calendar-nav">';
  			echo '<button data-dir="prev" class="calnav prev">PREV MONTH</button>';
  			echo '<button data-dir="next" class="calnav next">NEXT MONTH</button>';
  		echo '</div>';
  		echo '<div id="eventsCalendar">';
        echo $events->get_calendar();
      echo '</div>';
      echo '<div id="errorBox"></div>';

      echo '<button class="clear-occurances button-danger">Clear All Occurances</button>';
    echo '</div>';
  }




  private function get_time_form() {
    // TODO: Rename these fields to match this name format.
    // <input type="text" placeholder="Titel" name="sub_event_meta[starttime][]">
    // <input type="text" placeholder="Titel" name="sub_event_meta[endtime][]">


    echo '<div id="defaultEventMeta" class="event-times mindevents-forms">';
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
          echo '<input type="text" name="eventCost_" id="eventCost_" value="' . $this->default_event_cost . '" placeholder="">';
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
