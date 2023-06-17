<?php
/**
 * WC_Payments_Tasks class
 *
 * @package WooCommerce\Payments\Tasks
 */

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use WooCommerce\Payments\Tasks\WC_Payments_Task_Disputes;
use WCPay\Database_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Hooks into WC TaskLists to display WCPay tasks.
 */
class WC_Payments_Tasks {

	/**
	 * WC_Payments_Admin_Tasks constructor.
	 */
	public static function init() {
		include_once WCPAY_ABSPATH . 'includes/admin/tasks/class-wc-payments-task-disputes.php';

		add_action( 'init', [ __CLASS__, 'add_task_disputes_need_response' ] );
	}

	/**
	 * Adds a task to the WC 'Things to do next' task list the if disputes awaiting response.
	 */
	public static function add_task_disputes_need_response() {
		// 'extended' = 'Things to do next' task list on WooCommerce > Home.
		// TODO decide to inject api client and database cache here or get them statically in get_disputes_needing_response().
		TaskLists::add_task( 'extended', new WC_Payments_Task_Disputes( \WC_Payments::create_api_client(), \WC_Payments::get_database_cache() ) );
	}
}
