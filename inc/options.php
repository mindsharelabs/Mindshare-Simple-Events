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

      // Add recalculation button if WooCommerce is enabled
      if (isset($options[MINDEVENTS_PREPEND . 'enable_woocommerce'])) {
        add_settings_field(
          MINDEVENTS_PREPEND . 'recalc_order_stats', //setting id
          'Order Statistics', //setting title
          array('mindEventsOptions', MINDEVENTS_PREPEND . 'recalc_button_field'), //display callback
          'mindeventsPlugin', //setting page
          MINDEVENTS_PREPEND . 'api_settings_section', //setting section
          array(
            'message' => 'Recalculate order counts and revenue for all sub events. This updates the custom fields used in Admin Columns.',
            'field' => MINDEVENTS_PREPEND . 'recalc_order_stats'
          ) //args
        );
      }

  }



  static function mindevents_checkbox_field($args) {

    $html = '<input type="checkbox" class="' . $args['class'] . '" id="' . $args['field'] . '" name="mindevents_support_settings[' . $args['field'] . ']" ' . checked( 1, $args['checked'], false ) . '/>';
    $html .= ($args['label'] ? '<label for="checkbox_example">' . $args['label'] . '</label>' : '');

    echo $html;

}

  static function mindevents_recalc_button_field($args) {
    // Handle recalculation if requested
    if (isset($_GET['recalc_stats']) && $_GET['recalc_stats'] === '1' && wp_verify_nonce($_GET['_wpnonce'], 'recalc_order_stats')) {
      echo '<div class="notice notice-success"><p>';
      $results = self::perform_order_stats_recalculation();
      echo '<strong>Recalculation Complete!</strong><br>';
      echo 'Processed: ' . $results['processed'] . ' sub events<br>';
      
      // Show detailed results
      if (!empty($results['details'])) {
        echo '<br><strong>Sample Results:</strong><br>';
        foreach (array_slice($results['details'], 0, 5) as $detail) {
          echo '• ' . esc_html($detail) . '<br>';
        }
        if (count($results['details']) > 5) {
          echo '• ... and ' . (count($results['details']) - 5) . ' more<br>';
        }
      }
      
      if (!empty($results['errors'])) {
        echo '<br><strong>Errors:</strong><br>';
        foreach (array_slice($results['errors'], 0, 3) as $error) {
          echo '• ' . esc_html($error) . '<br>';
        }
      }
      echo '</p></div>';
    }
    
    $sub_events_count = wp_count_posts('sub_event');
    $total = $sub_events_count->publish + $sub_events_count->draft + $sub_events_count->private;
    
    $recalc_url = wp_nonce_url(
      add_query_arg('recalc_stats', '1', admin_url('options-general.php?page=mindevents-settings')),
      'recalc_order_stats'
    );
    
    echo '<p>Sub events to process: <strong>' . $total . '</strong></p>';
    echo '<p><a href="' . $recalc_url . '" class="button button-secondary" onclick="return confirm(\'Are you sure? This will recalculate order statistics for all sub events.\')">Recalculate Order Statistics</a></p>';
    if ($args['message']) {
      echo '<p><small>' . $args['message'] . '</small></p>';
    }
  }

  static function perform_order_stats_recalculation() {
    $details = array();
    
    // Check the working event #100316 - now using correct structure
    $attendees = get_post_meta(100316, 'attendees', true);
    if (is_array($attendees)) {
      $sub_event_keys = array_keys($attendees);
      $details[] = "Event #100316 structure: attendees[sub_event_id] = array";
      $details[] = "Sub event keys found: " . implode(', ', array_slice($sub_event_keys, 0, 5));
    }
    
    // Get all sub events and process them
    $sub_events = get_posts(array(
      'post_type' => 'sub_event',
      'posts_per_page' => -1,
      'post_status' => 'any'
    ));

    $processed = 0;
    $found_data = 0;

    foreach ($sub_events as $sub_event) {
      $sub_event_id = $sub_event->ID;
      
      // Get parent event
      $parent_id = wp_get_post_parent_id($sub_event_id);
      if (!$parent_id) {
        continue;
      }
      
      // Get attendees data from parent event
      $attendees = get_post_meta($parent_id, 'attendees', true);
      
      $related_orders_count = 0;
      $total_revenue = 0;
      
      if (is_array($attendees) && isset($attendees[$sub_event_id])) {
        // Found attendees for this specific sub event
        $attendees_for_sub_event = $attendees[$sub_event_id];
        
        // Count all attendees (total spots purchased)
        $related_orders_count = count($attendees_for_sub_event);
        
        // For revenue and customer list, collect unique order IDs and customer info
        $unique_order_ids = array();
        $customer_orders = array();
        
        foreach ($attendees_for_sub_event as $attendee) {
          if (isset($attendee['order_id']) && !in_array($attendee['order_id'], $unique_order_ids)) {
            $order = wc_get_order($attendee['order_id']);
            if ($order && in_array($order->get_status(), array('completed', 'processing'))) {
              $unique_order_ids[] = $attendee['order_id'];
              
              // Get customer name for orders list
              $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
              if (empty(trim($customer_name))) {
                $customer_name = $order->get_billing_email();
              }
              
              // Create clickable order link with line break
              $order_link = '<a href="' . admin_url('post.php?post=' . $attendee['order_id'] . '&action=edit') . '" target="_blank">Order #' . $attendee['order_id'] . '</a>';
              $customer_orders[] = $order_link . ': ' . $customer_name . '<br>';
            }
          }
        }
        
        // Calculate revenue from unique orders only
        foreach ($unique_order_ids as $order_id) {
          $order = wc_get_order($order_id);
          if ($order) {
            $total_revenue += floatval($order->get_total());
          }
        }
        
        // Store customer orders as array (not string) for Admin Columns multiple type
        $customer_orders_list = $customer_orders;
      }
      
      // Update meta fields
      update_post_meta($sub_event_id, 'related_orders_count', $related_orders_count);
      update_post_meta($sub_event_id, 'total_revenue', $total_revenue);
      update_post_meta($sub_event_id, 'customer_orders_list', isset($customer_orders_list) ? $customer_orders_list : array());
      
      if ($related_orders_count > 0) {
        $found_data++;
        $details[] = "✓ Sub Event #{$sub_event_id}: {$related_orders_count} orders, $" . number_format($total_revenue, 2);
      }
      
      $processed++;
    }
    
    $details[] = "SUCCESS: {$found_data} of {$processed} sub events now have order data!";

    return array(
      'processed' => $processed,
      'errors' => array(),
      'details' => $details
    );
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
