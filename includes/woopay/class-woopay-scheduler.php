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
 * This class adds a cron job that runs daily to check if is there any extensions incompatible with WooPay active,
 * if one is found WooPay is disabled, also updates the list of extensions adapted to work with WooPay and the
 * available countries.
 */
class WooPay_Scheduler {

	const INVALID_EXTENSIONS_FOUND_OPTION_NAME     = 'woopay_invalid_extension_found';
	const INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME = 'woopay_incompatible_extensions';
	const HAS_ADAPTED_EXTENSIONS_OPTION_NAME       = 'woopay_has_adapted_extensions';
	const ADAPTED_EXTENSIONS_LIST_OPTION_NAME      = 'woopay_adapted_extensions';

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
		add_action( 'validate_woopay_compatibility', [ $this, 'update_compatibility_and_maybe_show_incompatibility_warning' ] );
		add_action( 'activated_plugin', [ $this, 'show_warning_when_incompatible_extension_is_enabled' ] );
		add_action( 'deactivated_plugin', [ $this, 'hide_warning_when_incompatible_extension_is_disabled' ] );

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
	 * Updates the compability list.
	 */
	public function update_compatibility_and_maybe_show_incompatibility_warning() {
		try {
			$compatibility           = $this->payments_api_client->get_woopay_compatibility();
			$incompatible_extensions = isset( $compatibility['incompatible_extensions'] ) ? $compatibility['incompatible_extensions'] : [];
			$adapted_extensions      = isset( $compatibility['adapted_extensions'] ) ? $compatibility['adapted_extensions'] : [];
			$available_countries     = isset( $compatibility['available_countries'] ) ? $compatibility['available_countries'] : [];

			$active_plugins = get_option( 'active_plugins', [] );

			update_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, $incompatible_extensions );
			delete_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME );

			update_option( self::ADAPTED_EXTENSIONS_LIST_OPTION_NAME, $adapted_extensions );
			delete_option( self::HAS_ADAPTED_EXTENSIONS_OPTION_NAME );

			if ( ! empty( $active_plugins ) && is_array( $active_plugins ) ) {
				if ( $this->contains_extensions_in_list( $active_plugins, $incompatible_extensions ) ) {
					update_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );
				}

				if ( $this->contains_extensions_in_list( $active_plugins, $adapted_extensions ) ) {
					update_option( self::HAS_ADAPTED_EXTENSIONS_OPTION_NAME, true );
				}
			}

			$this->update_available_countries( $available_countries );
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay incompatible extensions list. ' . $e );
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
				update_option( WooPay_Utilities::AVAILABLE_COUNTRIES_OPTION_NAME, wp_json_encode( $available_countries ) );
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to decode WooPay available countries. ' . $e );
		}
	}

	/**
	 * Adds a warning to the WC Payments settings page.
	 *
	 * @param string $plugin The plugin being enabled.
	 */
	public function show_warning_when_incompatible_extension_is_enabled( $plugin ) {
		$incompatible_extensions = get_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [] );
		$adapted_extensions      = get_option( self::ADAPTED_EXTENSIONS_LIST_OPTION_NAME, [] );
		$plugin                  = $this->format_extension_name( $plugin );

		if ( $this->contains_extensions_in_list( [ $plugin ], $incompatible_extensions ) ) {
			update_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME, true );
		}

		if ( $this->contains_extensions_in_list( [ $plugin ], $adapted_extensions ) ) {
			update_option( self::HAS_ADAPTED_EXTENSIONS_OPTION_NAME, true );
		}
	}

	/**
	 * Removes the warning when the last incompatible extension is removed.
	 *
	 * @param string $plugin_being_deactivated The plugin name.
	 */
	public function hide_warning_when_incompatible_extension_is_disabled( $plugin_being_deactivated ) {
		$incompatible_extensions = get_option( self::INCOMPATIBLE_EXTENSIONS_LIST_OPTION_NAME, [] );
		$adapted_extensions      = get_option( self::ADAPTED_EXTENSIONS_LIST_OPTION_NAME, [] );
		$active_plugins          = get_option( 'active_plugins', [] );

		// Needs to remove the plugin being deactivated because WordPress only updates the list after this hook runs.
		$active_plugins = array_diff( $active_plugins, [ $plugin_being_deactivated ] );

		// Only deactivates the warning if there are no other incompatible extensions.
		if ( ! $this->contains_extensions_in_list( $active_plugins, $incompatible_extensions ) ) {
			delete_option( self::INVALID_EXTENSIONS_FOUND_OPTION_NAME );
		}

		if ( ! $this->contains_extensions_in_list( $active_plugins, $adapted_extensions ) ) {
			delete_option( self::ADAPTED_EXTENSIONS_LIST_OPTION_NAME );
		}
	}

	/**
	 * Checks if there is any incompatible extension in the list.
	 *
	 * @param mixed $active_plugins list of active plugins.
	 * @param mixed $extensions  list of incompatible extensions.
	 *
	 * @return bool
	 */
	public function contains_extensions_in_list( $active_plugins, $extensions ) {
		foreach ( $active_plugins as $plugin ) {
			$plugin = $this->format_extension_name( $plugin );

			if ( in_array( $plugin, $extensions, true ) ) {
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
}
