<?php
/**
 * WC_Payments_Action_Scheduler_Service class
 *
 * @package WooCommerce\Payments
 */

use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class which handles setting up all ActionScheduler hooks.
 */
class WC_Payments_Action_Scheduler_Service {

	const DEFAULT_PRIORITY = 10;

	/**
	 * Constructor for WC_Payments_Action_Scheduler_Service.
	 */
	public function __construct() {
		$this->add_action_scheduler_hooks();
	}

	/**
	 * Add hooks for all ActionScheduler actions.
	 *
	 * @return void
	 */
	public function add_action_scheduler_hooks() {
		add_action( 'wcpay-track_create_order', [ $this, 'track_create_order' ], self::DEFAULT_PRIORITY, 2 );
	}

	/**
	 * Schedule an ActionScheduler job to track order creation.
	 *
	 * @param int   $order_id    The ID of the order that has been created.
	 * @param array $order_data  The data for the order which has been created.
	 *
	 * @return void
	 */
	public function track_create_order( $order_id, $order_data ) {
		// TODO: implement.
	}
}
