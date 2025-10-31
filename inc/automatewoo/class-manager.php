<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates AutomateWoo specific functionality for Mindshare Events.
 *
 * @since 1.6.0
 */
class Mindshare_AutomateWoo_Manager {

	/**
	 * WP-Cron hook that scans upcoming events.
	 */
	const CRON_HOOK = 'mindevents_automatewoo_scan';

	/**
	 * Internal action used to notify triggers of an occurrence update.
	 */
	const PROCESS_HOOK = 'mindevents/automatewoo/process_occurrence';

	/**
	 * Boot the manager.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_custom_schedule' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_scan' ) );

		add_action( 'save_post_sub_event', array( __CLASS__, 'handle_sub_event_save' ), 20, 3 );
		add_action( 'save_post_events', array( __CLASS__, 'handle_parent_event_save' ), 20, 3 );
		add_action( 'mindevents/occurrence/updated', array( __CLASS__, 'dispatch_updated_occurrence' ), 10, 1 );
	}

	/**
	 * Register a quarter-hour cron schedule for timely reminders.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_custom_schedule( $schedules ) {
		if ( ! isset( $schedules['mindevents_quarter_hour'] ) ) {
			$schedules['mindevents_quarter_hour'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Mindshare Events (every 15 minutes)', 'mindshare-events' ),
			);
		}

		return $schedules;
	}

	/**
	 * Make sure our cron event is scheduled.
	 *
	 * @return void
	 */
	public static function ensure_cron_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'mindevents_quarter_hour', self::CRON_HOOK );
		}
	}

	/**
	 * Run the scheduled scan for upcoming occurrences.
	 *
	 * @return void
	 */
	public static function run_scheduled_scan() {
		self::process_upcoming_occurrences( 'cron' );
	}

	/**
	 * Handle updates to a single occurrence.
	 *
	 * @param int $occurrence_id Sub-event ID.
	 * @param string $context Optional context descriptor.
	 * @return void
	 */
	public static function dispatch_occurrence( $occurrence_id, $context = 'scan' ) {
		/**
		 * Fires when an AutomateWoo-aware occurrence should be evaluated.
		 *
		 * @param int    $occurrence_id Sub-event ID.
		 * @param string $context       Source context (scan, update, manual, etc).
		 */
		do_action( self::PROCESS_HOOK, absint( $occurrence_id ), $context );
	}

	/**
	 * Touch helper used by other components.
	 *
	 * @param int $occurrence_id Sub-event ID.
	 * @return void
	 */
	public static function touch_occurrence( $occurrence_id ) {
		if ( ! $occurrence_id ) {
			return;
		}

		/**
		 * Fires when an occurrence has changed and triggers should be re-evaluated.
		 *
		 * @param int $occurrence_id Sub-event ID.
		 */
		do_action( 'mindevents/occurrence/updated', absint( $occurrence_id ) );
	}

	/**
	 * Internal callback to dispatch after a touch event.
	 *
	 * @param int $occurrence_id Sub-event ID.
	 * @return void
	 */
	public static function dispatch_updated_occurrence( $occurrence_id ) {
		self::dispatch_occurrence( $occurrence_id, 'update' );
	}

	/**
	 * Ensure new/updated sub-events trigger reminder evaluation.
	 *
	 * @param int     $post_id Sub-event ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 * @return void
	 */
	public static function handle_sub_event_save( $post_id, $post, $update ) {
		if ( 'sub_event' !== $post->post_type ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		self::touch_occurrence( $post_id );
	}

	/**
	 * When a parent event is saved propagate to all occurrences.
	 *
	 * @param int     $post_id Parent event ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Update flag.
	 * @return void
	 */
	public static function handle_parent_event_save( $post_id, $post, $update ) {
		if ( 'events' !== $post->post_type ) {
			return;
		}

		$sub_events = get_posts(
			array(
				'post_type'      => 'sub_event',
				'post_parent'    => $post_id,
				'post_status'    => array( 'publish', 'future', 'pending', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		foreach ( $sub_events as $occurrence_id ) {
			self::touch_occurrence( $occurrence_id );
		}
	}

	/**
	 * Process upcoming occurrences and dispatch to triggers.
	 *
	 * @param string $context Execution context.
	 * @return void
	 */
	public static function process_upcoming_occurrences( $context = 'scan' ) {
		$occurrence_ids = self::get_upcoming_occurrence_ids();

		foreach ( $occurrence_ids as $occurrence_id ) {
			self::dispatch_occurrence( $occurrence_id, $context );
		}
	}

	/**
	 * Locate upcoming occurrences that we should evaluate.
	 *
	 * @param int $window_days How far ahead to look (defaults to 90 days).
	 * @return int[]
	 */
	protected static function get_upcoming_occurrence_ids( $window_days = 90 ) {
		$now        = current_time( 'timestamp' );
		$window_end = $now + ( absint( $window_days ) * DAY_IN_SECONDS );

		$args = array(
			'post_type'      => 'sub_event',
			'post_status'    => array( 'publish', 'future', 'private' ),
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_key'       => 'event_start_time_stamp',
			'meta_type'      => 'DATETIME',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'event_start_time_stamp',
					'value'   => gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => 'event_start_time_stamp',
					'value'   => gmdate( 'Y-m-d H:i:s', $window_end ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
			),
		);

		$query = new WP_Query( $args );

		return $query->posts;
	}
}

if ( ! function_exists( 'mindshare_automatewoo_touch_occurrence' ) ) :
	/**
	 * Helper wrapper to safely notify AutomateWoo integration of updates.
	 *
	 * @param int $occurrence_id Sub-event ID.
	 * @return void
	 */
	function mindshare_automatewoo_touch_occurrence( $occurrence_id ) {
		if ( class_exists( 'Mindshare_AutomateWoo_Manager' ) ) {
			Mindshare_AutomateWoo_Manager::touch_occurrence( $occurrence_id );
		}
	}
endif;

