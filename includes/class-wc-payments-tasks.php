<?php
/**
 * WC_Payments_Tasks class
 *
 * @package WooCommerce\Payments\Tasks
 */

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use WooCommerce\Payments\Tasks\WC_Payments_Task_Disputes;

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
		$account_service = WC_Payments::get_account_service();
		if ( ! $account_service || ! $account_service->is_stripe_account_valid() ) {
			return;
		}

		// 'extended' = 'Things to do next' task list on WooCommerce > Home.
		TaskLists::add_task( 'extended', new WC_Payments_Task_Disputes() );
	}
}
