<?php
/**
 * Class WooPay_Scheduler.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WCPay\Logger;

/**
 * This class adds a cron job that runs daily to check if is there any extensions incompatible with WooPay active,
 * if one is found WooPay is disabled, also updates the list of extensions adapted to work with WooPay and the
 * available countries.
 */
class WooPay_Scheduler {

	/**
	 * Init the hooks.
	 */
	public function init() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( 'validate_woopay_compatibility', [ $this, 'validate_woopay_compatibility' ] );

		register_deactivation_hook( WCPAY_PLUGIN_FILE, [ $this, 'remove_scheduler' ] );
	}

	/**
	 * Disables the scheduler when the plugin is disabled.
	 */
	public function remove_scheduler() {
		wp_clear_scheduled_hook( 'validate_woopay_compatibility' );
	}

	/**
	 * Starts the cron job.
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( 'validate_woopay_compatibility' ) ) {
			wp_schedule_event( time(), 'daily', 'validate_woopay_compatibility' );
		}
	}

	/**
	 * Disables WooPay if an incompatible extension is active
	 */
	public function validate_woopay_compatibility() {
		try {
			$compatibility = $this->get_compatibility();

			$active_plugins    = get_option( 'active_plugins' );
			$formatted_plugins = [];

			if ( ! empty( $active_plugins ) && is_array( $active_plugins ) ) {
				foreach ( $active_plugins as $plugin ) {
					$plugin = $this->format_extension_name( $plugin );

					$formatted_plugins[] = $plugin;
				}
			}

			$this->disable_woopay_if_incompatible_extension_active( $formatted_plugins, $compatibility['incompatible_extensions'] );
			$this->update_has_adapted_extensions( $formatted_plugins, $compatibility['adapted_extensions'] );
			$this->update_available_countries( $compatibility['available_countries'] );
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay incompatible extensions list. ' . $e );
		}
	}

	/**
	 * Disables WooPay if an incompatible extension is active
	 *
	 * @param array $plugins The active plugins with formatted name.
	 * @param array $incompatible_extensions The incompatible extensions list.
	 */
	public function disable_woopay_if_incompatible_extension_active( $plugins, $incompatible_extensions ) {
		delete_option( 'woopay_disabled_invalid_extensions' );

		foreach ( $incompatible_extensions as $incompatible_extension ) {
			if ( in_array( $incompatible_extension, $plugins, true ) ) {
				update_option( 'woopay_disabled_invalid_extensions', true );
				break;
			}
		}
	}

	/**
	 * Set if it has a WooPay adapted extension activated.
	 *
	 * @param array $plugins The active plugins with formatted name.
	 * @param array $adapted_extensions The adapted extensions list.
	 */
	public function update_has_adapted_extensions( $plugins, $adapted_extensions ) {
		delete_option( 'woopay_has_adapted_extensions' );

		foreach ( $adapted_extensions as $adapted_extension ) {
			if ( in_array( $adapted_extension, $plugins, true ) ) {
				update_option( 'woopay_has_adapted_extensions', true );
				break;
			}
		}
	}

	/**
	 * Update the available countries list.
	 *
	 * @param array $available_countries The available countries list.
	 */
	public function update_available_countries( $available_countries ) {
		try {
			if ( is_array( $available_countries ) ) {
				update_option( WooPay_Utilities::AVAILABLE_COUNTRIES_KEY, wp_json_encode( $available_countries ) );
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay available countries. ' . $e );
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
	 * Get the list of extensions that are known to be incompatible with WooPay, adapted ones and available countries.
	 *
	 * @return array
	 */
	public function get_compatibility() {
		$args = [
			'url'     => WooPay_Utilities::get_woopay_rest_url( 'compatibility' ),
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

		return $json;
	}
}
