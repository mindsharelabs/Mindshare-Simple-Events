<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires a workflow a configurable number of days before an event at a specific time.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Trigger_Event_Reminder extends AutomateWoo\Trigger {

	/**
	 * Data items made available to the workflow.
	 *
	 * @var array
	 */
	public $supplied_data_items = array( 'mindshare_event' );

	/**
	 * Current event during validation.
	 *
	 * @var Mindshare_AutomateWoo_Event_Data|null
	 */
	protected $current_event;

	/**
	 * Current target datetime.
	 *
	 * @var DateTimeImmutable|null
	 */
	protected $current_target;

	/**
	 * Trigger label and grouping.
	 *
	 * @return void
	 */
	public function init() {
		$this->title = __( 'Mindshare Event Reminder', 'mindshare-events' );
		$this->group = __( 'Mindshare Events', 'mindshare-events' );
	}

	/**
	 * Register workflow configuration fields.
	 *
	 * @return void
	 */
	public function load_fields() {
		$days_before = ( new \AutomateWoo\Fields\Number() )
			->set_name( 'days_before' )
			->set_title( __( 'Days Before Event', 'mindshare-events' ) )
			->set_description( __( 'How many days before the event the workflow should run.', 'mindshare-events' ) );

		$send_time = ( new \AutomateWoo\Fields\Time() )
			->set_name( 'send_time' )
			->set_title( __( 'Send At (site time)', 'mindshare-events' ) )
			->set_description( __( 'Select the time of day (site timezone) that the workflow should fire.', 'mindshare-events' ) );

		$min_attendees = ( new \AutomateWoo\Fields\Number() )
			->set_name( 'min_attendees' )
			->set_title( __( 'Minimum Attendee Count', 'mindshare-events' ) )
			->set_description( __( 'Optional minimum attendee count required for the workflow to run.', 'mindshare-events' ) );

		$max_attendees = ( new \AutomateWoo\Fields\Number() )
			->set_name( 'max_attendees' )
			->set_title( __( 'Maximum Attendee Count', 'mindshare-events' ) )
			->set_description( __( 'Optional maximum attendee count. Leave blank for no limit.', 'mindshare-events' ) );

		$this->add_field( $days_before );
		$this->add_field( $send_time );
		$this->add_field( $min_attendees );
		$this->add_field( $max_attendees );
	}

	/**
	 * Hook into our internal processing action.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( Mindshare_AutomateWoo_Manager::PROCESS_HOOK, array( $this, 'evaluate_occurrence' ), 10, 2 );
	}

	/**
	 * Evaluate an occurrence for potential workflow execution.
	 *
	 * @param int    $occurrence_id Sub-event ID.
	 * @param string $context       Context string.
	 * @return void
	 */
	public function evaluate_occurrence( $occurrence_id, $context = 'scan' ) {
		$event = Mindshare_AutomateWoo_Event_Data::from_occurrence( $occurrence_id );

		if ( ! $event ) {
			return;
		}

		$days_before = absint( $this->get_setting( 'days_before', 1 ) );
		$send_time   = $this->get_setting( 'send_time', '08:00' );

		$target = $event->get_target_datetime( $days_before, $send_time );

		if ( ! $target ) {
			return;
		}

		$window       = apply_filters( 'mindevents/automatewoo/reminder_window', 15 * MINUTE_IN_SECONDS, $this, $event, $context );
		$now_timestamp = current_time( 'timestamp' );
		$target_ts     = $target->getTimestamp();

		if ( $now_timestamp < ( $target_ts - $window ) || $now_timestamp > ( $target_ts + $window ) ) {
			return;
		}

		$this->current_event  = $event;
		$this->current_target = $target;

		if ( ! class_exists( 'Mindshare_AutomateWoo_Data_Item_Event', false ) ) {
			require_once MINDEVENTS_ABSPATH . 'inc/automatewoo/class-data-item-event.php';
		}

		if ( ! class_exists( 'Mindshare_AutomateWoo_Data_Item_Event', false ) ) {
			return;
		}

		$data_item = ( new Mindshare_AutomateWoo_Data_Item_Event() )->set_event( $event );

		$data = array(
			'mindshare_event' => $data_item,
		);

		$this->maybe_run( $data );

		$this->current_event  = null;
		$this->current_target = null;
	}

	/**
	 * Additional validation to avoid duplicate runs and honour attendee limits.
	 *
	 * @param AutomateWoo\Workflow $workflow Workflow.
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		if ( ! $this->current_event || ! $this->current_target ) {
			return false;
		}

		$count          = $this->current_event->get_attendee_count();
		$min_attendees  = absint( $this->get_setting( 'min_attendees', 0 ) );
		$max_attendees  = $this->get_setting( 'max_attendees', '' );
		$max_attendees  = '' === $max_attendees ? '' : absint( $max_attendees );
		$target_ts      = $this->current_target->getTimestamp();
		$workflow_id    = method_exists( $workflow, 'get_id' ) ? (int) $workflow->get_id() : 0;

		if ( $count < $min_attendees ) {
			return false;
		}

		if ( '' !== $max_attendees && $max_attendees && $count > $max_attendees ) {
			return false;
		}

		if ( $workflow_id && $this->current_event->workflow_has_run( $workflow_id, $target_ts ) ) {
			return false;
		}

		if ( $workflow_id ) {
			$this->current_event->mark_workflow_execution( $workflow_id, $target_ts );
		}

		return true;
}

	/**
	 * Helper to safely fetch trigger settings across AutomateWoo versions.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_setting( $key, $default = null ) {
		if ( method_exists( 'AutomateWoo\Trigger', 'get_option' ) ) {
			return parent::get_option( $key, $default );
		}

		if ( isset( $this->options[ $key ] ) ) {
			return $this->options[ $key ];
		}

		return $default;
	}
}
