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
 * Controls the working mode of WooCommerce Payments.
 *
 * @property-read bool $live  Flag for live mode.
 * @property-read bool $dev   Flag for dev mode.
 * @property-read bool $test  Flag for test mode.
 * @method        void live() Enters live mode. Should only be used for tests.
 * @method        void dev()  Enters live mode. Should only be used for tests.
 * @method        void test() Enters live mode. Should only be used for tests.
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
	 * Holds the gateway class for settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

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
	 * Stores the gateway for later retrieval of options.
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway The active gateway.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Initializes the working mode of WooCommerce Payments.
	 */
	private function init() {
		$dev_mode = (
			// Plugin-specific dev mode.
			$this->is_wcpay_dev_mode_defined()

			// WordPress Dev Environment.
			|| in_array( $this->get_wp_environment_type(), self::DEV_MODE_ENVIRONMENTS, true )
		);

		/**
		 * Allows WooCommerce to enter dev mode.
		 *
		 * @see https://woocommerce.com/document/payments/testing/dev-mode/
		 * @param bool $dev_mode The pre-determined dev mode.
		 */
		$this->dev_mode = (bool) apply_filters( 'wcpay_dev_mode', $dev_mode );

		$test_mode_setting = 'yes' === $this->gateway->get_option( 'test_mode' );
		$test_mode         = $this->dev_mode || $test_mode_setting;

		/**
		 * Allows WooCommerce to enter test mode.
		 *
		 * @see https://woocommerce.com/document/payments/testing/
		 * @param bool $test_mode The pre-determined test mode.
		 */
		$this->test_mode = (bool) apply_filters( 'wcpay_test_mode', $test_mode );
	}

	/**
	 * Checks whether WCPay is in the given mode.
	 *
	 * @param  string $property The mode to check for. Either `live`, `test`, or `dev`.
	 * @return bool If the propery is toggled.
	 *
	 * @throws Exception In case the class has not been initialized yet.
	 */
	public function __get( $property ) {
		if ( ! isset( $this->dev_mode ) || ! isset( $this->test_mode ) ) {
			if ( isset( $this->gateway ) && ! empty( $this->gateway->settings ) ) {
				$this->init();
			} else {
				throw new Exception( 'WooCommerce Payments\' working mode is not initialized yet. Wait for the `init` action.' );
			}
		}

		if ( 'live' === $property ) {
			return ! $this->test_mode && ! $this->dev_mode;
		} else {
			$prop = $property . '_mode';
			if ( property_exists( $this, $prop ) ) {
				return $this->$prop;
			}
		}
	}

	/**
	 * Forces a switch of the current mode.
	 * Only recommended when testing, use filters otherwise.
	 *
	 * @param string $name The name of the mode to enter.
	 * @param array  $args All args for the method (not used).
	 * @return void
	 */
	public function __call( $name, $args ) {
		// Only simulate the possible modes.
		if ( ! in_array( $name, [ 'live', 'test', 'dev' ], true ) ) {
			return;
		}

		if ( ! defined( 'WCPAY_TEST_ENV' ) || ! WCPAY_TEST_ENV ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			trigger_error(
				'Toggling test/dev mode for WooCommerce Payments when not running tests is strongly discouraged.',
				E_USER_DEPRECATED
			);
		}

		$this->test_mode = 'dev' === $name || 'test' === $name;
		$this->dev_mode  = 'dev' === $name;
	}

	/**
	 * Checks if the gateway is forced into dev mode through a constant.
	 *
	 * @return bool Whether `WCPAY_DEV_MODE` is defined and true.
	 */
	protected function is_wcpay_dev_mode_defined() : bool {
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
