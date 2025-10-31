<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AutomateWoo variable exposing the earliest Mindshare event start on an order.
 */
class Mindshare_AutomateWoo_Variable_Order_Event_Start extends AutomateWoo\Variable_Abstract_Datetime {

	protected $name = 'order.mindshare_event_start';

	public function load_admin_details() {
		$this->description = __( 'First event start date/time tied to this order.', 'mindshare-events' );
		parent::load_admin_details();
	}

	/**
	 * @param AutomateWoo\Order $order
	 * @param array             $parameters
	 *
	 * @return string|false
	 */
	public function get_value( $order, $parameters ) {
		if ( ! $order instanceof AutomateWoo\Order ) {
			return false;
		}

		$value = $order->get_meta( '_mindevents_event_start', true );

		if ( ! $value ) {
			$value = $order->get_meta( '_mindevent_start_datetime', true );
		}

		if ( ! $value ) {
			return false;
		}

		$timestamp = strtotime( $value );

		if ( ! $timestamp ) {
			return false;
		}

		return $this->format_datetime( $timestamp, $parameters );
	}
}

return new Mindshare_AutomateWoo_Variable_Order_Event_Start();
