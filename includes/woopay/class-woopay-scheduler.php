<?php
/**
 * Class WooPay_Scheduler.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use WC_Payments_API_Client;
use WCPay\Logger;

/**
 * This class adds a cron job that runs daily to check if is there any extensions incompatible  with WooPay active.
 * If one is found WooPay is disabled.
 */
class WooPay_Scheduler {

	const INVALID_EXTENSIONS_FOUND_OPTION_NAME     = 'woopay_invalid_extension_found';
	const INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME = 'woopay_incompatible_extensions';

	/**
	 * WC_Payments_API_Client instance.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client The Payments API Client.
	 * @return void
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Init the hooks.
	 */
	public function init() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( 'validate_incompatible_extensions', [ $this, 'update_incompatible_extensions_list_and_maybe_show_warning' ] );
		add_action( 'activated_plugin', [ $this, 'show_warning_when_incompatible_extension_is_enabled' ] );
		add_action( 'deactivated_plugin', [ $this, 'hide_warning_when_incompatible_extension_is_disabled' ] );

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
	 * Updates the list of incompatible extensions.
	 */
	public function update_incompatible_extensions_list_and_maybe_show_warning() {
		try {
			$incompatible_extensions = $this->get_incompatible_extensions();
			$active_plugins          = get_option( 'active_plugins', [] );

			update_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, $incompatible_extensions );
			delete_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME );

			if ( ! empty( $active_plugins ) && is_array( $active_plugins ) ) {
				if ( $this->contains_incompatible_extension( $active_plugins, $incompatible_extensions ) ) {
					update_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );
				}
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay incompatible extensions list. ' . $e );
		}
	}

	/**
	 * Adds a warning to the WC Payments settings page.
	 *
	 * @param string $plugin The plugin being enabled.
	 */
	public function show_warning_when_incompatible_extension_is_enabled( $plugin ) {
		$incompatible_extensions = get_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [] );
		$plugin                  = $this->format_extension_name( $plugin );

		if ( $this->contains_incompatible_extension( [ $plugin ], $incompatible_extensions ) ) {
			update_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );
		}
	}

	/**
	 * Removes the warning when the last incompatible extension is removed.
	 *
	 * @param string $plugin_being_deactivated The plugin name.
	 */
	public function hide_warning_when_incompatible_extension_is_disabled( $plugin_being_deactivated ) {
		$incompatible_extensions = get_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [] );
		$active_plugins          = get_option( 'active_plugins', [] );

		// Needs to remove the plugin being deactivated because WordPress only updates the list after this hook runs.
		$active_plugins = array_diff( $active_plugins, [ $plugin_being_deactivated ] );

		// Only deactivates the warning if there are no other incompatible extensions.
		if ( ! $this->contains_incompatible_extension( $active_plugins, $incompatible_extensions ) ) {
			delete_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME );
		}
	}

	/**
	 * Checks if there is any incompatible extension in the list.
	 *
	 * @param mixed $active_plugins list of active plugins.
	 * @param mixed $incompatible_extensions  list of incompatible extensions.
	 *
	 * @return bool
	 */
	public function contains_incompatible_extension( $active_plugins, $incompatible_extensions ) {
		foreach ( $active_plugins as $plugin ) {
			$plugin = $this->format_extension_name( $plugin );

			if ( in_array( $plugin, $incompatible_extensions, true ) ) {
				return true;
			}
		}

		return false;
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
	public function get_incompatible_extensions() {
		$incompatible_extensions = $this->payments_api_client->get_woopay_incompatible_extensions();

		return isset( $incompatible_extensions['incompatible_extensions'] ) ? $incompatible_extensions['incompatible_extensions'] : [];
	}
}
