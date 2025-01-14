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
    $this->default_event_cost = (isset($this->options['mindevents_event_cost']) ? $this->options['mindevents_event_cost'] : '25');

    add_action( 'add_meta_boxes', array($this, 'add_events_metaboxes' ));

    add_action( 'save_post_events', array($this, 'save_meta_info'), 10, 2 );



	}
  static function add_events_metaboxes() {
    $options = get_option( 'mindevents_support_settings' );
    // add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
  	add_meta_box(
  		'mindevents_calendar',
  		'Calendar',
  		array('mindeventsAdmin', 'display_calendar_metabox' ),
  		'events',
  		'normal',
  		'default'
  	);

    add_meta_box(
  		'mindevents_event_options',
  		'Calendar Options',
  		array('mindeventsAdmin', 'display_event_options_metabox' ),
  		'events',
  		'side',
  		'default'
  	);


    // if($options['mindevents_enable_woocommerce'] == true && class_exists('woocommerce')) :
    //   add_meta_box(
    // 		'mindevents_woo_options',
    // 		'WooCommerce Options',
    // 		array('mindeventsAdmin', 'display_woo_options_metabox' ),
    // 		'events',
    // 		'side',
    // 		'default'
    // 	);
    // endif;


  }


  function save_meta_info( $post_id, $post ) {

    /* Make sure this is our post type. */
    if($post->post_type != 'events')
      return $post_id;

    /* Verify the nonce before proceeding. */
    if ( !isset( $_POST['mindevents_event_meta_nonce'] ) || !wp_verify_nonce( $_POST['mindevents_event_meta_nonce'], basename( __FILE__ ) ) )
      return $post_id;

    update_post_meta( $post_id, 'event_defaults', $_POST['event']);

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
      echo '<h3>Occurance Options</h3>';
      $mindeventsAdmin = new mindeventsAdmin();
      $mindeventsAdmin->get_time_form();

      $events = new mindEventCalendar($post->ID);

  		echo '<div class="calendar-nav">';
  			echo '<button data-dir="prev" class="calnav prev"><span>&#8592;</span></button>';
  			echo '<button data-dir="next" class="calnav next"><span>&#8594;</span></button>';
  		echo '</div>';
  		echo '<div id="eventsCalendar">';
        echo $events->get_calendar();
      echo '</div>';
      echo '<div id="errorBox"></div>';

      echo '<button class="clear-occurances button-danger">Clear All Occurances</button>';
    echo '</div>';
  }

  static function display_woo_options_metabox() {
    wp_nonce_field( basename( __FILE__ ), 'mindevents_event_meta_nonce' );
    echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';
      

      echo '<div class="form-section">';
        echo '<p class="label">';
          echo '<input type="checkbox" name="woo_create_variations" id="woo_create_variations" value="1" ' . checked(get_post_meta(get_the_ID(), 'woo_create_variations', true), '1', false) . '>';
          echo '<label for="woo_create_variations">Create Product Variation for Each event Occurance?</label>';
        echo '</p>';
      echo '</div>';


    echo '</div>';

  }



  private function get_time_form() {
    $defaults = get_post_meta(get_the_ID(), 'event_defaults', true);
    $options = get_option( 'mindevents_support_settings' );
    echo '<fieldset id="defaultEventMeta" class="event-times mindevents-forms">';
      echo '<div class="time-block">';
        echo '<div class="form-section">';
          echo '<p class="label"><label for="starttime">Event Occurence Start</label></p>';
          echo '<input type="text" class="timepicker required" name="event[starttime]" id="starttime" value="' . (isset($defaults['starttime']) ? $defaults['starttime'] : $this->default_start_time) . '" placeholder="">';
        echo '</div>';
        echo '<div class="form-section">';
          echo '<p class="label"><label for="endtime">Event Occurence End</label></p>';
          echo '<input type="text" class="timepicker" name="event[endtime]" id="endtime" value="' . (isset($defaults['endtime']) ? $defaults['endtime'] : $this->default_end_time) . '" placeholder="">';
        echo '</div>';

        echo '<div class="form-section">';
          echo '<p class="label"><label for="eventColor">Occurence Color</label></p>';
          echo '<input type="text" class="field-color" name="event[eventColor]" id="eventColor" value="' . (isset($defaults['eventColor']) ? $defaults['eventColor'] : '#23B38C') . '" placeholder="">';
        echo '</div>';

        echo '<div class="form-section full">';
          echo '<p class="label"><label for="eventDescription">Short Description</label></p>';
          echo '<textarea type="text" name="event[eventDescription]" id="eventDescription" value="' . (isset($defaults['eventDescription']) ? $defaults['eventDescription'] : '') . '" placeholder="">' . (isset($defaults['eventDescription']) ? $defaults['eventDescription'] : '') . '</textarea>';
        echo '</div>';
        
        if($options['mindevents_enable_woocommerce'] == true && class_exists('woocommerce')) :
            //add hiden inpuit
            echo '<input type="hidden" name="event[wooLinked]" id="wooLink" value="1">';

            echo '<div class="form-section">';
              echo '<p class="label"><label for="eventProductID">Ticket Button Text</label></p>';
              echo '<input type="text" name="event[wooLabel]" id="eventLinkedProduct" value="' . (isset($defaults['wooLabel']) ? $defaults['wooLabel'] : '') . '" placeholder="">';
            echo '</div>';

            echo '<div class="form-section">';
              echo '<p class="label"><label for="eventProductID">Linked Product</label></p>';
              echo '<input type="number" name="event[wooLinkedProduct]" id="eventLinkedProduct" value="' . (isset($defaults['wooLinkedProduct']) ? $defaults['wooLinkedProduct'] : '') . '" placeholder="">';
              echo '<p class="description">If left blank a new product will be created.</p>';
            echo '</div>';
            
            echo '<div class="form-section">';
              echo '<p class="label"><label for="eventCost">Event Cost</label></p>';
              echo '<input type="number" name="event[wooPrice]" id="eventCost" value="' . (isset($defaults['wooPrice']) ? $defaults['wooPrice'] : '') . '" placeholder="100">';
              echo '<p class="description">This will be the default cost for all tickets, it can be changed on the WooCommerce product.</p>';
            echo '</div>';

            echo '<div class="form-section">';
              echo '<p class="label"><label for="wooStock">Event Stock</label></p>';
              echo '<input type="number" name="event[wooStock]" id="wooStock" value="' . (isset($defaults['wooStock']) ? $defaults['wooStock'] : '4') . '" placeholder="4">';
              echo '<p class="description">This will be the stock limit for the associated product variation, it will be ignored for simple products.</p>';
            echo '</div>';
            
          
        else : 
          echo '<h3 class="offers-title">Tickets</h3>';
          echo '<div class="offer-options" id="allOffers">';
            echo '<div class="single-offer">';
              echo '<div class="form-section">';
                echo '<p class="label"><label for="eventLinkLabel">Ticket Label</label></p>';
                echo '<input type="text" name="event[offerlabel][]" id="eventLinkLabel" value="' . (isset($defaults['eventLinkLabel']) ? $defaults['eventLinkLabel'] : 'General Admission') . '" placeholder="">';
              echo '</div>';

              echo '<div class="form-section">';
                echo '<p class="label"><label for="eventCost">Price</label></p>';
                echo '<input type="text" name="event[offerprice][]" id="eventCost" value="' . (isset($defaults['eventCost']) ? $defaults['eventCost'] : '') . '" placeholder="">';
              echo '</div>';

              echo '<div class="form-section">';
                echo '<p class="label"><label for="eventLink">Link</label></p>';
                echo '<input type="text" name="event[offerlink][]" id="eventLink" value="' . (isset($defaults['eventLink']) ? $defaults['eventLink'] : '') . '" placeholder="">';
              echo '</div>';

              echo '<div class="add-offer">';
                echo '<span>+</span>';
              echo '</div>';
            echo '</div>';



          echo '</div>';
        endif;


      echo '</div>';

    echo '</fieldset>';
  }


}//end of class

new mindeventsAdmin();
