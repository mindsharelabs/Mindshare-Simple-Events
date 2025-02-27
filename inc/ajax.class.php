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
      $eventID = $_POST['eventid'];


      $meta = $this->reArrayMeta($_POST['meta']['event']);
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

        $insideHTML = '<div class="event ' . (MINDEVENTS_IS_MOBILE ? 'mobile' : '') . '">';
          $insideHTML .= '<span style="background:' . $meta['eventColor'] . '; color:' . $this->getContrastColor($meta['eventColor']) . ';" data-subid = ' . $added_event_id . ' class="new edit">';
            $insideHTML .= $meta['starttime'] . '-' . $meta['endtime'];
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
      $return = $event->delete_sub_events();
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

  private function reArrayMeta($metaStart) {
    $meta = array();
    $meta['event_date'] = (isset($metaStart['event_date']) ? $metaStart['event_date'] : '');
    $meta['starttime'] = $metaStart['starttime'];
    $meta['endtime'] = $metaStart['endtime'];
    $meta['eventColor'] = $metaStart['eventColor'];
    $meta['eventDescription'] = $metaStart['eventDescription'];

    $meta['offers'] = array();

    $meta = array_merge($meta, $metaStart);
    if(isset($metaStart['event'])) :
      foreach ($metaStart['event']['offerlabel'] as $key => $label) :
        $meta['offers'][$key]['label'] = $label;
      endforeach;

      foreach ($metaStart['event']['offerprice'] as $key => $price) :
        $meta['offers'][$key]['price'] = $price;
      endforeach;

      foreach ($metaStart['event']['offerlink'] as $key => $link) :
        $meta['offers'][$key]['link'] = $link;
      endforeach;
    elseif(isset($metaStart['offerlabel'])) :
      foreach ($metaStart['offerlabel'] as $key => $label) :
        $meta['offers'][$key]['label'] = $label;
      endforeach;

      foreach ($metaStart['offerprice'] as $key => $price) :
        $meta['offers'][$key]['price'] = $price;
      endforeach;

      foreach ($metaStart['offerlink'] as $key => $link) :
        $meta['offers'][$key]['link'] = $link;
      endforeach;
    endif;

    return $meta;
  }


  public function updatesubevent() {
    if($_POST['action'] == MINDEVENTS_PREPEND . 'updatesubevent'){

      $id = $_POST['eventid'];
      $event = new mindEventCalendar($_POST['parentid'], $_POST['meta']['event_date']);
      $meta = $this->reArrayMeta($_POST['meta']);
      $event->update_sub_event($id, $meta, $_POST['parentid']);


      $return = array(
        'html' => $event->get_calendar()
      );
      wp_send_json_success($return);
    }
    wp_send_json_error();
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
      $event_date = $_POST['event_date'];

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
    $html = '<fieldset id="subEventEdit" class="container mindevents-forms event-times">';
      $html .= '<h3>Edit Occurance</h3>';
      $html .= '<div class="time-block">';

        $html .= '<div class="form-section third">';
          $html .= '<p class="label"><label for="event_date">Event Occurrence Date</label></p>';
          $html .= '<input type="text" class="required datepicker" name="event_date" id="event_date" value="' . $values['event_date'][0] . '" placeholder="">';
        $html .= '</div>';

        $html .= '<div class="form-section third">';
          $html .= '<p class="label"><label for="starttime">Event Occurrence Start</label></p>';
          $html .= '<input type="text" class="timepicker required" name="starttime" id="starttime" value="' . $values['starttime'][0] . '" placeholder="">';
        $html .= '</div>';
        $html .= '<div class="form-section third">';
          $html .= '<p class="label"><label for="endtime">Event Occurrence End</label></p>';
          $html .= '<input type="text" class="timepicker" name="endtime" id="endtime" value="' . $values['endtime'][0] . '" placeholder="">';
        $html .= '</div>';

        $html .= '<div class="form-section">';
          $html .= '<p class="label"><label for="eventColor">Occurrence Color</label></p>';
          $html .= '<input type="text" class="field-color" name="eventColor" id="eventColor" value="' . $values['eventColor'][0] . '" placeholder="">';
        $html .= '</div>';

        $html .= '<div class="form-section">';
          $html .= '<p class="label"><label for="linked_product">Linked Product ID</label></p>';
          $html .= '<input type="number" class="linked-product" name="linked_product" id="linked_product" value="' . $values['linked_product'][0] . '" placeholder="">';
        $html .= '</div>';

       




      //this is a temporary code block for use during the calendar transition


        $start_date = new DateTimeImmutable($values['event_date'][0]);
        // $start_date->modify('-1 day');
        $end_date = $start_date->modify('+1 day');
    
        $products = get_posts(array(
          'post_type' => 'product',
          'posts_per_page' => -1,
          'post_status' => array('publish'),
          'meta_query' => array(
            array(
              'key' => '_tribe_wooticket_for_event',
              'compare' => 'EXISTS'
            ),
            array(
              'key' => '_EventStartDate',
              'value' => array($start_date->format('Y-m-d'), $end_date->format('Y-m-d')),
              'compare' => 'BETWEEN',
              'type' => 'DATE'
            )
          )
        ));
    
        //output a list of product ids, titles and dates
        if($products) :
          
            $html .= '<ul>';
            foreach ($products as $key => $product) :
              $event = get_post_meta($product->ID, '_tribe_wooticket_for_event', true);
              if(!$event) :
                continue;
              endif;
    
              $html .= '<li class="small">' . $product->ID . ' - ' . $product->post_title . ' - ' . get_post_meta($event, '_EventStartDate', true) . '</li>';
            endforeach;
            $html .= '</ul>';
          
        endif;















        $html .= '<div class="form-section full">';
          $html .= '<p class="label"><label for="eventDescription">Short Description</label></p>';
          $html .= '<textarea type="text" name="eventDescription" id="eventDescription" placeholder="">' . $values['eventDescription'][0] . '</textarea>';
        $html .= '</div>';



        $html .= '<input type="hidden" name="parentID" value="' . $parentID . '">';
        $html .= '<input type="hidden" name="event_date" value="' . $values['event_date'][0] . '">';

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

    $html .= '</fieldset';

    return $html;
  }



}



new mindEventsAjax();
