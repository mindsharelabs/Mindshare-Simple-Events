<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AutomateWoo variable exposing the final Mindshare event end on an order.
 */
class Mindshare_AutomateWoo_Variable_Order_Event_End extends AutomateWoo\Variable_Abstract_Datetime {

	protected $name = 'order.mindshare_event_end';

	public function load_admin_details() {
		$this->description = __( 'Last event end date/time tied to this order.', 'mindshare-events' );
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

		$value = $order->get_meta( '_mindevents_event_end', true );

		if ( ! $value ) {
			$value = $order->get_meta( '_mindevent_end_datetime', true );
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

return new Mindshare_AutomateWoo_Variable_Order_Event_End();
