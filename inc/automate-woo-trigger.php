<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access
}

/**
 * This is an example trigger that is triggered via a WordPress action and includes a user data item.
 * Trigger with: do_action('mind_events_trigger', $user_id );
 */
class Class_Reminder_Trigger extends AutomateWoo\Trigger {

	/**
	 * Define which data items are set by this trigger, this determines which rules and actions will be available
	 *
	 * @var array
	 */
	public $supplied_data_items = array( 'customer' );

	/**
	 * Set up the trigger
	 */
	public function init() {
		$this->title = __( '3 Days Before Class', 'mindevents' );
		$this->group = __( 'Mind Events Triggers', 'mindevents' );
	}

	/**
	 * Add any fields to the trigger (optional)
	 */
	public function load_fields() {}

	/**
	 * Defines when the trigger is run
	 */
	public function register_hooks() {
		add_action( 'woo_event_three_days_before', array( $this, 'catch_hooks' ), 1, 2 );
	}

	/**
	 * Catches the action and calls the maybe_run() method.
	 *
	 * @param $user_id
	 */
	public function catch_hooks( $order_id, $product_id ) {
 
		// get/create customer object from the user id
		$customer = AutomateWoo\Customer_Factory::get_by_order( wc_get_order($order_id));
		$this->maybe_run(array(
			'customer' => $customer,
            'order' => wc_get_order($order_id),
            'product' => wc_get_product($product_id)
		));
	}

	/**
	 * Performs any validation if required. If this method returns true the trigger will fire.
	 *
	 * @param $workflow AutomateWoo\Workflow
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		$product = $workflow->data_layer()->get_product();
        //check if linked_event is still published
        $linked_event = get_post_meta($product->get_id(), 'linked_event', true);
        $event = get_post($linked_event);
        if(!$event || $event->post_status != 'publish') {
            return false;
        }
		return true;
	}

}


