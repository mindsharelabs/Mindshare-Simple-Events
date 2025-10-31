<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value object that represents a Mindshare event occurrence in the AutomateWoo context.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Event_Data {

	/**
	 * Sub-event ID.
	 *
	 * @var int
	 */
	protected $occurrence_id = 0;

	/**
	 * Parent event ID.
	 *
	 * @var int
	 */
	protected $parent_id = 0;

	/**
	 * Cached data store.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Create an instance from a sub-event ID.
	 *
	 * @param int $occurrence_id Sub-event ID.
	 * @return static|null
	 */
	public static function from_occurrence( $occurrence_id ) {
		$occurrence_id = absint( $occurrence_id );

		if ( ! $occurrence_id ) {
			return null;
		}

		$post = get_post( $occurrence_id );

		if ( ! $post || 'sub_event' !== $post->post_type ) {
			return null;
		}

		$instance = new static();
		$instance->occurrence_id = $occurrence_id;
		$instance->parent_id     = (int) $post->post_parent;
		$instance->bootstrap();

		return $instance;
	}

	/**
	 * Prepare derived data.
	 *
	 * @return void
	 */
	protected function bootstrap() {
		$tz = wp_timezone();

		$start_raw = get_post_meta( $this->occurrence_id, 'event_start_time_stamp', true );
		$end_raw   = get_post_meta( $this->occurrence_id, 'event_end_time_stamp', true );

		try {
			$start = $start_raw ? new DateTimeImmutable( $start_raw, $tz ) : null;
		} catch ( Exception $e ) {
			$start = null;
		}

		try {
			$end = $end_raw ? new DateTimeImmutable( $end_raw, $tz ) : null;
		} catch ( Exception $e ) {
			$end = null;
		}

		$parent_title = $this->parent_id ? get_the_title( $this->parent_id ) : '';
		$occurrence   = get_post( $this->occurrence_id );

		$this->data = array(
			'occurrence_id'   => $this->occurrence_id,
			'parent_id'       => $this->parent_id,
			'parent_title'    => $parent_title,
			'occurrence_name' => $occurrence ? $occurrence->post_title : '',
			'permalink'       => $this->parent_id ? get_permalink( $this->parent_id ) : '',
			'start'           => $start,
			'end'             => $end,
			'attendees'       => $this->load_attendees(),
			'instructors'     => $this->load_instructors(),
		);

		$this->data['attendee_count']    = count( $this->data['attendees'] );
		$this->data['checked_in_count']  = $this->count_checked_in();
		$this->data['attendees_table']   = $this->build_attendees_table();
		$this->data['attendees_json']    = $this->build_attendees_json();
		$this->data['primary_instructor'] = ! empty( $this->data['instructors'] ) ? $this->data['instructors'][0] : array();
	}

	/**
	 * Retrieve event data by key.
	 *
	 * @param string $key Data key.
	 * @param mixed  $default Default when not found.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( array_key_exists( $key, $this->data ) ) {
			return $this->data[ $key ];
		}

		return $default;
	}

	/**
	 * Get the occurrence (sub-event) ID.
	 *
	 * @return int
	 */
	public function get_occurrence_id() {
		return $this->occurrence_id;
	}

	/**
	 * Get the parent event ID.
	 *
	 * @return int
	 */
	public function get_parent_id() {
		return $this->parent_id;
	}

	/**
	 * Event start datetime.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_start() {
		return $this->get( 'start' );
	}

	/**
	 * Event end datetime.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_end() {
		return $this->get( 'end' );
	}

	/**
	 * Attendee count.
	 *
	 * @return int
	 */
	public function get_attendee_count() {
		return (int) $this->get( 'attendee_count', 0 );
	}

	/**
	 * Checked-in count.
	 *
	 * @return int
	 */
	public function get_checked_in_count() {
		return (int) $this->get( 'checked_in_count', 0 );
	}

	/**
	 * Render a human-friendly formatted start datetime.
	 *
	 * @param string $format Optional custom format.
	 * @return string
	 */
	public function get_formatted_start( $format = '' ) {
		$start = $this->get_start();

		if ( ! $start ) {
			return '';
		}

		if ( ! $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return $start->format( $format );
	}

	/**
	 * Render a human-friendly formatted end datetime.
	 *
	 * @param string $format Optional format override.
	 * @return string
	 */
	public function get_formatted_end( $format = '' ) {
		$end = $this->get_end();

		if ( ! $end ) {
			return '';
		}

		if ( ! $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return $end->format( $format );
	}

	/**
	 * Tokens used to prevent duplicate workflow executions.
	 *
	 * @return array
	 */
	protected function get_workflow_tokens() {
		$tokens = get_post_meta( $this->occurrence_id, '_mindevents_aw_workflows', true );

		return is_array( $tokens ) ? $tokens : array();
	}

	/**
	 * Record that a workflow has executed for this occurrence/target timestamp.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @param int $target_timestamp Target timestamp.
	 * @return void
	 */
	public function mark_workflow_execution( $workflow_id, $target_timestamp ) {
		$tokens = $this->get_workflow_tokens();
		$tokens[ $workflow_id ] = (int) $target_timestamp;
		update_post_meta( $this->occurrence_id, '_mindevents_aw_workflows', $tokens );
	}

	/**
	 * Check if a workflow has already executed for the provided target timestamp.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @param int $target_timestamp Target timestamp.
	 * @return bool
	 */
	public function workflow_has_run( $workflow_id, $target_timestamp ) {
		$tokens = $this->get_workflow_tokens();

		if ( ! isset( $tokens[ $workflow_id ] ) ) {
			return false;
		}

		// Allow re-run if the target changes (event rescheduled).
		if ( (int) $tokens[ $workflow_id ] !== (int) $target_timestamp ) {
			return false;
		}

		return true;
	}

	/**
	 * Calculate the target datetime for a configured reminder.
	 *
	 * @param int    $days_before Days prior to the event.
	 * @param string $time_of_day Time string (HH:MM).
	 * @return DateTimeImmutable|null
	 */
	public function get_target_datetime( $days_before, $time_of_day ) {
		$start = $this->get_start();

		if ( ! $start ) {
			return null;
		}

		$days_before = max( 0, absint( $days_before ) );
		$time_of_day = $time_of_day ? $time_of_day : '08:00';

		try {
			$target = $start->modify( sprintf( '-%d days', $days_before ) );
		} catch ( Exception $e ) {
			return null;
		}

		$time_parts = array_map( 'absint', explode( ':', $time_of_day ) );
		$hour       = isset( $time_parts[0] ) ? min( 23, $time_parts[0] ) : 0;
		$minute     = isset( $time_parts[1] ) ? min( 59, $time_parts[1] ) : 0;

		return $target->setTime( $hour, $minute, 0 );
	}

	/**
	 * Convert stored data to array.
	 *
	 * @return array
	 */
	public function to_array() {
		$payload = $this->data;

		if ( $payload['start'] instanceof DateTimeInterface ) {
			$payload['start'] = $payload['start']->format( DATE_ATOM );
		}

		if ( $payload['end'] instanceof DateTimeInterface ) {
			$payload['end'] = $payload['end']->format( DATE_ATOM );
		}

		return $payload;
	}

	/**
	 * Build attendee data array.
	 *
	 * @return array
	 */
	protected function load_attendees() {
		$entries = array();

		if ( ! $this->parent_id ) {
			return $entries;
		}

		$attendees_meta = get_post_meta( $this->parent_id, 'attendees', true );

		if ( ! is_array( $attendees_meta ) ) {
			return $entries;
		}

		$occurrence_attendees = isset( $attendees_meta[ $this->occurrence_id ] ) ? $attendees_meta[ $this->occurrence_id ] : array();

		if ( ! is_array( $occurrence_attendees ) ) {
			return $entries;
		}

		foreach ( $occurrence_attendees as $entry ) {
			$order_id = isset( $entry['order_id'] ) ? absint( $entry['order_id'] ) : 0;
			$user_id  = isset( $entry['user_id'] ) ? absint( $entry['user_id'] ) : 0;

			$order = $order_id ? wc_get_order( $order_id ) : null;

			$email = '';
			$name  = '';

			if ( $order ) {
				$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				$email = $order->get_billing_email();
			}

			if ( ! $name && $user_id ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user ) {
					$name  = trim( $user->first_name . ' ' . $user->last_name );
					$email = $user->user_email;
				}
			}

			$is_member = false;
			if ( $user_id && function_exists( 'wc_memberships_is_user_active_member' ) ) {
				$is_member = wc_memberships_is_user_active_member( $user_id );
			}

			$entries[] = array(
				'order_id'    => $order_id,
				'user_id'     => $user_id,
				'name'        => $name ? $name : __( 'Guest', 'mindshare-events' ),
				'email'       => $email,
				'is_member'   => (bool) $is_member,
				'checked_in'  => ! empty( $entry['checked_in'] ),
				'member_text' => $is_member ? __( 'Yes', 'mindshare-events' ) : __( 'No', 'mindshare-events' ),
				'order_url'   => $order_id ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '',
			);
		}

		return $entries;
	}

	/**
	 * Load instructor data for the occurrence.
	 *
	 * @return array
	 */
	protected function load_instructors() {
		$instructors = array();

		$email = get_post_meta( $this->occurrence_id, 'instructorEmail', true );

		if ( $email ) {
			$instructors[] = $this->build_instructor_from_email( $email );
		}

		if ( empty( $instructors ) && function_exists( 'get_field' ) && $this->parent_id ) {
			$parent_instructors = get_field( 'instructors', $this->parent_id );

			if ( is_array( $parent_instructors ) ) {
				foreach ( $parent_instructors as $item ) {
					if ( is_array( $item ) && isset( $item['ID'] ) ) {
						$instructors[] = $this->build_instructor_from_user_id( $item['ID'] );
					} elseif ( is_numeric( $item ) ) {
						$instructors[] = $this->build_instructor_from_user_id( (int) $item );
					}
				}
			}
		}

		// Remove empties / duplicates.
		$instructors = array_filter(
			$instructors,
			function ( $item ) {
				return ! empty( $item['email'] ) || ! empty( $item['name'] );
			}
		);

		return array_values( $instructors );
	}

	/**
	 * Construct instructor payload from email.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	protected function build_instructor_from_email( $email ) {
		$email = sanitize_email( $email );
		$user  = $email ? get_user_by( 'email', $email ) : null;

		$name  = '';
		$phone = '';

		if ( $user ) {
			$name  = trim( $user->first_name . ' ' . $user->last_name );
			$phone = get_user_meta( $user->ID, 'billing_phone', true );
		}

		return array(
			'email' => $email,
			'name'  => $name ? $name : $email,
			'phone' => $phone,
		);
	}

	/**
	 * Construct instructor payload from a user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	protected function build_instructor_from_user_id( $user_id ) {
		$user = $user_id ? get_user_by( 'id', $user_id ) : null;

		if ( ! $user ) {
			return array(
				'email' => '',
				'name'  => '',
				'phone' => '',
			);
		}

		$phone = get_user_meta( $user->ID, 'billing_phone', true );

		return array(
			'email' => $user->user_email,
			'name'  => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
			'phone' => $phone,
		);
	}

	/**
	 * Count checked-in attendees.
	 *
	 * @return int
	 */
	protected function count_checked_in() {
		$count = 0;

		foreach ( $this->data['attendees'] as $attendee ) {
			if ( ! empty( $attendee['checked_in'] ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Render attendee table HTML.
	 *
	 * @return string
	 */
	protected function build_attendees_table() {
		if ( empty( $this->data['attendees'] ) ) {
			return '<p>' . esc_html__( 'No attendees registered yet.', 'mindshare-events' ) . '</p>';
		}

		ob_start();
		?>
		<table class="mindevents-attendees" style="width:100%; border-collapse:collapse;">
			<thead>
				<tr>
					<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px;"><?php esc_html_e( 'Name', 'mindshare-events' ); ?></th>
					<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px;"><?php esc_html_e( 'Email', 'mindshare-events' ); ?></th>
					<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px;"><?php esc_html_e( 'Member', 'mindshare-events' ); ?></th>
					<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px;"><?php esc_html_e( 'Order', 'mindshare-events' ); ?></th>
					<th style="text-align:left; border-bottom:1px solid #ddd; padding:4px;"><?php esc_html_e( 'Checked In', 'mindshare-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->data['attendees'] as $attendee ) : ?>
					<tr>
						<td style="padding:4px; border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $attendee['name'] ); ?></td>
						<td style="padding:4px; border-bottom:1px solid #f0f0f0;"><a href="mailto:<?php echo esc_attr( $attendee['email'] ); ?>"><?php echo esc_html( $attendee['email'] ); ?></a></td>
						<td style="padding:4px; border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $attendee['member_text'] ); ?></td>
						<td style="padding:4px; border-bottom:1px solid #f0f0f0;">
							<?php if ( $attendee['order_id'] ) : ?>
								<a href="<?php echo esc_url( $attendee['order_url'] ); ?>" target="_blank" rel="noopener noreferrer">#<?php echo esc_html( $attendee['order_id'] ); ?></a>
							<?php else : ?>
								<?php esc_html_e( 'N/A', 'mindshare-events' ); ?>
							<?php endif; ?>
						</td>
						<td style="padding:4px; border-bottom:1px solid #f0f0f0;"><?php echo ! empty( $attendee['checked_in'] ) ? esc_html__( 'Yes', 'mindshare-events' ) : esc_html__( 'No', 'mindshare-events' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		return trim( ob_get_clean() );
	}

	/**
	 * Build JSON representation of attendee data.
	 *
	 * @return string
	 */
	protected function build_attendees_json() {
		if ( empty( $this->data['attendees'] ) ) {
			return '[]';
		}

		return wp_json_encode( $this->data['attendees'] );
	}

	/**
	 * Human readable title combining occurrence and parent names.
	 *
	 * @return string
	 */
	public function get_display_title() {
		$title = $this->get( 'occurrence_name', '' );

		if ( $title ) {
			return $title;
		}

		return $this->get( 'parent_title', __( 'Mindshare Event', 'mindshare-events' ) );
	}
}
