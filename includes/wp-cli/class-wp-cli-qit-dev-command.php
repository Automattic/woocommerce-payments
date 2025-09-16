<?php
/**
 * QIT Dev Tools command - Foundation (PR 1)
 *
 * @package WooCommerce\Payments
 */

use WCPay\Database_Cache;

/**
 * QIT development tools for WooCommerce Payments E2E testing.
 * Provides basic Jetpack connection setup for QIT environments.
 */
class WP_CLI_QIT_Dev_Command {
	/**
	 * Sets up a basic WCPay connection for QIT testing with mock Stripe data.
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
	 * [--force_connected]
	 * : Force the plugin to appear connected even if API calls fail.
	 *
	 * ## EXAMPLES
	 *     wp woopayments qit_setup 248403234 --blog_token=abc123 --user_token=def456
	 *
	 * @when after_wp_load
	 */
	public function qit_setup( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) || ! is_numeric( $args[0] ) ) {
			\WP_CLI::error( 'Please provide a numeric blog ID.' );
		}

		if ( ! class_exists( 'Jetpack_Options' ) ) {
			\WP_CLI::error( 'Jetpack_Options class does not exist. Ensure Jetpack is installed and active.' );
		}

		$blog_id         = (int) $args[0];
		$blog_token      = isset( $assoc_args['blog_token'] ) ? (string) $assoc_args['blog_token'] : '123.ABC.QIT';
		$user_token      = isset( $assoc_args['user_token'] ) ? (string) $assoc_args['user_token'] : '123.ABC.QIT.1';
		$force_connected = isset( $assoc_args['force_connected'] );

		// Set up Jetpack connection
		$this->setup_jetpack_connection( $blog_id, $blog_token, $user_token );

		// Set up mock account data if WCPay is available
		if ( class_exists( 'WC_Payments' ) ) {
			$this->setup_mock_account_data( $force_connected );
		}

		// Configure environment for testing
		$this->configure_test_environment();

		\WP_CLI::success( "QIT WCPay setup completed for blog ID {$blog_id}" );
		\WP_CLI::line( "Using mock Stripe data (basic connectivity only)" );
	}

	private function setup_jetpack_connection( int $blog_id, string $blog_token, string $user_token ): void {
		$user_tokens = array( 1 => $user_token );

		Jetpack_Options::update_option( 'id', $blog_id );
		Jetpack_Options::update_option( 'master_user', 1 );
		Jetpack_Options::update_option( 'blog_token', $blog_token );
		Jetpack_Options::update_option( 'user_tokens', $user_tokens );

		\WP_CLI::log( "Jetpack connection configured for blog ID {$blog_id}" );
	}

	private function setup_mock_account_data( bool $force_connected ): void {
		$database_cache = \WC_Payments::get_database_cache();

		if ( ! $database_cache ) {
			update_option( 'wcpay_qit_account_connected', true );
			\WP_CLI::log( 'Mock account data configured (fallback mode)' );
			return;
		}

		$mock_account = array(
'account_id'           => 'acct_QIT123MockAccount',
			'test_publishable_key' => 'pk_test_QITMockPublishableKey',
			'is_live'              => false,
			'country'              => 'US',
			'default_currency'     => 'USD',
			'charges_enabled'      => true,
			'status'               => 'complete',
		);

		$database_cache->add( Database_Cache::ACCOUNT_KEY, $mock_account );
		if ( $force_connected ) {
			$database_cache->add( 'wcpay_force_connected', true );
		}

		\WP_CLI::log( 'Mock account data configured' );
	}

	private function configure_test_environment(): void {
		update_option( 'wcpay_dev_mode', true );
		update_option( 'wcpay_should_redirect_to_onboarding', false );
		\WP_CLI::log( 'Test environment configured' );
	}

	/**
	 * Shows QIT WCPay connection status for debugging.
	 *
	 * @when after_wp_load
	 */
	public function qit_status(): void {
		\WP_CLI::line( "=== QIT WCPay Connection Status ===" );

		if ( class_exists( 'Jetpack_Options' ) ) {
			$blog_id = Jetpack_Options::get_option( 'id' );
			\WP_CLI::line( "Blog ID: " . ( $blog_id ? $blog_id : "Not Set" ) );
		}

		if ( class_exists( 'WC_Payments' ) ) {
			$database_cache = \WC_Payments::get_database_cache();
			if ( $database_cache ) {
				$account_data = $database_cache->get( Database_Cache::ACCOUNT_KEY );
				\WP_CLI::line( "Account Data: " . ( $account_data ? "Present" : "Not Set" ) );
			}
		}

		\WP_CLI::line( "Dev Mode: " . ( get_option( 'wcpay_dev_mode' ) ? "Enabled" : "Disabled" ) );
	}
}
