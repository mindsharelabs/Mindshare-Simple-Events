<?php


class mindEvent {

  private $eventID = '';
  private $wp_post = '';

  function __construct($id) {
    $this->eventID = $id;
    $this->wp_post = get_post($id);
  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }


  public function add_sub_event($args = array(), $date, $time) {

    $unique = $this->build_unique_key($date, $time);
    $return = array();
    $args = array(
     'fields' => 'ids',
     'post_type'   => 'sub_event',
     'meta_query'  => array(
       array(
         'key' => 'unique_event_key',
         'value' => $unique
       )
     )
    );
    $my_query = new WP_Query( $args );
    if( empty($my_query->have_posts()) ) :
      $defaults = array(
         'post_author'           => get_current_user_id(),
         'post_content'          => '',
         'post_title'            => $this->build_title($date, $time),
         'post_excerpt'          => '',
         'post_status'           => 'publish',
         'post_type'             => 'sub_event',
         'post_parent'           => $this->eventID,
         'context'               => '',
         'meta_input' => array(
           'event_date' => $date,
           'event_start' => $time['starttime'],
           'event_end' => $time['endtime'],
           'event_times' => $time,
           'unique_event_key' => $unique
          )
        );
      $args = wp_parse_args($args, $defaults);
      $return = wp_insert_post($args);
    else :
      $return = false;
    endif;
    return $return;
  }


  private function build_unique_key($date = '', $times = '') {
    return sanitize_title($date . '_' . $times['starttime'] . '-' . $times['endtime']);
  }

  private function build_title($date = '', $times = '') {
    $title = get_the_title($this->eventID) . ' | ' . $date . ' | ' . $times['starttime'] . '-' . $times['endtime'];
    return apply_filters('mind_events_title', $title, $date, $times, $this);
  }


  public function delete_sub_events() {
    $sub_events = $this->get_sub_events();
    if (is_array($sub_events) && count($sub_events) > 0) {
      foreach($sub_events as $event){
        $return = wp_delete_post($event->ID);
      }
      return true;
    }
    return false;
  }
  //
  // public function add_date_to_sub_event($date, $times) {
  //   update_post_meta($this->eventID, 'event_date', $date);
  //   update_post_meta($this->eventID, 'event_start', $times['starttime']);
  //   update_post_meta($this->eventID, 'event_end', $times['endtime']);
  //   update_post_meta($this->eventID, 'event_times', $times);
  // }

  public function get_sub_events($args = array()) {
    $defaults = array(
      'meta_query' => array(
         // 'relation' => 'AND',
         'start_clause' => array(
             'key' => 'event_start',
             'compare' => 'EXISTS',
         ),
         'date_clause' => array(
             'key' => 'event_date',
             'compare' => 'EXISTS',
         ),
      ),
      'orderby' => array(
        'start_clause' => 'ASC',
        'date_clause' => 'DESC',
      ),

      // 'meta_value'       => '',
      'order'            => 'DESC',
      'post_type'        => 'sub_event',
      'post_parent'      => $this->eventID,
      'suppress_filters' => true,
      'posts_per_page' => -1
    );
    $args = wp_parse_args($args, $defaults);
    return get_posts($args);

  }



}
