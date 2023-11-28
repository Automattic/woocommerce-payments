<?php
/**
 * Class WC_Payments_Task_Refund_Policy
 *
 * @package WooCommerce\Payments\Tasks
 */

namespace WooCommerce\Payments\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;

defined( 'ABSPATH' ) || exit;

/**
 * WC Onboarding Task displayed if refund policy is not set-up.
 */
class WC_Payments_Task_Refund_Policy extends Task {
	/**
	 * URL to the documentation about the refund policy.
	 */
	const TASK_DOCUMENTATION_URL = 'https://woo.com/document/woocommerce-refunds/#how-do-i-inform-my-customers-about-the-refund-policy';

	/**
	 * Gets the task ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'woocommerce_payments_refund_policy_task';
	}

	/**
	 * Gets the task title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Set up refund policy', 'woocommerce-payments' );
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
	 * Gets the task subtitle.
	 *
	 * @return string
	 */
	public function get_additional_info() {
		return __(
			'Protect your merchant account from unauthorized transaction disputes by defining the policy and making it accessible to customers.',
			'woocommerce-payments'
		) . ' <a href="' . self::TASK_DOCUMENTATION_URL . '" target="_blank">' . __( 'Read more', 'woocommerce-payments' ) . '</a>';
	}

	/**
	 * Gets the task's action label.
	 *
	 * @return string
	 */
	public function get_action_label() {
		return '';
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
					'page' => 'wc-settings',
					'tab'  => 'advanced',
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
	 * Gets the task content.
	 *
	 * @return string
	 */
	public function get_content() {
		return '';
	}

	/**
	 * Get whether the task is completed.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return $this->has_woocommerce_terms_and_conditions_page_id();
	}

	/**
	 * Get whether the task is visible.
	 *
	 * @return bool
	 */
	public function can_view() {
		return true;
	}

	/**
	 * Returns whether the task is dismissable.
	 *
	 * @return bool
	 */
	public function is_dismissable() {
		return false;
	}

	/**
	 * Returns whether the merchant has "Terms and conditions" page set up.
	 *
	 * @return bool
	 */
	private function has_woocommerce_terms_and_conditions_page_id() {
		$terms_and_conditions_page_id = get_option( 'woocommerce_terms_page_id', 0 );
		return $terms_and_conditions_page_id > 0;
	}
}
