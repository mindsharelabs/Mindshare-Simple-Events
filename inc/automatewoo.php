<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MINDEVENTS_ABSPATH . 'inc/automatewoo/class-manager.php';
require_once MINDEVENTS_ABSPATH . 'inc/automatewoo/class-event-data.php';

Mindshare_AutomateWoo_Manager::init();

/**
 * Register the custom AutomateWoo data item.
 *
 * @param array $data_items Existing data items.
 * @return array
 */
function mindshare_automatewoo_register_data_item( $data_items ) {
	if ( class_exists( '\AutomateWoo\Data_Item' ) ) {
		$data_items['mindshare_event'] = MINDEVENTS_ABSPATH . 'inc/automatewoo/data-item-event-loader.php';
	}

	return $data_items;
}
add_filter( 'automatewoo/data_items', 'mindshare_automatewoo_register_data_item' );

/**
 * Register custom AutomateWoo variables.
 *
 * @param array $variables Existing variables.
 * @return array
 */
function mindshare_automatewoo_register_variables( $variables ) {
	if ( ! class_exists( '\AutomateWoo\Variable' ) ) {
		return $variables;
	}

	$variables['order_item']['mindshare_event_start'] = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-order-item-event-start.php';
	$variables['order_item']['mindshare_event_end']   = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-order-item-event-end.php';
	$variables['order']['mindshare_event_start']      = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-order-event-start.php';
	$variables['order']['mindshare_event_end']        = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-order-event-end.php';
	$variables['mindshare_event']['field']            = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-event-field.php';
	$variables['mindshare_event']['attendees_table']  = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-event-attendees-table.php';
	$variables['mindshare_event']['attendees_json']   = MINDEVENTS_ABSPATH . 'inc/automatewoo/variable-event-attendees-json.php';

	return $variables;
}
add_filter( 'automatewoo/variables', 'mindshare_automatewoo_register_variables' );

/**
 * Register custom AutomateWoo triggers.
 *
 * @param array $triggers Existing triggers.
 * @return array
 */
function mindshare_automatewoo_register_trigger( $triggers ) {
	if ( class_exists( 'AutomateWoo\Trigger' ) ) {
		require_once MINDEVENTS_ABSPATH . 'inc/automatewoo/class-trigger-event-reminder.php';
		$triggers['mindshare_event_reminder'] = 'Mindshare_AutomateWoo_Trigger_Event_Reminder';
	}

	return $triggers;
}
add_filter( 'automatewoo/triggers', 'mindshare_automatewoo_register_trigger' );
