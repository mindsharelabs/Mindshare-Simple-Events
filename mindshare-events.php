<?php
/**
 * Plugin Name: Mindshare Simple Events
 * Plugin URI:https://mind.sh/are
 * Description: A simple events plugin, for sites that just need the basics.
 * Version: 0.0.1
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
    $this->define( 'MINDEVENTS_PLUGIN_VERSION', '1.0.0');

    add_action( 'admin_enqueue_scripts', array($this, 'enque_scripts_and_styles'), 100 );

    $this->includes();

    $this->options = get_option( 'mindevents_support_settings' );
    $this->token = (isset($this->options['mindevents_api_token']) ? $this->options['mindevents_api_token'] : false);

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
    include_once MINDEVENTS_ABSPATH . 'inc/options.php';
    include_once MINDEVENTS_ABSPATH . 'inc/calendar.class.php';
    include_once MINDEVENTS_ABSPATH . 'inc/admin.class.php';
    include_once MINDEVENTS_ABSPATH . 'inc/posttypes.php';
    include_once MINDEVENTS_ABSPATH . 'inc/ajax.class.php';
    include_once MINDEVENTS_ABSPATH . 'inc/events.class.php';
  }

  public function enque_scripts_and_styles() {

		wp_register_script('mindevents-js', plugins_url('js/admin.js', MINDEVENTS_PLUGIN_FILE), array('jquery'), MINDEVENTS_PLUGIN_VERSION, true);
		wp_enqueue_script('mindevents-js');
    wp_localize_script( 'mindevents-js', 'mindeventsSettings', array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'post_id' => get_the_id()
    ) );


    wp_register_script('timepicker-js', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array('jquery'), '1.3.5', true);
		wp_enqueue_script('timepicker-js');


		wp_register_style('mindevents-css', plugins_url('style.css', MINDEVENTS_PLUGIN_FILE), array(), MINDEVENTS_PLUGIN_VERSION, 'all');
		wp_enqueue_style('mindevents-css');

    wp_register_style('timepicker-css', 'https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css', array(), '1.3.5', 'all');
		wp_enqueue_style('timepicker-css');


	}


}//end of class


new mindEvents();
