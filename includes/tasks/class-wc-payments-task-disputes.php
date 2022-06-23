<?php
/**
 * Class WC_Payments_Task_Disputes
 *
 * @package WooCommerce\Payments\Tasks
 */

namespace WooCommerce\Payments\Tasks;

use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Task;


defined( 'ABSPATH' ) || exit;

/**
 * WC Onboarding Task displayed if disputes awaiting response.
 */
class WC_Payments_Task_Disputes extends Task {
	/**
	 * ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'woocommerce_payments_disputes_task';
	}


	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'You have _ disputed payments totalling ___', 'woocommerce-payments' );
	}

	/**
	 * Content.
	 *
	 * @return string
	 */
	public function get_content() {
		return __(
			'You can review and challenge the disputes by ___',
			'woocommerce-payments'
		);
	}

	/**
	 * Action label.
	 *
	 * @return string
	 */
	public function get_action_label() {
		return __( 'Disputes', 'woocommerce-payments' );
	}

	/**
	 * Action URL.
	 *
	 * @return string
	 */
	public function get_action_url() {
		return '/';
	}

	/**
	 * Time.
	 *
	 * @return string
	 */
	public function get_time() {
		return __( '2 minutes', 'woocommerce-payments' );
	}

	/**
	 * Task completion.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return false;
	}

	/**
	 * Task visibility.
	 *
	 * @return bool
	 */
	public function can_view() {
		return true;
	}
}
