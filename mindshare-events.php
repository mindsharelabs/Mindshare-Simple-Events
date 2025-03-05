<?php
/**
 * Plugin Name: Mindshare Simple Events
 * Plugin URI:https://mind.sh/are
 * Description: A simple events plugin, for sites that just need the basics.
 * Version: 1.1.4
 * Author: Mindshare Labs, Inc
 * Author URI: https://mind.sh/are
 */


class mindEvents {
  private $options = '';

  private $token = '';

  protected static $instance = NULL;

  public function __construct() {
    if ( !defined( 'MINDEVENTS_PLUGIN_FILE' ) ) {
    	define( 'MINDEVENTS_PLUGIN_FILE', __FILE__ );
    }
    //Define all the constants
    $this->define( 'MINDEVENTS_ABSPATH', dirname( MINDEVENTS_PLUGIN_FILE ) . '/' );
    $this->define( 'MINDEVENTS_PLUGIN_VERSION', '0.2.4');
    $this->define( 'MINDEVENTS_PREPEND', 'mindevents_' );


    $this->options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
    $this->token = (isset($this->options[MINDEVENTS_PREPEND . 'api_token']) ? $this->options[MINDEVENTS_PREPEND . 'api_token'] : false);


    $this->includes();

    $mobile = new Mobile_Detect();

    add_action( 'admin_enqueue_scripts', array($this, 'enque_scripts_and_styles'), 100 );
    add_action( 'wp_enqueue_scripts', array($this, 'enque_front_scripts_and_styles'), 100 );

    add_action ('save_post_events', array($this, 'add_post_meta'), 1, 2);


    add_action ('save_post_events', array($this, 'sync_sub_event_tax'), 100, 2);

    // do_action( 'delete_post', array($this, 'delete_sub_events'), 100, 2);
    add_action( 'transition_post_status', array($this, 'transition_sub_events'), 100, 3 );


    add_action ('wp_head', array($this, 'generate_schema'));

    $this->define( 'MINDEVENTS_IS_MOBILE', $mobile->isMobile() );
    
	}
  public static function get_instance() {
    if ( null === self::$instance ) {
  		self::$instance = new self;
  	}
  	return self::$instance;
  }
  private function define( $name, $value ) {
    if ( ! defined( $name ) ) {
      define( $name, $value );
    }
  }
  private function includes() {
    //General
    include MINDEVENTS_ABSPATH . 'inc/utilities.php';
    include MINDEVENTS_ABSPATH . 'inc/mobile-detect.php';
    include MINDEVENTS_ABSPATH . 'inc/events.class.php';
    include MINDEVENTS_ABSPATH . 'inc/options.php';
    include MINDEVENTS_ABSPATH . 'inc/admin.class.php';
    include MINDEVENTS_ABSPATH . 'inc/posttypes.php';
    include MINDEVENTS_ABSPATH . 'inc/ajax.class.php';
    include MINDEVENTS_ABSPATH . 'inc/front-end.php';
    

    if($this->options[MINDEVENTS_PREPEND . 'enable_woocommerce']) :

      


      include MINDEVENTS_ABSPATH . 'inc/woocommerce.php';
    endif;


    add_filter( 'automatewoo/triggers',function ( $triggers ) {

      include MINDEVENTS_ABSPATH . 'inc/automate-woo-trigger.php';
      // set a unique name for the trigger and then the class name
      $triggers['mind_events_trigger'] = 'Class_Reminder_Trigger';
    
      return $triggers;
    } );
    

  }

  public function enque_front_scripts_and_styles() {
    wp_register_style('mindevents-css', plugins_url('css/style.css', MINDEVENTS_PLUGIN_FILE), array(), MINDEVENTS_PLUGIN_VERSION, 'all');
		wp_enqueue_style('mindevents-css');

    wp_register_script('mindevents-js', plugins_url('js/mindevents.js', MINDEVENTS_PLUGIN_FILE), array('jquery'), MINDEVENTS_PLUGIN_VERSION, true);
		wp_enqueue_script('mindevents-js');

    if(is_post_type_archive('events')) :
      $postID = 'archive';
    else :
      $postID = get_the_ID();
    endif;

    $data = array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'post_id' => $postID,
    );
    if($this->options[MINDEVENTS_PREPEND . 'enable_woocommerce']) :
      $data['cart_url'] = wc_get_cart_url();
    endif;
    wp_localize_script( 'mindevents-js', 'mindeventsSettings', $data);
  }


  public function enque_scripts_and_styles() {

    wp_register_style('mindevents-css', plugins_url('css/style.css', MINDEVENTS_PLUGIN_FILE), array(), MINDEVENTS_PLUGIN_VERSION, 'all');
		wp_enqueue_style('mindevents-css');

    wp_register_style('timepicker-js', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css', array(), '1.3.5', 'all');
    wp_enqueue_style('timepicker-js');

    wp_register_script('timepicker-js', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array('jquery'), '1.3.5', true);
		wp_enqueue_script('timepicker-js');

    wp_register_script('fontawesome-js', 'https://kit.fontawesome.com/bed26df994.js', array(), MINDEVENTS_PLUGIN_VERSION, true);
		wp_enqueue_script('fontawesome-js');

    wp_register_script('mindevents-js', plugins_url('js/admin.js', MINDEVENTS_PLUGIN_FILE), array('jquery', 'timepicker-js'), MINDEVENTS_PLUGIN_VERSION, true);
		wp_enqueue_script('mindevents-js');
    wp_localize_script( 'mindevents-js', 'mindeventsSettings', array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'post_id' => get_the_id()
    ) );

	}




  static function transition_sub_events($new_status, $old_status, $parentOBJ) {
    if($parentOBJ->post_type == 'events') :
      $sub_events = get_posts(array(
        'post_parent' => $parentOBJ->ID,
        'post_type' => 'sub_event',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash')
      ));
      if($sub_events) :
        foreach ($sub_events as $key => $post) :
          wp_update_post(array(
            'ID' =>  $post->ID,
            'post_status' => $new_status
          ));
        endforeach;
      endif;
    endif;

    //TODO: transition product status as well
  }

  static function sync_sub_event_tax($id, $object) {
    if($object->post_type == 'events') :
      $terms = wp_get_post_terms( $id, 'event_category',  array('fields' => 'ids'));
      $sub_events = get_posts(array(
        'post_parent' => $object->ID,
        'post_type' => 'sub_event',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash')
      ));
      if($sub_events) :
        foreach ($sub_events as $key => $post) :
          wp_set_post_terms( $post->ID, $terms, 'event_category');
        endforeach;
      endif;
    endif;
  }
  static function add_post_meta($id, $object) {
    if($object->post_type == 'events') :
      if(isset($_POST['defaults'])) :
        $metas = array_map( 'sanitize_text_field', wp_unslash( $_POST['defaults'] ) );
        update_post_meta($id, 'defaults', $metas);
        foreach ($metas as $key => $value) {
          update_post_meta($id, $key, $value);
        }
      endif;



      $first_event = get_posts(array(
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'post_parent' => $id,
        'order' => 'ASC',
        'post_type' => 'sub_event',
        'posts_per_page' => 1
      ));

      if($first_event) :
        $first_event = $first_event[0];
        update_post_meta($id, 'first_event_date', get_post_meta($first_event->ID, 'event_start_time_stamp', true));
      endif;

      $last_event = get_posts(array(
        'orderby' => 'meta_value',
        'meta_key' => 'event_start_time_stamp',
        'meta_type' => 'DATETIME',
        'post_parent' => $id,
        'order' => 'DESC',
        'post_type' => 'sub_event',
        'posts_per_page' => 1
      ));
      if($last_event):
        $last_event = $last_event[0];
        update_post_meta($id, 'last_event_date', get_post_meta($last_event->ID, 'event_start_time_stamp', true));
      endif;
    endif;
  }


  public function generate_schema() {
    if(is_singular('events')) :
      $event = new mindEventCalendar(get_the_ID());
      echo '<script type="application/ld+json">';
        echo $event->generate_schema();
      echo '</script>';
    endif;
  }


}//end of class

new mindEvents();







//temportary program to build meta information for products



add_action('init', function() {


//   $products = get_posts(array(
//     'post_type' => 'product',
//     'posts_per_page' => -1,
//     'post_status' => array('publish'),
//     'meta_query' => array(
//       array(
//         'key' => '_tribe_wooticket_for_event',
//         'compare' => 'EXISTS'
//       ),
//     )
//   ));
//   $i = 0;
//   foreach ($products as $key => $product) :
//     $event = get_post_meta($product->ID, '_tribe_wooticket_for_event', true);
//     $event = get_post($event);
//     if(!$event) :
//       continue;
//     endif;

//     $i++;
//     //get event date
//     $_EventStartDate = get_post_meta($event->ID, '_EventStartDate', true);
//     mapi_write_log('Event Start Date: ' . $_EventStartDate);
//     mapi_write_log('Count: ' . $i);
//     //save event date to meta on product
//     update_post_meta($product->ID, '_EventStartDate', $_EventStartDate);
//   endforeach;
});
