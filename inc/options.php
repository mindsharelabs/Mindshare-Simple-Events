<?php


class mindEventsOptions {
  public function __construct() {
    add_action( 'admin_menu', array($this,'mindevents_support_settings_page' ));
    add_action( 'admin_init', array($this,'mindevents_api_settings_init' ));
	}


  static function mindevents_support_settings_page() {
    
      add_options_page(
        'Mindshare Events Plugin Settings',
        'Mindshare Events Plugin Settings',
        'manage_options', //permisions
        'mindevents-settings', //page slug
        array($this, 'mindevents_support_settings') //callback for display
      );
  }


  static function mindevents_api_settings_init(  ) {
      register_setting( 'mindeventsPlugin', 'mindevents_support_settings' );
      $options = get_option( 'mindevents_support_settings' );

      add_settings_section(
        'mindevents_api_settings_section', //section id
        'WooCommerce Returns Options', //section title
        array($this, 'mindevents_support_settings_section_callback'), //display callback
        'mindeventsPlugin' //settings page
      );


      add_settings_field(
        'mindevents_api_token', //setting id
        'API Token', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => '',
          'field' => 'mindevents_api_token',
          'value' => (isset($options['mindevents_api_token']) ? $options['mindevents_api_token'] : false)
        ) //args
      );

  }


  static function mindevents_setting_field($args) {
    echo '<input type="password" id="' . $args['field'] . '" name="mindevents_support_settings[' . $args['field'] . ']" value="' . $args['value'] . '">';
    if($args['message']) {
      echo '<br><small>' . $args['message'] . '</small>';
    }
  }


  static function mindevents_support_settings_section_callback() {
    echo '';
  }


  static function mindevents_support_settings() {
    echo '<div class="mindeventsPage">';
    echo '<form action="options.php" method="post">';
        settings_fields( 'mindeventsPlugin' );
        do_settings_sections( 'mindeventsPlugin' );
        submit_button();
    echo '</form>';
    echo '</div>';

  }
}
new mindEventsOptions();
