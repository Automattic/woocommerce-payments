<?php
/**
 * Class WooPay_Scheduler.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WC_Payment_Gateway_WCPay;
use WCPay\Logger;

/**
 * This class adds a cron job that runs daily to check if is there any extensions incompatible  with WooPay active.
 * If one is found WooPay is disabled.
 */
class WooPay_Scheduler {

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * WC_REST_Payments_Settings_Controller constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway WC_Payment_Gateway_WCPay instance.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $wcpay_gateway ) {
		$this->wcpay_gateway = $wcpay_gateway;
	}

	/**
	 * Init the hooks.
	 */
	public function init() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( 'validate_incompatible_extensions', [ $this, 'disable_woopay_if_incompatible_extension_active' ] );

		register_deactivation_hook( WCPAY_PLUGIN_FILE, [ $this, 'remove_scheduler' ] );
	}

	/**
	 * Disables the scheduler when the plugin is disabled.
	 */
	public function remove_scheduler() {
		wp_clear_scheduled_hook( 'validate_incompatible_extensions' );
	}

	/**
	 * Starts the cron job.
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( 'validate_incompatible_extensions' ) ) {
			wp_schedule_event( time(), 'daily', 'validate_incompatible_extensions' );
		}
	}

	/**
	 * Disables WooPay if an incompatible extension is active
	 */
	public function disable_woopay_if_incompatible_extension_active() {
		try {
			$incompatible_extensions = $this->get_incompatible_extensions();

			$active_plugins = get_option( 'active_plugins' );
			delete_option( 'woopay_disabled_invalid_extensions' );

			if ( ! empty( $active_plugins ) && is_array( $active_plugins ) ) {
				foreach ( $active_plugins as $plugin ) {
					$plugin = $this->format_extension_name( $plugin );

					if ( in_array( $plugin, $incompatible_extensions, true ) ) {
						$this->wcpay_gateway->update_is_woopay_enabled( false );
						update_option( 'woopay_disabled_invalid_extensions', true );
					}
				}
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay incompatible extensions list. ' . $e );
		}
	}

	/**
	 * Removes the folder and file extension from the plugin name.
	 *
	 * @param string $plugin the plugin main file name.
	 * @return string the plugin name.
	 */
	private function format_extension_name( $plugin ) {
		$plugin = explode( '/', $plugin );
		$plugin = end( $plugin );
		return str_replace( '.php', '', $plugin );
	}

	/**
	 * Get the list of extensions that are known to be incompatible with WooPay.
	 *
	 * @return array
	 */
	private function get_incompatible_extensions() {
		$args = [
			'url'     => WooPay_Utilities::get_woopay_rest_url( 'incompatible-extensions' ),
			'method'  => 'GET',
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		/**
		 * Suppress psalm error from Jetpack Connection namespacing WP_Error.
		 *
		 * @psalm-suppress UndefinedDocblockClass
		 */
		$response      = \Automattic\Jetpack\Connection\Client::remote_request( $args );
		$response_body = wp_remote_retrieve_body( $response );

		// phpcs:ignore
		/**
		 * @psalm-suppress UndefinedDocblockClass
		 */
		if ( is_wp_error( $response ) || ! is_array( $response ) || ( ! empty( $response['code'] ) && ( $response['code'] >= 300 || $response['code'] < 200 ) ) ) {
			Logger::error( 'HTTP_REQUEST_ERROR ' . var_export( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			return;
		}

		$json = json_decode( $response_body, true );

		return isset( $json['incompatible_extensions'] ) ? $json['incompatible_extensions'] : [];
	}
}
