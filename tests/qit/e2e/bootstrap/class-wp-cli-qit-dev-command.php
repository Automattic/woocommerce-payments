<?php
/**
 * QIT Dev Tools command
 *
 * @package WooCommerce\Payments
 */

use WCPay\Database_Cache;

/**
 * QIT development tools for WooPayments E2E testing.
 * Provides Jetpack connection setup for QIT environments.
 */
class WP_CLI_QIT_Dev_Command {
	/**
	 * Establishes Jetpack connection for WooPayments QIT testing.
	 *
	 * ## OPTIONS
	 *
	 * <blog_id>
	 * : Numeric blog ID from WordPress.com.
	 *
	 * [--blog_token=<value>]
	 * : Jetpack blog token.
	 *
	 * [--user_token=<value>]
	 * : Jetpack user token.
	 *
	 * ## EXAMPLES
	 *     wp woopayments qit_jetpack_connection 248403234 --blog_token=abc123 --user_token=def456
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function qit_jetpack_connection( array $args, array $assoc_args ): void {
		// Safety check: Only allow in local/development environments.
		$environment_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( 'local' !== $environment_type && 'development' !== $environment_type ) {
			\WP_CLI::error( 'This command can only be run in local or development environments for safety.' );
		}

		if ( empty( $args[0] ) || ! is_numeric( $args[0] ) ) {
			\WP_CLI::error( 'Please provide a numeric blog ID.' );
		}

		if ( ! class_exists( 'Jetpack_Options' ) ) {
			\WP_CLI::error( 'Jetpack_Options class does not exist. Ensure Jetpack is installed and active.' );
		}

		$blog_id    = (int) $args[0];
		$blog_token = isset( $assoc_args['blog_token'] ) ? (string) $assoc_args['blog_token'] : '123.ABC.QIT';
		$user_token = isset( $assoc_args['user_token'] ) ? (string) $assoc_args['user_token'] : '123.ABC.QIT.1';

		// Force test mode BEFORE any other operations (since this is a test account).
		$this->force_test_mode();

		// Set up Jetpack connection.
		$this->setup_jetpack_connection( $blog_id, $blog_token, $user_token );

		// Enable dev mode (like WCP Dev Tools plugin does).
		$this->enable_dev_mode();

		// Refresh account data to get real account info from server (like regular E2E tests).
		if ( class_exists( 'WC_Payments' ) ) {
			$this->refresh_account_data();
		}

		\WP_CLI::success( "Jetpack connection established for blog ID {$blog_id}" );
		\WP_CLI::line( 'Account data fetched from server based on Jetpack connection' );
	}

	/**
	 * Shows Jetpack connection status for WooPayments QIT testing.
	 *
	 * @when after_wp_load
	 */
	public function qit_jetpack_status(): void {
		// Safety check: Only allow in local/development environments.
		$environment_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( 'local' !== $environment_type && 'development' !== $environment_type ) {
			\WP_CLI::error( 'This command can only be run in local or development environments for safety.' );
		}

		\WP_CLI::line( '=== QIT Jetpack Connection Status ===' );

		if ( class_exists( 'Jetpack_Options' ) ) {
			$blog_id = Jetpack_Options::get_option( 'id' );
			\WP_CLI::line( 'Blog ID: ' . ( $blog_id ? $blog_id : 'Not Set' ) );
		}

		if ( class_exists( 'WC_Payments' ) ) {
			$database_cache = \WC_Payments::get_database_cache();
			if ( $database_cache ) {
				$account_data = $database_cache->get( Database_Cache::ACCOUNT_KEY );
				\WP_CLI::line( 'Account Data: ' . ( $account_data ? 'Present' : 'Not Set' ) );
			}
		}

		\WP_CLI::line( 'Dev Mode: ' . ( get_option( 'wcpaydev_dev_mode' ) ? 'Enabled' : 'Disabled' ) );
	}

	/**
	 * Configures Jetpack connection options.
	 *
	 * @param int    $blog_id    WordPress.com blog ID.
	 * @param string $blog_token Jetpack blog token.
	 * @param string $user_token Jetpack user token.
	 */
	private function setup_jetpack_connection( int $blog_id, string $blog_token, string $user_token ): void {
		$user_tokens = [ 1 => $user_token ];

		Jetpack_Options::update_option( 'id', $blog_id );
		Jetpack_Options::update_option( 'master_user', 1 );
		Jetpack_Options::update_option( 'blog_token', $blog_token );
		Jetpack_Options::update_option( 'user_tokens', $user_tokens );

		\WP_CLI::log( "Jetpack connection configured for blog ID {$blog_id}" );
	}

	/**
	 * Enables WCP development mode like the WCP Dev Tools plugin.
	 */
	private function enable_dev_mode(): void {
		// Enable dev mode like WCP Dev Tools plugin does.
		update_option( 'wcpaydev_dev_mode', '1' );

		// Add the dev mode filter like WCP Dev Tools plugin does.
		add_filter( 'wcpay_dev_mode', '__return_true' );

		\WP_CLI::log( 'Enabled WCPay dev mode with filter' );
	}

	/**
	 * Forces WCP test mode by setting filters and gateway settings.
	 *
	 * DEFENSE IN DEPTH STRATEGY:
	 * This method uses multiple independent mechanisms to ensure test mode is active.
	 * While WP_ENVIRONMENT_TYPE=development automatically enables dev mode (see WCPay\Core\Mode),
	 * we explicitly set test mode through multiple layers for maximum safety:
	 *
	 * 1. WordPress filters - Override mode detection at runtime
	 * 2. Gateway settings - Persist test mode in database
	 * 3. Onboarding service - Set test mode at service layer
	 *
	 * This redundancy protects against:
	 * - Changes to Mode class logic
	 * - Filter overrides by other code
	 * - Environment variable changes
	 * - Accidental live mode activation
	 *
	 * All mechanisms must fail for live mode to activate - acceptable tradeoff for test safety.
	 */
	private function force_test_mode(): void {
		// Force test mode onboarding and test mode since we're using a test account.
		add_filter( 'wcpay_test_mode_onboarding', '__return_true' );
		add_filter( 'wcpay_test_mode', '__return_true' );

		// Also try setting the gateway settings to enable test mode.
		$gateway_settings              = get_option( 'woocommerce_woocommerce_payments_settings', [] );
		$gateway_settings['test_mode'] = 'yes';
		update_option( 'woocommerce_woocommerce_payments_settings', $gateway_settings );

		// CRITICAL: Use WC_Payments_Onboarding_Service to set test mode (this sets test_mode_onboarding).
		if ( class_exists( 'WC_Payments_Onboarding_Service' ) ) {
			\WC_Payments_Onboarding_Service::set_test_mode( true );
			\WP_CLI::log( 'Set WC_Payments_Onboarding_Service test mode - this enables test_mode_onboarding' );
		}

		\WP_CLI::log( 'Forced WCPay test mode for test account (filters + gateway settings + onboarding service)' );
	}

	/**
	 * Refreshes account data from the WCP server and validates the connection.
	 */
	private function refresh_account_data(): void {
		if ( ! class_exists( 'WC_Payments' ) ) {
			\WP_CLI::log( 'WC_Payments not available - skipping account refresh' );
			return;
		}

		try {
			$account_service = \WC_Payments::get_account_service();
			\WP_CLI::log( 'Attempting to refresh account data...' );

			$result = $account_service->refresh_account_data();

			// Check if data was actually set.
			$database_cache = \WC_Payments::get_database_cache();
			$account_data   = $database_cache ? $database_cache->get( Database_Cache::ACCOUNT_KEY ) : null;

			if ( $account_data ) {
				\WP_CLI::log( 'Account data refreshed successfully from server' );
				// Verify key fields exist without exposing sensitive data.
				$has_account_id = isset( $account_data['account_id'] ) && ! empty( $account_data['account_id'] );
				$has_keys       = isset( $account_data['live_publishable_key'] ) || isset( $account_data['test_publishable_key'] );
				$status         = $account_data['status'] ?? 'unknown';
				\WP_CLI::log( 'Account validation: ID=' . ( $has_account_id ? 'present' : 'missing' ) . ', Keys=' . ( $has_keys ? 'present' : 'missing' ) . ', Status=' . $status );
			} else {
				\WP_CLI::warning( 'Account refresh completed but no account data cached - connection may be invalid' );
			}
		} catch ( \Exception $e ) {
			\WP_CLI::warning( 'Account refresh failed: ' . $e->getMessage() );
		}
	}
}
