<?php
/**
 * Class WC_Payments_Task_Disputes
 *
 * @package WooCommerce\Payments\Tasks
 */

namespace WooCommerce\Payments\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;
use WCPay\Database_Cache;

defined( 'ABSPATH' ) || exit;

/**
 * WC Onboarding Task displayed if disputes awaiting response.
 *
 * Note: this task is separate to the Payments → Overview disputes task, which is defined in client/overview/task-list/tasks.js.
 */
class WC_Payments_Task_Disputes extends Task {

	/**
	 * Gets the task ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'woocommerce_payments_disputes_task';
	}

	/**
	 * Gets the task title.
	 *
	 * @return string
	 */
	public function get_title() {
		$dispute_count = $this->get_disputes_awaiting_response_count();

		// Translators: The placeholder is the number of disputes.
		return sprintf( _n( '%d disputed payment needs your response', '%d disputed payments need your response', $dispute_count, 'woocommerce-payments' ), $dispute_count );
	}

	/**
	 * Get the parent list ID.
	 *
	 * This function prior to WC 6.4.0 was abstract and so is needed for backwards compatibility.
	 *
	 * @return string
	 */
	public function get_parent_id() {
		// WC 6.4.0 compatibility.
		if ( is_callable( 'parent::get_parent_id' ) ) {
			return parent::get_parent_id();
		}

		return 'extended';
	}

	/**
	 * Gets the task content.
	 *
	 * @return string
	 */
	public function get_content() {
		return '';
	}

	/**
	 * Get the additional info.
	 *
	 * @return string
	 */
	public function get_additional_info() {
		return __( 'View and respond', 'woocommerce-payments' );
	}

	/**
	 * Gets the task's action label.
	 *
	 * @return string
	 */
	public function get_action_label() {
		return __( 'Disputes', 'woocommerce-payments' );
	}

	/**
	 * Gets the task's action URL.
	 *
	 * @return string
	 */
	public function get_action_url() {
		return admin_url(
			add_query_arg(
				[
					'page'   => 'wc-admin',
					'path'   => '%2Fpayments%2Fdisputes',
					'filter' => 'awaiting_response',
				],
				'admin.php'
			)
		);
	}

	/**
	 * Get the estimated time to complete the task.
	 *
	 * @return string
	 */
	public function get_time() {
		return '';
	}

	/**
	 * Get whether the task is completed.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return false;
	}

	/**
	 * Get whether the task is visible.
	 *
	 * @return bool
	 */
	public function can_view() {
		return $this->get_disputes_awaiting_response_count() > 0;
	}

	/**
	 * Gets the number of disputes which need a response.
	 *
	 * Because this task is initialized before WC Payments, we can only fetch from the cache (via "get()").
	 * The dispute status cache cannot be populated via this task. If this value hasn't been cached yet, this task won't show until it is.
	 *
	 * @return int The number of disputes which need a response.
	 */
	private function get_disputes_awaiting_response_count() {
		$disputes_status_counts = \WC_Payments::get_database_cache()->get( Database_Cache::DISPUTE_STATUS_COUNTS_KEY );

		if ( empty( $disputes_status_counts ) ) {
			return 0;
		}

		$needs_response_statuses = [ 'needs_response', 'warning_needs_response' ];
		return (int) array_sum( array_intersect_key( $disputes_status_counts, array_flip( $needs_response_statuses ) ) );
	}
}
