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
		include_once WCPAY_ABSPATH . 'includes/tasks/class-wc-payments-task-disputes.php';

		add_action( 'init', [ __CLASS__, 'add_task_disputes_need_response' ] );
	}

	/**
	 * Adds a task to WC 'Things to do next' task list the if disputes awaiting response.
	 */
	public static function add_task_disputes_need_response() {
		$task = new WC_Payments_Task_Disputes();
		// 'extended' = 'Things to do next' task list.
		TaskLists::add_task( 'extended', $task );
	}
}
