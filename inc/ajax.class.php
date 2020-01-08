<?php


class mindEventsAjax {
  private $options = '';
  private $token = '';

  function __construct() {

    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

    // add_action( 'wp_ajax_nopriv_mindevents_generate_label', array( $this, 'accept_review' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'selectday', array( $this, 'selectday' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'clearevents', array( $this, 'clearevents' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'deleteevent', array( $this, 'deleteevent' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'editevent', array( $this, 'editevent' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'updatesubevent', array( $this, 'updatesubevent' ) );

    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'movecalendar', array( $this, 'movecalendar' ) );


    add_action( 'wp_ajax_nopriv_' . MINDRETURNS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'move_pub_calendar', array( $this, 'move_pub_calendar' ) );

    add_action( 'wp_ajax_nopriv_' . MINDRETURNS_PREPEND . 'move_archive_calendar', array( $this, 'move_archive_calendar' ) );
    add_action( 'wp_ajax_' . MINDRETURNS_PREPEND . 'move_archive_calendar', array( $this, 'move_archive_calendar' ) );


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
      $event = new mindEventCalendar();
      $html = $event->get_cal_meta_html($id);

      $return = array(
        'html' => $html
      );
      wp_send_json_success($return);
    }
  }




  static function deleteevent() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'deleteevent'){
      $eventID = $_POST['eventid'];
      wp_delete_post($eventID);
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



  static function selectday() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'selectday'){

      $date = $_POST['date'];
      $eventID = $_POST['eventid'];

      $meta = $this->reArrayMeta($_POST['meta']['event']);

      $event = new mindEventCalendar($eventID);

      $added_events = array();
      $errors = array();
      $html = '';

      $added_event_id = $event->add_sub_event('', $date, $meta, $eventID);

      if($added_event_id == false) :
        $errors[] = 'An event at that time already exists';
      elseif(is_wp_error($added_event_id)) :
        $errors[] = $added_event_id->get_error_message();
      else :
        $added_events[] = $added_event_id;

        $insideHTML = '<div class="event ' . (MINDRETURNS_IS_MOBILE ? 'mobile' : '') . '">';
          $insideHTML .= '<span style="background:' . $meta['eventColor'] . '; color:' . $this->getContrastColor($meta['eventColor']) . ';" data-subid = ' . $added_event_id . ' class="new">';
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

  static function clearevents() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'clearevents'){
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
      $event = new mindEventCalendar($eventID, $new_date);
      $return = array(
        'new_date' => $new_date,
        'html' => $event->get_front_calendar($eventID),
      );

      wp_send_json($return);
    }

  }

  private function reArrayMeta($metaStart) {
    $meta = array();
    $meta['event_date'] = $metaStart['event_date'];
    $meta['starttime'] = $metaStart['starttime'];
    $meta['endtime'] = $metaStart['endtime'];
    $meta['eventColor'] = $metaStart['eventColor'];
    $meta['eventDescription'] = $metaStart['eventDescription'];

    $meta['offers'] = array();

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
    else :
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


  static function updatesubevent() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'updatesubevent'){

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
      $event = new mindEventCalendar($eventID, $new_date);
      $return = array(
        'new_date' => $new_date,
        'html' => $event->get_calendar(),
      );

      wp_send_json($return);
    }

  }





  static function editevent() {
    if($_POST['action'] == MINDRETURNS_PREPEND . 'editevent'){
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
    $offers = unserialize($values['offers'][0]);
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

        $html .= '<div class="form-section full">';
          $html .= '<p class="label"><label for="eventDescription">Short Description</label></p>';
          $html .= '<textarea type="text" name="eventDescription" id="eventDescription" placeholder="">' . $values['eventDescription'][0] . '</textarea>';
        $html .= '</div>';


        if($offers) :
          foreach ($offers as $key => $offer) :
            $html .= '<div class="offer-options" id="editOffers_' . $key .'">';

              $html .='<div class="single-offer">';
                $html .='<div class="form-section">';
                  $html .='<p class="label"><label for="eventLinkLabel_' . $key .'">Ticket Label</label></p>';
                  $html .='<input type="text" name="event[offerlabel][]" id="eventLinkLabel_' . $key .'" value="' . (isset($offer['label']) ? $offer['label'] : 'General Admission') . '" placeholder="">';
                $html .='</div>';

                $html .='<div class="form-section">';
                  $html .='<p class="label"><label for="eventCost_' . $key .'">Price</label></p>';
                  $html .='<input type="text" name="event[offerprice][]" id="eventCost_' . $key .'" value="' . (isset($offer['price']) ? $offer['price'] : '') . '" placeholder="">';
                $html .='</div>';

                $html .='<div class="form-section">';
                  $html .='<p class="label"><label for="eventLink_' . $key .'">Link</label></p>';
                  $html .='<input type="text" name="event[offerlink][]" id="eventLink_' . $key .'" value="' . (isset($offer['link']) ? $offer['link'] : '') . '" placeholder="">';
                $html .='</div>';


                if($key == 0) :
                  $html .='<div class="add-offer-edit" data-key="' . $key . '">';
                    $html .='<span>+</span>';
                  $html .='</div>';
                else :
                  $html .='<div class="remove-offer" data-key="' . $key . '">';
                    $html .='<span>-</span>';
                  $html .='</div>';
                endif;

              $html .='</div>';


            $html .= '</div>';




          endforeach;
        endif;

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
