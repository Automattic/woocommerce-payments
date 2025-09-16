<?php
/**
 * QIT Dev Tools command - Foundation (PR 1)
 *
 * @package WooCommerce\Payments
 */

use WCPay\Database_Cache;

/**
 * QIT development tools for WooCommerce Payments E2E testing.
 * Provides Jetpack connection setup for QIT environments.
 */
class WP_CLI_QIT_Dev_Command {
	/**
	 * Sets up a WooPayments connection for QIT testing.
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
	 *     wp woopayments qit_setup 248403234 --blog_token=abc123 --user_token=def456
	 *
	 * @param array $args Positional arguments passed to the command.
	 * @param array $assoc_args Associative arguments passed to the command.
	 */
	public function qit_setup( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) || ! is_numeric( $args[0] ) ) {
			\WP_CLI::error( 'Please provide a numeric blog ID.' );
		}

		if ( ! class_exists( 'Jetpack_Options' ) ) {
			\WP_CLI::error( 'Jetpack_Options class does not exist. Ensure Jetpack is installed and active.' );
		}

		$blog_id    = (int) $args[0];
		$blog_token = isset( $assoc_args['blog_token'] ) ? (string) $assoc_args['blog_token'] : '123.ABC.QIT';
		$user_token = isset( $assoc_args['user_token'] ) ? (string) $assoc_args['user_token'] : '123.ABC.QIT.1';

		// Set up Jetpack connection.
		$this->setup_jetpack_connection( $blog_id, $blog_token, $user_token );

		// Refresh account data to get real account info from server.
		if ( class_exists( 'WC_Payments' ) ) {
			$this->refresh_account_data();
		}

		\WP_CLI::success( "QIT WCPay setup completed for blog ID {$blog_id}" );
		\WP_CLI::line( 'Account data will be fetched from server based on Jetpack connection.' );
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
	 * Refreshes WCPay account data from the server.
	 */
	private function refresh_account_data(): void {
		if ( ! class_exists( 'WC_Payments' ) ) {
			\WP_CLI::log( 'WC_Payments not available - skipping account refresh' );
			return;
		}

		try {
			\WC_Payments::get_account_service()->refresh_account_data();
			\WP_CLI::log( 'Account data refreshed from server' );
		} catch ( \Exception $e ) {
			\WP_CLI::log( 'Account refresh failed (expected in local dev): ' . $e->getMessage() );
		}
	}

	/**
	 * Shows QIT WCPay connection status for debugging.
	 *
	 * @when after_wp_load
	 */
	public function qit_status(): void {
		\WP_CLI::line( '=== QIT WCPay Connection Status ===' );

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

		\WP_CLI::line( 'Dev Mode: ' . ( get_option( 'wcpay_dev_mode' ) ? 'Enabled' : 'Disabled' ) );
	}
}
