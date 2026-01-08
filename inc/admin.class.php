<?php
class mindeventsAdmin {
  private $options = '';
  private $token = '';
  private $default_start_time = '';
  private $default_end_time = '';
  private $default_event_color = '';


  protected static $instance = NULL;

  public function __construct() {
    $this->options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
    $this->token = (isset($this->options[MINDEVENTS_PREPEND . 'api_token']) ? $this->options[MINDEVENTS_PREPEND . 'api_token'] : false);

    $this->default_start_time = (isset($this->options[MINDEVENTS_PREPEND . 'start_time']) ? $this->options[MINDEVENTS_PREPEND . 'start_time'] : '2:00 PM');
    $this->default_end_time = (isset($this->options[MINDEVENTS_PREPEND . 'end_time']) ? $this->options[MINDEVENTS_PREPEND . 'end_time'] : '6:00 PM');
    $this->default_event_color = (isset($this->options[MINDEVENTS_PREPEND . 'event_color']) ? $this->options[MINDEVENTS_PREPEND . 'event_color'] : '#43A0D9');
    $this->default_event_cost = (isset($this->options[MINDEVENTS_PREPEND . 'event_cost']) ? $this->options[MINDEVENTS_PREPEND . 'event_cost'] : '25');

    add_action( 'add_meta_boxes', array($this, 'add_events_metaboxes' ));

    add_action( 'save_post_events', array($this, 'save_meta_info'), 10, 2 );



	}
  static function add_events_metaboxes() {
    $options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
    // add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
  	add_meta_box(
  		MINDEVENTS_PREPEND . 'calendar',
  		'Calendar',
  		array('mindeventsAdmin', 'display_calendar_metabox' ),
  		'events',
  		'normal',
  		'default'
  	);

    add_meta_box(
  		MINDEVENTS_PREPEND . 'event_options',
  		'Calendar Options',
  		array('mindeventsAdmin', 'display_event_options_metabox' ),
        'events',
        'side',
        'default'
      );


    add_meta_box(
  		MINDEVENTS_PREPEND . 'attendees',
  		'Event Attendees',
        array('mindeventsAdmin', 'display_attendees_metabox' ),
        'events',
        'normal',
        'default'
      );

    add_meta_box(
  		MINDEVENTS_PREPEND . 'product_event_info', //id
  		'Event Details', //title
  		array('mindeventsAdmin', 'display_product_event_info' ), //callback
        'product', // screen
        'normal', //context
        'default', //priority
        null //callback_args
      );

    

  }


  static function display_product_event_info() {
    $linked_event = get_post_meta(get_the_ID(), 'linked_event', true);
    if($linked_event) :
      echo '<div class="mindevents_meta_box" id="mindevents_meta_box">';
        echo '<p><strong>This is a ticket for the event:</strong> <a href="' . get_edit_post_link($linked_event) . '" target="_blank">' . get_the_title($linked_event) . '</a></p>';
      echo '</div>';
    endif;
  }


  static function display_event_options_metabox() {
    $global_options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
    $cal = get_post_meta(get_the_ID(), 'cal_display', true);
    $show_past_events = get_post_meta(get_the_ID(), 'show_past_events', true);
    $ticket_stock = (get_post_meta(get_the_ID(), 'ticket_stock', true) ? get_post_meta(get_the_ID(), 'ticket_stock', true) : 4);
    $ticket_price = (get_post_meta(get_the_ID(), 'ticket_price', true) ? get_post_meta(get_the_ID(), 'ticket_price', true) : 120);
    $event_type = get_post_meta(get_the_ID(), 'event_type', true);
    $has_tickets = get_post_meta(get_the_ID(), 'has_tickets', true);



    
    wp_nonce_field( basename( __FILE__ ), MINDEVENTS_PREPEND . 'event_meta_nonce' );
    echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';

    
      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta_event_type">Event Type</label></p>';
        echo '<div class="select-wrap">';
          echo '<select class="required" name="event_meta[event_type]" id="event_meta_event_type">';
            echo '<option value="" disabled selected>(Select Type)</option>';
            echo '<option value="multiple-events" ' . selected($event_type, 'multiple-events', false) . '>Multiple Unique Events</option>';
            echo '<option value="single-event" ' . selected($event_type, 'single-event', false) . '>One Event Multiple Dates</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';

      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta_has_tickets">Has Tickets?</label></p>';
        echo '<div class="select-wrap">';
          echo '<select name="event_meta[has_tickets]" id="event_meta_has_tickets">';
            echo '<option value="1" ' . selected($has_tickets, '1', false) . '>Yes</option>';
            echo '<option value="0" ' . selected($has_tickets, '0', false) . '>No</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';


      //if woocommerce is enabled
      if($global_options[MINDEVENTS_PREPEND . 'enable_woocommerce'] == true && class_exists('woocommerce')) :
        echo '<div class="form-section ticket-option single-option">';
          echo '<p class="label"><label for="event_meta_ticket_stock">Available Tickets</label></p>';
          echo '<div class="input-wrap">';
            echo '<input type="number" name="event_meta[ticket_stock]" id="event_meta_ticket_stock" value="' . $ticket_stock . '">';
            echo '<p class="description">This will be the total stock for the event ticket.</p>';
           echo '</div>';
        echo '</div>';


        echo '<div class="form-section ticket-option single-option">';
          echo '<p class="label"><label for="event_meta_ticket_price">Ticket Price</label></p>';
          echo '<div class="input-wrap">';
            echo '<input type="number" name="event_meta[ticket_price]" id="event_meta_ticket_price" value="' . $ticket_price . '">';
            echo '<p class="description">This will be the ticket price for this event.</p>';
           echo '</div>';
        echo '</div>';
      endif;


      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta_cal_display">Calendar Display</label></p>';
        echo '<div class="select-wrap">';
          echo '<select name="event_meta[cal_display]" id="event_meta_cal_display">';
            echo '<option value="list" ' . selected($cal, 'list', false) . '>List</option>';
            echo '<option value="calendar" ' . selected($cal, 'calendar', false) . '>Calendar</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';

      echo '<div class="form-section">';
        echo '<p class="label"><label for="event_meta_show_past_events">Show Past Events?</label></p>';
        echo '<div class="select-wrap">';
          echo '<select name="event_meta[show_past_events]" id="event_meta_show_past_events">';
            echo '<option value="0" ' . selected($show_past_events, '0', false) . '>Show only future events</option>';
            echo '<option value="1" ' . selected($show_past_events, '1', false) . '>Show all events</option>';
          echo '</select>';
        echo '</div>';
      echo '</div>';


    // Add Featured checkbox before closing div
    $is_featured = get_post_meta(get_the_ID(), 'is_featured', true);
    echo '<div class="form-section">';
      echo '<p class="label"><label for="event_meta_is_featured">';
      echo '<input type="checkbox" name="event_meta[is_featured]" id="event_meta_is_featured" value="1" ' . checked($is_featured, '1', false) . '> Featured Event';
      echo '</label></p>';
    echo '</div>';

    // Add Featured checkbox before closing div
    $is_featured = get_post_meta(get_the_ID(), 'is_featured', true);
    echo '<div class="form-section">';
      echo '<p class="label"><label for="event_meta_is_featured">';
      echo '<input type="checkbox" name="event_meta[is_featured]" id="event_meta_is_featured" value="1" ' . checked($is_featured, '1', false) . '> Featured Event';
      echo '</label></p>';
    echo '</div>';

    // Add expense fields for profit calculation
    $instructor_expense = get_post_meta(get_the_ID(), 'instructor_expense', true);
    $materials_expense = get_post_meta(get_the_ID(), 'materials_expense', true);
    
    echo '<div class="form-section">';
      echo '<p class="label"><label for="event_meta_instructor_expense">Instructor Expense ($)</label></p>';
      echo '<div class="input-wrap">';
        echo '<input type="number" name="event_meta[instructor_expense]" id="event_meta_instructor_expense" value="' . esc_attr($instructor_expense) . '" step="0.01" min="0">';
        echo '<p class="description">Cost for instructor per sub event</p>';
      echo '</div>';
    echo '</div>';

    echo '<div class="form-section">';
      echo '<p class="label"><label for="event_meta_materials_expense">Materials Expense ($)</label></p>';
      echo '<div class="input-wrap">';
        echo '<input type="number" name="event_meta[materials_expense]" id="event_meta_materials_expense" value="' . esc_attr($materials_expense) . '" step="0.01" min="0">';
        echo '<p class="description">Cost for materials per attendee (will be multiplied by number of attendees)</p>';
      echo '</div>';
    echo '</div>';

    echo '</div>';
  }


  static function display_calendar_metabox($post) {
    echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';
      echo '<h3>Occurance Options</h3>';
      $mindeventsAdmin = new mindeventsAdmin();
      $mindeventsAdmin->get_time_form();

      $events = new mindEventCalendar($post->ID, time(), true);

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

  static function display_attendees_metabox($post) {

    $attendees = get_post_meta($post->ID, 'attendees', true);
    $event_type = get_post_meta($post->ID, 'event_type', true);

    if($event_type == 'single-event') :
      $sub_events[$post->ID] = $post->ID; //the event we're getting tickets for is the parent now
    else :
      $sub_events = get_post_meta($post->ID, 'sub_events', true);
    endif;

    $has_tickets = get_post_meta($post->ID, 'has_tickets', true);
    
    $today = new DateTime();
    $today = $today->format('Y-m-d');

    if($has_tickets) :
      echo '<div class="mindevents_meta_box mindevents-forms" id="mindevents_meta_box">';
          if($attendees && $sub_events) :          
              $columns = apply_filters(MINDEVENTS_PREPEND . 'attendee_columns', array(
                'order_id' => 'Order ID',
                'status' => 'Status',
                'user_id' => 'Attendee',
                'product' => 'Product',
                'check_in' => 'Check In',
              ));


              
              foreach($sub_events as $sub_event) :
                $attendees_for_occurance = (isset($attendees[$sub_event]) ? $attendees[$sub_event] : array());
                $meta_start_date = get_post_meta($sub_event, 'event_start_time_stamp', true);
                  
                if(!$meta_start_date) :
                  $meta_start_date = get_post_meta($post->ID, 'first_event_date', true);
                endif;

                $event_start_time_stamp = new DateTimeImmutable($meta_start_date);
                $event_start_day = $event_start_time_stamp->format('Y-m-d');

                echo '<div class="occurance-container ' . ($today == $event_start_day ? 'today' : '') . ' ' . ($today > $event_start_day ? 'past-event' : '') . '">';
                  if($event_type == 'single-event') :
                    echo '<h3>Series Attendees</h3>'; 
                    
                  else :
                    echo '<h3>' . $event_start_time_stamp->format('F j, Y') . '</h3>';
                  endif;

                  echo ($today > $event_start_day ? '<span class="small toggle-expand">(click to toggle table)</span>' : '');

                  echo '<table class="event-attendees wp-list-table widefat fixed striped">';
                    echo '<thead>';
                      echo '<tr>';
                        foreach($columns as $key => $value) :
                          echo '<th>' . $value . '</th>';
                        endforeach;
                      echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';


                      if($attendees_for_occurance) :
                      foreach($attendees_for_occurance as $akey => $ticket) :

                        if($ticket) : 
     
                            $order = wc_get_order($ticket['order_id']);
                            $ticket_data = apply_filters(MINDEVENTS_PREPEND . 'attendee_data', array(
                              'order_id' => $ticket['order_id'],
                              'status' => ($order ? $order->get_status() : 'order not found'),
                              'user_id' => $ticket['user_id'],
                              'product' => get_post_meta($sub_event, 'linked_product', true),
                              'checked_in' => $ticket['checked_in'],
                            ));
                            $user_info = get_userdata($ticket_data['user_id']);
                            

                            //if user_ifo is not an object, skip this ticket
                            if(!is_object($user_info)) {
                              continue;
                            }
          
                            echo '<tr>';
                              foreach($ticket_data as $key => $value) :

                                if($key == 'user_id') :
                                  $value = '<a href="' . get_edit_user_link($ticket_data['user_id']) . '" target="_blank">' . $user_info->data->display_name . '</a>';
                                
                                
                                elseif($key == 'product') :
                                  $product = wc_get_product($value);
                                  if($product) :
                                    $value = '<a href="' . get_edit_post_link($product->get_id()) . '" target="_blank">' . $product->get_title() . '</a>';
                                  else :
                                    $value = '<strong>Product not found</strong>';
                                  endif;
                                              
                              
                                elseif($key == 'status') :
                                  $value = '<span class="status ' . $value . '">' . wc_get_order_status_name($value) . '</span>';
                                
                                  
                                elseif($key == 'checked_in') :
                                  $checked_in = $value;
                                  if($order) :
                                    $value = '<button 
                                      class="atendee-check-in ' . ($checked_in ? 'checked-in' : '') . ' ' . ($order->get_status() != 'completed' ? 'disabled' : '') . '" 
                                      data-akey="' . $akey  . '" 
                                      data-occurance="' . $sub_event . '" 
                                      data-user_id="' . $ticket_data['user_id'] . '" ' . 
                                      ($order->get_status() != 'completed' ? 'disabled' : '') . '>';
            
            
                                      if($order->get_status() == 'completed') :
                                        $value .= '<span class="check-in-status">' . ($checked_in ? 'Undo Checkin' : 'Checkin') . '</span>';
                                      else :
                                        $value .= '<span class="check-in-status">Order not completed</span>';
                                      endif;
                                                    
                                    $value .= '</button>';
                                  else :
                                    $value = '<span class="status">Order not found</span>';  
                                  endif;
          
                                            
                                
                                  
                                  
                                elseif($key == 'order_id') :
                                  $value = '<a href="' . admin_url('post.php?post=' . $value . '&action=edit') . '" target="_blank">' . $value . '</a>';
                                endif;
          
          
                                echo '<td>' . apply_filters(MINDEVENTS_PREPEND . 'attendee_value', $value) . '</td>';
          
                              endforeach;
          
                            echo '</tr>';
          
        
                        endif;
                        
                      endforeach;
                      else :
                        echo '<tr><td colspan="' . count($columns) . '">No Attendees</td></tr>';
                      endif;
                    echo '</tbody>';
                  echo '</table>';
                echo '</div>';















              endforeach;


              
              
              
          
          endif;
        
      echo '</div>';
    endif;
  }


  static function save_meta_info( $post_id, $post ) {

    /* Make sure this is our post type. */
    if($post->post_type != 'events')
      return $post_id;

    /* Verify the nonce before proceeding. */
    if ( !isset( $_POST[MINDEVENTS_PREPEND . 'event_meta_nonce'] ) || !wp_verify_nonce( $_POST[MINDEVENTS_PREPEND . 'event_meta_nonce'], basename( __FILE__ ) ) )
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
  /* Get All orders IDs for a given product ID.
  *
  * @param  integer  $product_id (required)
  * @param  array    $order_status (optional) Default is 'wc-completed'
  *
  * @return array
  */
  static function get_orders_ids_by_product_id( $product_id, $order_status = array( 'wc-completed' ) ){
     global $wpdb;
 
     $results = $wpdb->get_col("
         SELECT order_items.order_id
         FROM {$wpdb->prefix}woocommerce_order_items as order_items
         LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
         LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
         WHERE posts.post_type = 'shop_order'
         AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' )
         AND order_items.order_item_type = 'line_item'
         AND order_item_meta.meta_key = '_product_id'
         AND order_item_meta.meta_value = '$product_id'
         LIMIT 5000
     ");
 
     return $results;
 }


  private function get_time_form() {
    $defaults = get_post_meta(get_the_ID(), 'event_defaults', true);
    $options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
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
          echo '<input type="text" class="field-color" name="event[eventColor]" id="eventColor" value="' . (isset($defaults['eventColor']) ? $defaults['eventColor'] : '') . '" placeholder="">';
          echo '<p class="description">If left blank this will default to event category color.</p>';
        echo '</div>';
        

        echo '<div class="form-section full">';
          echo '<p class="label"><label for="eventDescription">Short Description</label></p>';
          echo '<textarea type="text" name="event[eventDescription]" id="eventDescription" value="' . (isset($defaults['eventDescription']) ? $defaults['eventDescription'] : '') . '" placeholder="">' . (isset($defaults['eventDescription']) ? $defaults['eventDescription'] : '') . '</textarea>';
        echo '</div>';

        
        
        if($options[MINDEVENTS_PREPEND . 'enable_woocommerce'] == true && class_exists('woocommerce')) :
            //add hiden inpuit
            echo '<input type="hidden" name="event[wooLinked]" id="wooLink" value="1">';

            echo '<div class="form-section ticket-option multiple-option">';
              echo '<p class="label"><label for="eventProductID">Ticket Button Text</label></p>';
              echo '<input type="text" name="event[wooLabel]" id="eventLinkedProduct" value="' . (isset($defaults['wooLabel']) ? $defaults['wooLabel'] : '') . '" placeholder="Join Class">';
            echo '</div>';

            echo '<div class="form-section ticket-option multiple-option">';
              echo '<p class="label"><label for="eventProductID">Linked Product</label></p>';
              echo '<input type="number" name="event[linked_product]" id="eventLinkedProduct" value="' . (isset($defaults['linked_product']) ? $defaults['linked_product'] : '') . '" placeholder="">';
              echo '<p class="description">If left blank a new product will be created.</p>';
            echo '</div>';
            
            echo '<div class="form-section ticket-option multiple-option">';
              echo '<p class="label"><label for="eventCost">Event Cost</label></p>';
              echo '<input type="number" name="event[ticket_price]" id="eventCost" value="' . (isset($defaults['ticket_price']) ? $defaults['ticket_price'] : '100') . '" >';
              echo '<p class="description">This will be the default cost for all tickets, it can be changed on the WooCommerce product.</p>';
            echo '</div>';

            echo '<div class="form-section ticket-option multiple-option">';
              echo '<p class="label"><label for="ticket_stock">Event Stock</label></p>';
              echo '<input type="number" name="event[ticket_stock]" id="ticket_stock" value="' . (isset($defaults['ticket_stock']) ? $defaults['ticket_stock'] : '4') . '" placeholder="4">';
              echo '<p class="description">This will be the stock limit for the associated product, it will be ignored for events without products.</p>';
            echo '</div>';
            
        else : 
         
          echo '<div class="offer-options ticket-option" id="allOffers">';
            echo '<h3 class="offers-title">Tickets</h3>';

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
