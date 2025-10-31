<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs attendee table markup for an event occurrence.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Variable_Event_Attendees_Table extends AutomateWoo\Variable {

	protected $name = 'mindshare_event.attendees_table';

	public function load_admin_details() {
		$this->description = __( 'HTML table of attendees for the current Mindshare event occurrence.', 'mindshare-events' );
	}

	public function get_value( $payload, $parameters ) {
		$event = null;

		if ( $payload instanceof Mindshare_AutomateWoo_Data_Item_Event ) {
			$event = $payload->get_event();
		} elseif ( $payload instanceof Mindshare_AutomateWoo_Event_Data ) {
			$event = $payload;
		} elseif ( is_array( $payload ) && isset( $payload['occurrence_id'] ) ) {
			$event = Mindshare_AutomateWoo_Event_Data::from_occurrence( absint( $payload['occurrence_id'] ) );
		}

		if ( ! $event ) {
			return '';
		}

		return $event->get( 'attendees_table', '' );
	}
}

return new Mindshare_AutomateWoo_Variable_Event_Attendees_Table();
