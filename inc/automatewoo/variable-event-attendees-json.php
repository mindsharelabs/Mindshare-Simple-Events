<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns attendee data JSON for alternate templating.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Variable_Event_Attendees_Json extends AutomateWoo\Variable {

	protected $name = 'mindshare_event.attendees_json';

	public function load_admin_details() {
		$this->description = __( 'JSON encoded attendee list for the current Mindshare event occurrence.', 'mindshare-events' );
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
			return '[]';
		}

		return $event->get( 'attendees_json', '[]' );
	}
}

return new Mindshare_AutomateWoo_Variable_Event_Attendees_Json();
