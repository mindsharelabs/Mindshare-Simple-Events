<?php


class mindEventsOptions {
  public function __construct() {
    add_action( 'admin_menu', array($this,MINDEVENTS_PREPEND . 'support_settings_page' ));
    add_action( 'admin_init', array($this,MINDEVENTS_PREPEND . 'api_settings_init' ));
	}


  static function mindevents_support_settings_page() {

      add_options_page(
        'Mindshare Events Plugin Settings',
        'Mindshare Events Plugin Settings',
        'manage_options', //permisions
        'mindevents-settings', //page slug
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'support_settings') //callback for display
      );
  }


  static function mindevents_api_settings_init(  ) {
      register_setting( 'mindeventsPlugin', MINDEVENTS_PREPEND . 'support_settings' );
      $options = get_option( MINDEVENTS_PREPEND . 'support_settings' );
      add_settings_section(
        MINDEVENTS_PREPEND . 'api_settings_section', //section id
        'Mindshare Simple Events Options', //section title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'support_settings_section_callback'), //display callback
        'mindeventsPlugin' //settings page
      );


      add_settings_field(
        MINDEVENTS_PREPEND . 'api_token', //setting id
        'API Token', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => '',
          'field' => MINDEVENTS_PREPEND . 'api_token',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'api_token']) ? $options[MINDEVENTS_PREPEND . 'api_token'] : false),
          'type' => 'password',
          'class' => ''
        ) //args
      );

      add_settings_field(
        MINDEVENTS_PREPEND . 'start_day', //setting id
        'Week Start Day', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Day the week starts on. ex: "Monday" or 0-6 where 0 is Sunday',
          'field' => MINDEVENTS_PREPEND . 'start_day',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'start_day']) ? $options[MINDEVENTS_PREPEND . 'start_day'] : 'Monday'),
          'type' => 'text',
          'class' => ''
        ) //args
      );

      add_settings_field(
        MINDEVENTS_PREPEND . 'start_time', //setting id
        'Default Start Time', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Default start time for event occurances.',
          'field' => MINDEVENTS_PREPEND . 'start_time',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'start_time']) ? $options[MINDEVENTS_PREPEND . 'start_time'] : '7:00 PM'),
          'type' => 'text',
          'class' => 'timepicker'
        ) //args
      );

      add_settings_field(
        MINDEVENTS_PREPEND . 'end_time', //setting id
        'Default End Time', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Default end time for event occurances.',
          'field' => MINDEVENTS_PREPEND . 'end_time',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'end_time']) ? $options[MINDEVENTS_PREPEND . 'end_time'] : '10:00 PM'),
          'type' => 'text',
          'class' => 'timepicker'
        ) //args
      );

      add_settings_field(
        MINDEVENTS_PREPEND . 'event_cost', //setting id
        'Default Event Cost', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Default cost for event occurances. (Do not include currency symbol)',
          'field' => MINDEVENTS_PREPEND . 'event_cost',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'event_cost']) ? $options[MINDEVENTS_PREPEND . 'event_cost'] : ''),
          'type' => 'text',
          'class' => ''
        ) //args
      );

      add_settings_field(
        MINDEVENTS_PREPEND . 'currency_symbol', //setting id
        'Currency Symbol', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'setting_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Default currency symbol.',
          'field' => MINDEVENTS_PREPEND . 'currency_symbol',
          'value' => (isset($options[MINDEVENTS_PREPEND . 'currency_symbol']) ? $options[MINDEVENTS_PREPEND . 'currency_symbol'] : '$'),
          'type' => 'text',
          'class' => ''
        ) //args
      );


      add_settings_field(
        MINDEVENTS_PREPEND . 'enable_woocommerce', //setting id
        'Enable WooCommerce Integration', //setting title
        array('mindEventsOptions', MINDEVENTS_PREPEND . 'checkbox_field'), //display callback
        'mindeventsPlugin', //setting page
        MINDEVENTS_PREPEND . 'api_settings_section', //setting section
        array(
          'message' => 'Enable WooCommerce Integration.',
          'label' => 'Check here to enable WooCommerce integration.',
          'field' => MINDEVENTS_PREPEND . 'enable_woocommerce',
          //'value' => (isset($options[MINDEVENTS_PREPEND . 'enable_woocommerce']) ? $options[MINDEVENTS_PREPEND . 'enable_woocommerce'] : false),
          'checked' => (isset($options[MINDEVENTS_PREPEND . 'enable_woocommerce']) ? true : false),
          'type' => 'checkbox',
          'class' => ''
        ) //args
      );


  }



  static function mindevents_checkbox_field($args) {

    $html = '<input type="checkbox" class="' . $args['class'] . '" id="' . $args['field'] . '" name="mindevents_support_settings[' . $args['field'] . ']" ' . checked( 1, $args['checked'], false ) . '/>';
    $html .= ($args['label'] ? '<label for="checkbox_example">' . $args['label'] . '</label>' : '');

    echo $html;

}

  static function mindevents_setting_field($args) {
    echo '<input type="' . $args['type'] . '" class="' . $args['class'] . '" id="' . $args['field'] . '" name="mindevents_support_settings[' . $args['field'] . ']" value="' . $args['value'] . '">';
    if($args['message']) {
      echo '<br><small>' . $args['message'] . '</small>';
    }
  }


  static function mindevents_support_settings_section_callback($section) {
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
