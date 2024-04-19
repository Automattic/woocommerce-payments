<?php
/**
 * Class file for WCPay\Core\Mode.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core;

use WC_Payment_Gateway_WCPay;
use Exception;

/**
 * Controls the working mode of WooPayments.
 */
class Mode {
	/**
	 * Holds the test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Holds the dev mode flag.
	 *
	 * @var bool
	 */
	private $dev_mode;

	/**
	 * Indicates the WCPay version which introduced the class.
	 *
	 * @var string
	 */
	const AVAILABLE_SINCE = '5.0.0';

	/**
	 * Environment types, which are used to automatically enter dev mode.
	 *
	 * @see wp_get_environment_type()
	 * @see https://developer.wordpress.org/reference/functions/wp_get_environment_type/#description
	 */
	const DEV_MODE_ENVIRONMENTS = [
		'development',
		'staging',
	];

	/**
	 * Initializes the working mode of WooPayments.
	 *
	 * @throws Exception In case the class has not been initialized yet.
	 */
	private function maybe_init() {
		// The object is only initialized once.
		if ( isset( $this->dev_mode ) && isset( $this->test_mode ) ) {
			return;
		}

		$dev_mode = (
			// Plugin-specific dev mode.
			$this->is_wcpay_dev_mode_defined()

			// WordPress Dev Environment.
			|| in_array( $this->get_wp_environment_type(), self::DEV_MODE_ENVIRONMENTS, true )
		);

		/**
		 * Allows WooPayments to enter dev (aka sandbox) mode.
		 *
		 * @see https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/
		 * @param bool $dev_mode The pre-determined dev mode.
		 */
		$this->dev_mode = (bool) apply_filters( 'wcpay_dev_mode', $dev_mode );

		// Getting the gateway settings directly from the database so the gateway doesn't need to be initialized.
		$settings_option_name = 'woocommerce_' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '_settings';
		$wcpay_settings       = get_option( $settings_option_name );
		$test_mode_setting    = 'yes' === ( $wcpay_settings['test_mode'] ?? false );
		$test_mode            = $this->dev_mode || $test_mode_setting;

		/**
		 * Allows WooPayments to enter test mode.
		 *
		 * @see https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#enabling-test-mode
		 * @param bool $test_mode The pre-determined test mode.
		 */
		$this->test_mode = (bool) apply_filters( 'wcpay_test_mode', $test_mode );
	}

	/**
	 * Checks if live is enabled.
	 *
	 * @throws Exception In case the class has not been initialized yet.
	 * @return bool
	 */
	public function is_live(): bool {
		$this->maybe_init();
		return ! $this->test_mode && ! $this->dev_mode;
	}

	/**
	 * Checks if test is enabled.
	 *
	 * @throws Exception In case the class has not been initialized yet.
	 * @return bool
	 */
	public function is_test(): bool {
		$this->maybe_init();

		return $this->test_mode;
	}

	/**
	 * Checks if dev is enabled.
	 *
	 * @throws Exception In case the class has not been initialized yet.
	 * @return bool
	 */
	public function is_dev(): bool {
		$this->maybe_init();
		return $this->dev_mode;
	}

	/**
	 * Enters into live mode.
	 *
	 * @return void
	 */
	public function live() {
		$this->test_mode = false;
		$this->dev_mode  = false;
	}

	/**
	 * Enters into test mode.
	 *
	 * @return void
	 */
	public function test() {
		$this->test_mode = true;
		$this->dev_mode  = false;
	}

	/**
	 * Enters into dev mode.
	 *
	 * @return void
	 */
	public function dev() {
		$this->test_mode = true;
		$this->dev_mode  = true;
	}

	/**
	 * Checks if the gateway is forced into dev mode through a constant.
	 *
	 * @return bool Whether `WCPAY_DEV_MODE` is defined and true.
	 */
	protected function is_wcpay_dev_mode_defined(): bool {
		return(
			defined( 'WCPAY_DEV_MODE' )
			&& WCPAY_DEV_MODE
		);
	}

	/**
	 * Returns the current WP environment type.
	 *
	 * @return string|null
	 */
	protected function get_wp_environment_type() {
		return function_exists( 'wp_get_environment_type' )
			? wp_get_environment_type()
			: null;
	}
}
