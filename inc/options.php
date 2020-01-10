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
          'value' => (isset($options['mindevents_api_token']) ? $options['mindevents_api_token'] : false),
          'type' => 'password',
          'class' => ''
        ) //args
      );

      add_settings_field(
        'mindevents_start_day', //setting id
        'Week Start Day', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => 'Day the week starts on. ex: "Monday" or 0-6 where 0 is Sunday',
          'field' => 'mindevents_start_day',
          'value' => (isset($options['mindevents_start_day']) ? $options['mindevents_start_day'] : 'Monday'),
          'type' => 'text',
          'class' => ''
        ) //args
      );

      add_settings_field(
        'mindevents_start_time', //setting id
        'Default Start Time', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => 'Default start time for event occurances.',
          'field' => 'mindevents_start_time',
          'value' => (isset($options['mindevents_start_time']) ? $options['mindevents_start_time'] : '7:00 PM'),
          'type' => 'text',
          'class' => 'timepicker'
        ) //args
      );

      add_settings_field(
        'mindevents_end_time', //setting id
        'Default End Time', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => 'Default end time for event occurances.',
          'field' => 'mindevents_end_time',
          'value' => (isset($options['mindevents_end_time']) ? $options['mindevents_end_time'] : '10:00 PM'),
          'type' => 'text',
          'class' => 'timepicker'
        ) //args
      );

      add_settings_field(
        'mindevents_event_cost', //setting id
        'Default Event Cost', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => 'Default cost for event occurances. (Do not include currency symbol)',
          'field' => 'mindevents_event_cost',
          'value' => (isset($options['mindevents_event_cost']) ? $options['mindevents_event_cost'] : ''),
          'type' => 'text',
          'class' => ''
        ) //args
      );

      add_settings_field(
        'mindevents_currency_symbol', //setting id
        'Currency Symbol', //setting title
        array($this, 'mindevents_setting_field'), //display callback
        'mindeventsPlugin', //setting page
        'mindevents_api_settings_section', //setting section
        array(
          'message' => 'Default currency symbol.',
          'field' => 'mindevents_currency_symbol',
          'value' => (isset($options['mindevents_currency_symbol']) ? $options['mindevents_currency_symbol'] : '$'),
          'type' => 'text',
          'class' => ''
        ) //args
      );


  }


  static function mindevents_setting_field($args) {
    echo '<input type="' . $args['type'] . '" class="' . $args['class'] . '" id="' . $args['field'] . '" name="mindevents_support_settings[' . $args['field'] . ']" value="' . $args['value'] . '">';
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
