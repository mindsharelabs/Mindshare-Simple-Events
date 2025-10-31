<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\AutomateWoo\Data_Item' ) ) {
	return;
}

if ( ! class_exists( 'Mindshare_AutomateWoo_Data_Item_Event', false ) ) {

	/**
	 * AutomateWoo data item representing a Mindshare event occurrence.
	 *
	 * @since 1.6.0
	 */
	class Mindshare_AutomateWoo_Data_Item_Event extends AutomateWoo\Data_Item {

		/**
		 * Data item name used inside AutomateWoo.
		 *
		 * @var string
		 */
		public $name = 'mindshare_event';

		/**
		 * Event payload.
		 *
		 * @var Mindshare_AutomateWoo_Event_Data|null
		 */
		protected $event;

		/**
		 * Constructor.
		 *
		 * @param Mindshare_AutomateWoo_Event_Data|null $event Event data.
		 */
		public function __construct( $event = null ) {
			if ( $event instanceof Mindshare_AutomateWoo_Event_Data ) {
				$this->set_event( $event );
			}
		}

		/**
		 * Assign the event payload.
		 *
		 * @param Mindshare_AutomateWoo_Event_Data $event Event data.
		 * @return $this
		 */
		public function set_event( Mindshare_AutomateWoo_Event_Data $event ) {
			$this->event = $event;
			return $this;
		}

		/**
		 * Retrieve the underlying event payload.
		 *
		 * @return Mindshare_AutomateWoo_Event_Data|null
		 */
		public function get_event() {
			return $this->event;
		}

		/**
		 * Required by AutomateWoo – returns the object represented by the data item.
		 *
		 * @return Mindshare_AutomateWoo_Event_Data|null
		 */
		public function get_object() {
			return $this->event;
		}

		/**
		 * Friendly title for the workflow UI.
		 *
		 * @return string
		 */
		public function get_title() {
			if ( ! $this->event ) {
				return __( 'Mindshare Event', 'mindshare-events' );
			}

			return $this->event->get_display_title();
		}
	}
}
