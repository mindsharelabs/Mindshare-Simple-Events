<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AutomateWoo variable to expose the Mindshare event start datetime for an order item.
 */
class Mindshare_AutomateWoo_Variable_Order_Item_Event_Start extends AutomateWoo\Variable_Abstract_Datetime {

	protected $name = 'order_item.mindshare_event_start';

	public function load_admin_details() {
		$this->description = __( 'Start date and time for the Mindshare event linked to this order item.', 'mindshare-events' );
		parent::load_admin_details();
	}

	/**
	 * @param AutomateWoo\Order_Item $order_item
	 * @param array                  $parameters
	 *
	 * @return string|false
	 */
	public function get_value( $order_item, $parameters ) {
		if ( ! is_object( $order_item ) ) {
			return false;
		}

		$value = $this->get_meta_value( $order_item, array( '_mindevents_event_start', '_mindevent_start_datetime' ) );

		if ( ! $value ) {
			return false;
		}

		$timestamp = strtotime( $value );

		if ( ! $timestamp ) {
			return false;
		}

		return $this->format_datetime( $timestamp, $parameters );
	}

	/**
	 * Attempt to retrieve the requested meta key from the AutomateWoo order item,
	 * falling back to the underlying WC_Order_Item or parent order if needed.
	 *
	 * @param object   $order_item
	 * @param string[] $keys
	 *
	 * @return string
	 */
	protected function get_meta_value( $order_item, array $keys ) {
		foreach ( $keys as $key ) {
			// Try AutomateWoo's accessor first.
			if ( method_exists( $order_item, 'get_meta' ) ) {
				$value = $order_item->get_meta( $key, true );
				if ( $value ) {
					return $value;
				}
			}

			// Access the underlying WC_Order_Item if available.
			if ( method_exists( $order_item, 'get_wc_order_item' ) ) {
				$wc_item = $order_item->get_wc_order_item();
				if ( $wc_item instanceof \WC_Order_Item ) {
					$value = $wc_item->get_meta( $key, true );
					if ( $value ) {
						return $value;
					}
				}
			}

			// Fall back to the parent order meta (helps older orders without per-line meta).
			if ( method_exists( $order_item, 'get_order' ) ) {
				$order = $order_item->get_order();
				if ( $order instanceof \AutomateWoo\Order ) {
					$value = $order->get_meta( $key, true );
					if ( $value ) {
						return $value;
					}
				} elseif ( $order instanceof \WC_Order ) {
					$value = $order->get_meta( $key, true );
					if ( $value ) {
						return $value;
					}
				}
			}
		}

		return '';
	}
}

return new Mindshare_AutomateWoo_Variable_Order_Item_Event_Start();
