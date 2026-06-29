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
	 * Holds the onboarding test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode_onboarding;

	/**
	 * Holds the dev mode flag.
	 *
	 * @var bool
	 */
	private $dev_mode;

	/**
	 * Holds the triggers that activated dev mode.
	 *
	 * @var string[]
	 */
	private $dev_mode_triggers = [];

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
	 */
	private function maybe_init() {
		// The object is only initialized once.
		if ( isset( $this->dev_mode ) && isset( $this->test_mode_onboarding ) && isset( $this->test_mode ) ) {
			return;
		}

		$triggers = [];

		// Plugin-specific dev mode.
		if ( $this->is_wcpay_dev_mode_defined() ) {
			$triggers[] = 'WCPAY_DEV_MODE';
		}

		// WordPress Dev Environment.
		$env_type = $this->get_wp_environment_type();
		if ( in_array( $env_type, self::DEV_MODE_ENVIRONMENTS, true ) ) {
			$triggers[] = 'WP_ENVIRONMENT_TYPE=' . $env_type;
		}

		// WordPress Development mode. If any development mode is enabled, we'll fall back to dev as well.
		$dev_mode_setting = $this->wp_get_development_mode();
		if ( '' !== $dev_mode_setting ) {
			$triggers[] = 'WP_DEVELOPMENT_MODE=' . $dev_mode_setting;
		}

		$dev_mode = ! empty( $triggers );

		/**
		 * Allows WooPayments to enter dev (aka sandbox) mode.
		 *
		 * @since 0.8.2
		 *
		 * @see https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/
		 * @param bool $dev_mode Whether to enter WooPayments in dev mode.
		 */
		$this->dev_mode = (bool) apply_filters( 'wcpay_dev_mode', $dev_mode );

		// Reconcile triggers with the post-filter result.
		if ( $this->dev_mode && ! $dev_mode ) {
			// The filter turned dev mode on with no underlying conditions.
			$triggers[] = 'wcpay_dev_mode filter';
		} elseif ( ! $this->dev_mode ) {
			// Dev mode is off (filter may have suppressed it); no triggers apply.
			$triggers = [];
		}
		$this->dev_mode_triggers = $triggers;

		// If dev mode is active, we enable test mode onboarding.
		$test_mode_onboarding = $this->dev_mode || \WC_Payments_Onboarding_Service::is_test_mode_enabled();

		/**
		 * Allows WooPayments to use test mode onboarding.
		 *
		 * @since 8.1.0
		 *
		 * @param bool $test_mode_onboarding Whether to use test mode onboarding.
		 */
		$this->test_mode_onboarding = (bool) apply_filters( 'wcpay_test_mode_onboarding', $test_mode_onboarding );

		// If the current mode of onboarding is test, we will enable test mode payments processing.
		// Otherwise, follow the gateway settings.
		if ( $this->test_mode_onboarding ) {
			$test_mode = true;
		} else {
			// Getting the gateway settings directly from the database so the gateway doesn't need to be initialized.
			$settings_option_name = 'woocommerce_' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '_settings';
			$wcpay_settings       = get_option( $settings_option_name );
			$test_mode            = 'yes' === ( $wcpay_settings['test_mode'] ?? false );
		}

		/**
		 * Allows WooPayments to process payments in test mode.
		 *
		 * @since 4.2.0
		 *
		 * @see https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/#enabling-test-mode
		 * @param bool $test_mode Whether to process payments in test mode.
		 */
		$this->test_mode = (bool) apply_filters( 'wcpay_test_mode', $test_mode );
	}

	/**
	 * Checks if live payment processing is enabled.
	 *
	 * @return bool
	 * @throws Exception In case the class has not been initialized yet.
	 */
	public function is_live(): bool {
		$this->maybe_init();

		return ! $this->test_mode;
	}

	/**
	 * Checks if test payments processing is enabled.
	 *
	 * @return bool
	 * @throws Exception In case the class has not been initialized yet.
	 */
	public function is_test(): bool {
		$this->maybe_init();

		return $this->test_mode;
	}

	/**
	 * Checks if test mode onboarding is enabled.
	 *
	 * @return bool
	 * @throws Exception In case the class has not been initialized yet.
	 */
	public function is_test_mode_onboarding(): bool {
		$this->maybe_init();

		return $this->test_mode_onboarding;
	}

	/**
	 * Checks if dev mode is enabled.
	 *
	 * @return bool
	 * @throws Exception In case the class has not been initialized yet.
	 */
	public function is_dev(): bool {
		$this->maybe_init();

		return $this->dev_mode;
	}

	/**
	 * Returns the triggers that activated dev mode.
	 *
	 * Returns an empty array when dev mode is not active.
	 *
	 * @return string[]
	 */
	public function get_dev_mode_triggers(): array {
		$this->maybe_init();
		return $this->dev_mode_triggers;
	}

	/**
	 * Enable live payment processing.
	 *
	 * @return void
	 */
	public function live() {
		$this->test_mode = false;
		// We can't process live payments and be in test mode onboarding.
		$this->test_mode_onboarding = false;
		// We also can't be in dev mode.
		$this->dev_mode          = false;
		$this->dev_mode_triggers = [];
	}

	/**
	 * Enable test payment processing.
	 *
	 * @return void
	 */
	public function test() {
		$this->test_mode = true;
		// Doesn't affect the onboarding mode or the dev mode.
	}

	/**
	 * Enable test mode onboarding.
	 *
	 * @return void
	 */
	public function test_mode_onboarding() {
		$this->test_mode_onboarding = true;
		// When onboarding in test mode, we can only do test payment processing.
		$this->test_mode = true;
	}

	/**
	 * Enable live mode onboarding.
	 *
	 * @return void
	 */
	public function live_mode_onboarding() {
		$this->test_mode_onboarding = false;
		// When onboarding in live mode, we can't be in dev mode.
		$this->dev_mode          = false;
		$this->dev_mode_triggers = [];
		// Doesn't affect the payments processing mode.
	}

	/**
	 * Enable the gateway dev mode.
	 *
	 * Payments processing and onboarding are always in test mode when dev mode is active.
	 *
	 * @return void
	 */
	public function dev() {
		$this->dev_mode          = true;
		$this->dev_mode_triggers = [ 'manual' ];
		// In dev mode, everything is in test mode.
		$this->test_mode            = true;
		$this->test_mode_onboarding = true;
	}

	/**
	 * Checks if the gateway is forced into dev mode through a constant.
	 *
	 * @return bool Whether `WCPAY_DEV_MODE` is defined and true.
	 */
	protected function is_wcpay_dev_mode_defined(): bool {
		return (
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

	/**
	 * Returns the WordPress development mode setting.
	 *
	 * @return string
	 */
	protected function wp_get_development_mode(): string {
		return function_exists( 'wp_get_development_mode' )
			? wp_get_development_mode()
			: ''; // Support for older WordPress versions.
	}
}
