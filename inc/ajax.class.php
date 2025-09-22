<?php


class mindEventsAjax {
  private $options = '';
  private $token = '';

  function __construct() {

    $this->options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
    $this->token = (isset($this->options[MINDEVENTS_PREPEND . 'api_token']) ? $this->options[MINDEVENTS_PREPEND . 'api_token'] : false);

    // add_action( 'wp_ajax_nopriv_mindevents_generate_label', array( $this, 'accept_review' ) );
    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'selectday', array( $this, 'selectday' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'clearevents', array( $this, 'clearevents' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'deleteevent', array( $this, 'deleteevent' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'editevent', array( $this, 'editevent' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'moveevent', array( $this, 'moveevent' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'updatesubevent', array( $this, 'updatesubevent' ) );

    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'movecalendar', array( $this, 'movecalendar' ) );


    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'checkin_toggle', array( $this, 'checkin_toggle' ) );


    add_action( 'wp_ajax_nopriv_' . MINDEVENTS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );
    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );

    add_action( 'wp_ajax_nopriv_' . MINDEVENTS_PREPEND . 'move_archive_calendar', array( $this, 'move_archive_calendar' ) );
    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'move_archive_calendar', array( $this, 'move_archive_calendar' ) );


    add_action( 'wp_ajax_nopriv_' . MINDEVENTS_PREPEND . 'get_event_meta_html', array( $this, 'get_event_meta_html' ) );
    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'get_event_meta_html', array( $this, 'get_event_meta_html' ) );


    add_action( 'wp_ajax_nopriv_' . MINDEVENTS_PREPEND . 'add_woo_product_to_cart', array( $this, 'add_woo_product_to_cart' ) );
    add_action( 'wp_ajax_' . MINDEVENTS_PREPEND . 'add_woo_product_to_cart', array( $this, 'add_woo_product_to_cart' ) );

  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }

  public function get_event_meta_html() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'get_event_meta_html'){
      $id = $_POST['eventid'];
      $event = new mindEventCalendar();
      $html = $event->get_cal_meta_html($id);

      $return = array(
        'html' => $html
      );
      wp_send_json_success($return);
    }
  }




  public function deleteevent() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'deleteevent'){
      $eventID = $_POST['eventid'];

      //get partent
      $parent = get_post($eventID);
      $parentID = $parent->post_parent;
      //check if event has attendees
      $attendees = get_post_meta($parentID, 'attendees', true);
      if(isset($attendees[$eventID])) :
        if($attendees[$eventID]) :
          wp_send_json_error('This event has attendees, please reschedule or remove the attendees before deleting the event.');
        endif;
      endif;


      do_action('mindreturns_before_sub_event_deleted', $eventID);
      wp_delete_post($eventID);
      do_action('mindreturns_after_sub_event_deleted', $eventID);
      wp_send_json_success();
    }
    wp_send_json_error();
  }

  private function getContrastColor($hexcolor) {
      $r = hexdec(substr($hexcolor, 1, 2));
      $g = hexdec(substr($hexcolor, 3, 2));
      $b = hexdec(substr($hexcolor, 5, 2));
      $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      return ($yiq >= 150) ? '#333' : '#fff';
  }



  public function selectday() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'selectday'){
      $date = $_POST['date']; 
      $meta = $_POST['meta']['event'];
      $meta['event_date'] = $date;


      $eventID = $_POST['eventid'];
      $insideHTML = '';
     
      $dateformat = get_option('date_format');
      $timeformat = get_option('time_format');

      // Normalize date/time into timestamp metas
      $tz     = wp_timezone();
      $fmtDT  = 'Y-m-d H:i:s';
      $start_time = isset($meta['starttime']) ? $meta['starttime'] : '00:00';
      $end_time   = isset($meta['endtime'])   ? $meta['endtime']   : $start_time;
      $startDT = date_create_immutable($date . ' ' . $start_time, $tz);
      $endDT   = date_create_immutable($date . ' ' . $end_time,   $tz);

      if ($startDT && $endDT) {
        $meta['event_start_time_stamp'] = $startDT->format($fmtDT);
        $meta['event_end_time_stamp']   = $endDT->format($fmtDT);

      }

      if($meta['eventColor'] == '') :
        $meta['eventColor'] = '#2d8703';
      endif;

      $event = new mindEventCalendar($eventID);

      $added_events = array();
      $errors = array();

      
      $added_event_id = $event->add_sub_event($date, $meta, $eventID);

      if($added_event_id == false) :
        $errors[] = 'An event at that time already exists';
      elseif(is_wp_error($added_event_id)) :
        $errors[] = $added_event_id->get_error_message();
      else :
        $added_events[] = $added_event_id;

        $insideHTML .= '<div class="event" id="event-' . $added_event_id . '">';
          $insideHTML .= '<span style="background:' . $meta['eventColor'] . '; color:' . $this->getContrastColor($meta['eventColor']) . ';" data-subid = ' . $added_event_id . ' class="new edit">';
            $insideHTML .= $startDT->format($timeformat) . '-' . $endDT->format($timeformat);
          $insideHTML .= '</span>';
          if(is_admin()) :
            $insideHTML .= '<span data-subid="' . $added_event_id . '" class="delete">&#10005;</span>';
          endif;
        $insideHTML .= '</div>';
      endif;

      $return = array(
        'html' => $insideHTML,
        'events' => $added_events,
        'errors' => $errors
      );
      wp_send_json($return);

    }
  }

  public function clearevents() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'clearevents'){
      $eventID = $_POST['eventid'];
      $event = new mindEventCalendar($eventID);
      $return = $event->delete_sub_events($eventID);
      $return = array(
        'html' => $event->get_calendar(),
        'success' => $return
      );
      wp_send_json($return);
    }
  }


  public function move_pub_calendar() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'move_pub_calendar'){
      $direction = $_POST['direction'];
      $month = $_POST['month'];
      $year = $_POST['year'];
      $date = new DateTime();
      $date->setDate($year, $month, 1);
      if($direction == 'prev') {
        $date->modify('first day of last month');
      } else {
        $date->modify('first day of next month');
      }
      $new_date = $date->format('Y-m-d');


      $cat = (isset($_POST['category']) ? $_POST['category'] : false);

  

      if($cat) :
        $eventID = 'archive';
      else :
        $eventID = $_POST['eventid'];
      endif;


      $calendar = new mindEventCalendar($eventID, $new_date);
      if($cat) :
        $calendar->setEventCategories($cat);
      endif;



      $return = array(
        'new_date' => $new_date,
        'html' => $calendar->get_front_calendar($eventID),
      );

      wp_send_json($return);
    }

  }


  public function updatesubevent() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'updatesubevent'){

      $id = $_POST['eventid'];
      $meta = $_POST['meta'];

      $event_start_time_stamp = date('Y-m-d H:i:s', strtotime($_POST['meta']['event_date'] . ' ' . $_POST['meta']['starttime']));
      $event_end_time_stamp = date('Y-m-d H:i:s', strtotime($_POST['meta']['event_date'] . ' ' . $_POST['meta']['endtime']));


      $meta['event_start_time_stamp'] = $event_start_time_stamp;
      $meta['event_end_time_stamp']   = $event_end_time_stamp;

      $event = new mindEventCalendar($_POST['parentid'], $_POST['meta']['event_date']);

      $event->update_sub_event($id, $meta, $_POST['parentid']);


      $return = array(
        'html' => $event->get_calendar()
      );
      wp_send_json_success($return);
    }
    wp_send_json_error();
  }


  public function moveevent() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'moveevent'){
      
   
      $parentid = isset($_POST['parentid']) ? absint($_POST['parentid']) : 0;
      $raw_new_date  = isset($_POST['new_date'])   ? sanitize_text_field( wp_unslash( $_POST['new_date'] ) )   : '';
      $raw_start     = isset($_POST['start_date']) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
      $raw_end       = isset($_POST['end_date'])   ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) )   : '';
      $event_id      = isset($_POST['eventid'])    ? absint( $_POST['eventid'] )                               : 0;
      if ( ! $event_id || $raw_new_date === '' || $raw_start === '' || $raw_end === '' ) {
        return;
      }
      $tz    = wp_timezone();
      $fmtD  = 'Y-m-d';
      $fmtDT = 'Y-m-d H:i:s';
      $newDate = DateTimeImmutable::createFromFormat('!'.$fmtD, $raw_new_date, $tz);
      $startDT = date_create_immutable($raw_start, $tz);
      $endDT   = date_create_immutable($raw_end,   $tz);
      if ( ! $newDate || ! $startDT || ! $endDT ) { return; }
      $start_time = $startDT->format('H:i:s');
      $end_time   = $endDT->format('H:i:s');
      $newStart   = DateTimeImmutable::createFromFormat($fmtDT, $newDate->format($fmtD) . ' ' . $start_time, $tz);
      $newEnd     = DateTimeImmutable::createFromFormat($fmtDT, $newDate->format($fmtD) . ' ' . $end_time,   $tz);
      if ( ! $newStart || ! $newEnd ) { return; }
      // Canonical timestamp metas
      update_post_meta( $event_id, 'event_start_time_stamp', $newStart->format($fmtDT) );
      update_post_meta( $event_id, 'event_end_time_stamp',   $newEnd->format($fmtDT) );


      // Keep simple fields in sync for UI


      $linked_product = get_post_meta($event_id, 'linked_product', true);
      if($linked_product) :
        // change the title of the linked product to match the new date
        $product = wc_get_product($linked_product);
        if($product) :
          
          $new_title = get_the_title($parentid) . ' | ' . $newStart->format('D, M j g:i a') . ' - ' . $newEnd->format('g:i a');

          $product->set_name($new_title);
          $product->save();
        endif;
      endif;
    }
  }

  public function movecalendar() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'movecalendar'){
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
      $event = new mindEventCalendar($eventID, $new_date);
      $return = array(
        'new_date' => $new_date,
        'html' => $event->get_calendar(),
      );

      wp_send_json($return);
    }

  }



  public function checkin_toggle() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'checkin_toggle'){
      

      $occurance = $_POST['occurance'];
      $akey = $_POST['akey'];
      $parentid = $_POST['parentid'];

      $attendee_info = get_post_meta($parentid, 'attendees', true);
      if($attendee_info) :
        $attendee_info[$occurance][$akey]['checked_in'] = !$attendee_info[$occurance][$akey]['checked_in'];
        $new_status = $attendee_info[$occurance][$akey]['checked_in'];
        update_post_meta($parentid, 'attendees', $attendee_info);
      endif;

      do_action( MINDEVENTS_PREPEND . 'after_checkin_toggled', $parentid, $occurance, $new_status);
    

      $return = array(
        'success' => true,
        'new_status' => $new_status,
        'html' => ($new_status ? 'Undo Checkin' : 'Checkin'),
      );
      wp_send_json_success($return);
    }

   

  }
  public function add_woo_product_to_cart() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'add_woo_product_to_cart'){
      $product_id = $_POST['product_id'];
      $quantity = $_POST['quantity'];

      $success = false;


      if($product_id && $quantity) :
        $success = WC()->cart->add_to_cart($product_id, $quantity);
        $return['cart_url'] = wc_get_cart_url();
        $return['cart_count'] = WC()->cart->get_cart_contents_count();
      endif; 

      if($success) :
        wp_send_json_success($return);
      else :
        $error_messages = wc_get_notices('error');
        wp_send_json_error($error_messages);
      endif;
    }
  }


  public function editevent() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'editevent'){
      $eventID = $_POST['eventid'];
      $return = array(
        'html' => $this->get_meta_form($eventID, $_POST['parentid'])
      );
      wp_send_json_success($return);
    }
    wp_send_json_error();
  }


  private function get_meta_form($sub_event_id, $parentID) {
    $values = get_post_meta($sub_event_id);
    // Derive date/time from timestamps when available
    $tz = wp_timezone();
    $startTS = isset($values['event_start_time_stamp'][0]) ? $values['event_start_time_stamp'][0] : '';
    $endTS   = isset($values['event_end_time_stamp'][0])   ? $values['event_end_time_stamp'][0]   : '';

    //get wordpress date format
    $dateformat = get_option('date_format');
    $timeformat = get_option('time_format');

    $startDate = new DateTimeImmutable($startTS, $tz);
    $endDate = new DateTimeImmutable($endTS, $tz);

    $html = '<fieldset id="subEventEdit" class="container mindevents-forms event-times">';
      $html .= '<h3>Edit Occurance</h3>';
      $html .= '<div class="time-block">';

        $form_html = '<div class="form-section third">';
          $form_html .= '<p class="label"><label for="event_date">Event Occurrence Date</label></p>';
          $form_html .= '<input type="text" class="required datepicker" name="event_date" id="event_date" value="' . $startDate->format( $dateformat) . '" placeholder="" disabled>';
        $form_html .= '</div>';

        $form_html .= '<div class="form-section third">';
          $form_html .= '<p class="label"><label for="starttime">Event Occurrence Start</label></p>';
          $form_html .= '<input type="text" class="timepicker required" name="starttime" id="starttime" value="' . $startDate->format($timeformat) . '" placeholder="">';
        $form_html .= '</div>';
        $form_html .= '<div class="form-section third">';
          $form_html .= '<p class="label"><label for="endtime">Event Occurrence End</label></p>';
          $form_html .= '<input type="text" class="timepicker" name="endtime" id="endtime" value="' . $endDate->format($timeformat) . '" placeholder="">';
        $form_html .= '</div>';

        $form_html .= '<div class="form-section">';
          $form_html .= '<p class="label"><label for="eventColor">Occurrence Color</label></p>';
          $form_html .= '<input type="text" class="field-color" name="eventColor" id="eventColor" value="' . $values['eventColor'][0] . '" placeholder="">';
        $form_html .= '</div>';

        $form_html .= '<div class="form-section">';
          $form_html .= '<p class="label"><label for="linked_product">Linked Product ID</label></p>';
          $form_html .= '<input type="number" class="linked-product" name="linked_product" id="linked_product" value="' . $values['linked_product'][0] . '" placeholder="">';
          if($values['linked_product'][0]) :
            $form_html .= '<p class="description">Current Product: <a href="' . get_edit_post_link($values['linked_product'][0]) . '" target="_blank">' . get_the_title($values['linked_product'][0]) . '</a></p>';
          endif;
        $form_html .= '</div>';

        
        $form_html .= '<div class="form-section full">';
          $form_html .= '<p class="label"><label for="eventDescription">Short Description</label></p>';
          $form_html .= '<textarea type="text" name="eventDescription" id="eventDescription" placeholder="">' . $values['eventDescription'][0] . '</textarea>';
        $form_html .= '</div>';

        $form_html = apply_filters(MINDEVENTS_PREPEND . 'sub_event_form', $form_html, $values, $sub_event_id, $parentID);
        $html .= $form_html;
        
        
        $html .= '<input type="hidden" name="parentID" value="' . $parentID . '">';
        $html .= '<input type="hidden" name="event_date" value="' . $startDate->format($dateformat) . '">';

        $html .= '<div class="buttonContainer">';
          $html .= '<button
            class="edit-button update-event"
            data-subid="' . $sub_event_id . '"
            >Update Occurance</button>';
        $html .= '</div>';

        $html .= '<div class="buttonContainer">';
          $html .= '<button class="edit-button cancel">Cancel</button>';
        $html .= '</div>';

      $html .= '</div>';

    $html .= '</fieldset>';
    
    return $html;
  }



}



new mindEventsAjax();
