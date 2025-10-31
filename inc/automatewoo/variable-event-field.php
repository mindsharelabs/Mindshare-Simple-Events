<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic variable for accessing Mindshare event occurrence fields.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Variable_Event_Field extends AutomateWoo\Variable {

	protected $name = 'mindshare_event.field';

	/**
	 * Configure admin UI.
	 *
	 * @return void
	 */
	public function load_admin_details() {
		$this->description = __( 'Access Mindshare event occurrence fields (title, formatted dates, counts, etc).', 'mindshare-events' );

		$options = array(
			'title'             => __( 'Event Title', 'mindshare-events' ),
			'parent_title'      => __( 'Parent Event Title', 'mindshare-events' ),
			'start_datetime'    => __( 'Start (date & time)', 'mindshare-events' ),
			'start_date'        => __( 'Start Date', 'mindshare-events' ),
			'start_time'        => __( 'Start Time', 'mindshare-events' ),
			'end_datetime'      => __( 'End (date & time)', 'mindshare-events' ),
			'end_date'          => __( 'End Date', 'mindshare-events' ),
			'end_time'          => __( 'End Time', 'mindshare-events' ),
			'attendee_count'    => __( 'Attendee Count', 'mindshare-events' ),
			'checked_in_count'  => __( 'Checked-in Count', 'mindshare-events' ),
			'permalink'         => __( 'Event URL', 'mindshare-events' ),
			'instructor_names'  => __( 'Instructor Names (comma separated)', 'mindshare-events' ),
			'instructor_emails' => __( 'Instructor Emails (comma separated)', 'mindshare-events' ),
		);

		if ( method_exists( $this, 'add_parameter_select_field' ) ) {
			$this->add_parameter_select_field(
				'key',
				__( 'Field', 'mindshare-events' ),
				$options
			);
		} else {
			$this->add_parameter_text_field( 'key', __( 'Field Key', 'mindshare-events' ) );
			$this->description .= ' ' . __( 'Available keys: ', 'mindshare-events' ) . implode( ', ', array_keys( $options ) );
		}
	}

	/**
	 * Retrieve a value for the workflow template.
	 *
	 * @param array $payload    Data payload.
	 * @param array $parameters Variable parameters.
	 * @return string|int
	 */
	public function get_value( $data_item, $parameters ) {
		$event = $this->get_event_from_payload( $data_item );
		if ( ! $event ) {
			return '';
		}

		$key = isset( $parameters['key'] ) ? $parameters['key'] : '';

		switch ( $key ) {
			case 'title':
				return $event->get( 'occurrence_name', '' ) ?: $event->get_display_title();

			case 'parent_title':
				return $event->get( 'parent_title', '' );

			case 'start_datetime':
				return $event->get_formatted_start();

			case 'start_date':
				return $event->get_formatted_start( get_option( 'date_format' ) );

			case 'start_time':
				return $event->get_formatted_start( get_option( 'time_format' ) );

			case 'end_datetime':
				return $event->get_formatted_end();

			case 'end_date':
				return $event->get_formatted_end( get_option( 'date_format' ) );

			case 'end_time':
				return $event->get_formatted_end( get_option( 'time_format' ) );

			case 'attendee_count':
				return $event->get_attendee_count();

			case 'checked_in_count':
				return $event->get_checked_in_count();

			case 'permalink':
				return $event->get( 'permalink', '' );

			case 'instructor_names':
				return $this->implode_instructor_field( $event, 'name' );

			case 'instructor_emails':
				return $this->implode_instructor_field( $event, 'email' );
		}

		return '';
	}

	/**
	 * Combine instructor values into a string.
	 *
	 * @param Mindshare_AutomateWoo_Event_Data $event Event data.
	 * @param string                           $field Field key.
	 * @return string
	 */
	protected function implode_instructor_field( $event, $field ) {
		$instructors = $event->get( 'instructors', array() );

		if ( empty( $instructors ) ) {
			return '';
		}

		return implode(
			', ',
			array_filter(
				array_map(
					function ( $item ) use ( $field ) {
						return isset( $item[ $field ] ) ? $item[ $field ] : '';
					},
					$instructors
				)
			)
		);
	}

	/**
	 * Resolve the Mindshare event payload from AutomateWoo.
	 *
	 * @param mixed $payload Data payload.
	 * @return Mindshare_AutomateWoo_Event_Data|null
	 */
	protected function get_event_from_payload( $payload ) {
		if ( $payload instanceof Mindshare_AutomateWoo_Data_Item_Event ) {
			return $payload->get_event();
		}

		if ( $payload instanceof Mindshare_AutomateWoo_Event_Data ) {
			return $payload;
		}

		if ( is_array( $payload ) && isset( $payload['occurrence_id'] ) ) {
			return Mindshare_AutomateWoo_Event_Data::from_occurrence( absint( $payload['occurrence_id'] ) );
		}

		return null;
	}
}

return new Mindshare_AutomateWoo_Variable_Event_Field();
