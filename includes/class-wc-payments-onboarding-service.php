<?php
/**
 * Class WC_Payments_Onboarding_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;

/**
 * Class handling onboarding related business logic.
 */
class WC_Payments_Onboarding_Service {

	const TEST_MODE_OPTION                    = 'wcpay_onboarding_test_mode';
	const ONBOARDING_ELIGIBILITY_MODAL_OPTION = 'wcpay_onboarding_eligibility_modal_dismissed';
	const SOURCE_WCADMIN_PAYMENT_TASK         = 'wcadmin-payment-task';
	const SOURCE_WCADMIN_SETTINGS_PAGE        = 'wcadmin-settings-page';
	const SOURCE_WCADMIN_INCENTIVE_PAGE       = 'wcadmin-incentive-page';
	const SOURCE_WCPAY_CONNECT_PAGE           = 'wcpay-connect-page';
	const SOURCE_WCPAY_RESET_ACCOUNT          = 'wcpay-reset-account';
	const SOURCE_WCPAY_SETUP_LIVE_PAYMENTS    = 'wcpay-setup-live-payments';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Cache util for managing onboarding data.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 * @param Database_Cache         $database_cache      Database cache util.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, Database_Cache $database_cache ) {
		$this->payments_api_client = $payments_api_client;
		$this->database_cache      = $database_cache;
	}

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'wcpay_dev_mode', [ $this, 'maybe_enable_dev_mode' ], 100 );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_classes' ] );
	}

	/**
	 * Retrieve the fields data to use in the onboarding form.
	 *
	 * The data is retrieved from the server and is cached. If we can't retrieve, we will use whatever data we have.
	 *
	 * @param string $locale The locale to use to i18n the data.
	 * @param bool   $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return ?array Fields data, or NULL if failed to retrieve.
	 */
	public function get_fields_data( string $locale = '', bool $force_refresh = false ): ?array {
		// If we don't have a server connection, return what data we currently have, regardless of expiry.
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return $this->database_cache->get( Database_Cache::ONBOARDING_FIELDS_DATA_KEY, true );
		}

		$cache_key = Database_Cache::ONBOARDING_FIELDS_DATA_KEY;
		if ( ! empty( $locale ) ) {
			$cache_key .= '__' . $locale;
		}

		return $this->database_cache->get_or_add(
			$cache_key,
			function () use ( $locale ) {
				try {
					// We will use the language for the current user (defaults to the site language).
					$fields_data = $this->payments_api_client->get_onboarding_fields_data( $locale );
				} catch ( API_Exception $e ) {
					// Return NULL to signal retrieval error.
					return null;
				}

				return $fields_data;
			},
			'__return_true',
			$force_refresh
		);
	}

	/**
	 * Gets and caches the business types per country from the server.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return array|bool Business types, or false if failed to retrieve.
	 */
	public function get_cached_business_types( bool $force_refresh = false ) {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		$refreshed = false;

		$business_types = $this->database_cache->get_or_add(
			Database_Cache::BUSINESS_TYPES_KEY,
			function () {
				try {
					$business_types = $this->payments_api_client->get_onboarding_business_types();
				} catch ( API_Exception $e ) {
					// Return false to signal retrieval error.
					return false;
				}

				if ( ! $this->is_valid_cached_business_types( $business_types ) ) {
					return false;
				}

				return $business_types;
			},
			[ $this, 'is_valid_cached_business_types' ],
			$force_refresh,
			$refreshed
		);

		if ( null === $business_types ) {
			return false;
		}

		return $business_types;
	}

	/**
	 * Get the required verification information for the selected country/type/structure combination from the API.
	 *
	 * @param string      $country_code The currently selected country code.
	 * @param string      $type         The currently selected business type.
	 * @param string|null $structure    The currently selected business structure (optional).
	 *
	 * @return array
	 *
	 * @throws API_Exception
	 */
	public function get_required_verification_information( string $country_code, string $type, $structure = null ): array {
		return $this->payments_api_client->get_onboarding_required_verification_information( $country_code, $type, $structure );
	}

	/**
	 * Check whether the business types fetched from the cache are valid.
	 *
	 * @param array|bool|string $business_types The business types returned from the cache.
	 *
	 * @return bool
	 */
	public function is_valid_cached_business_types( $business_types ): bool {
		if ( null === $business_types || false === $business_types ) {
			return false;
		}

		// Non-array values are not expected, and we expect a non-empty array.
		if ( ! is_array( $business_types ) || empty( $business_types ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Enable dev mode if onboarding test mode is enabled.
	 *
	 * @param bool $dev_mode Current dev mode value.
	 *
	 * @return bool
	 */
	public function maybe_enable_dev_mode( bool $dev_mode ): bool {
		return self::is_test_mode_enabled() || $dev_mode;
	}

	/**
	 * Adds body classes to the main wp-admin wrapper.
	 *
	 * @param string $classes Space separated string of class names.
	 *
	 * @return string Classes to add to the body.
	 */
	public function add_admin_body_classes( string $classes = '' ): string {
		// Onboarding needs to hide wp-admin navigation and masterbar while JS loads.
		// This class will be removed by the onboarding component.
		if ( isset( $_GET['path'] ) && '/payments/onboarding' === $_GET['path'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$classes .= ' woocommerce-admin-is-loading';
		}

		return $classes;
	}

	/**
	 * Clear any account options we may want to reset when a new onboarding flow is initialised.
	 * Currently, just deletes the option which stores whether the eligibility modal has been dismissed.
	 *
	 * @return boolean Whether the option was deleted successfully.
	 */
	public static function clear_account_options(): bool {
		return delete_option( self::ONBOARDING_ELIGIBILITY_MODAL_OPTION );
	}

	/**
	 * Set the onboarding eligibility modal dismissed option to true.
	 *
	 * @return void
	 */
	public static function set_onboarding_eligibility_modal_dismissed(): void {
		update_option( self::ONBOARDING_ELIGIBILITY_MODAL_OPTION, true );
	}

	/**
	 * Set onboarding test mode.
	 * Will also switch WC_Payments mode immediately.
	 *
	 * @param boolean $test_mode Whether to enable test mode.
	 * @return void
	 */
	public static function set_test_mode( bool $test_mode ): void {
		update_option( self::TEST_MODE_OPTION, $test_mode );

		// Ensure WC_Payments mode is switched immediately.
		if ( $test_mode ) {
			WC_Payments::mode()->dev();
		} else {
			WC_Payments::mode()->live();
		}
	}

	/**
	 * Returns whether onboarding test mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_test_mode_enabled(): bool {
		return get_option( self::TEST_MODE_OPTION );
	}

	/**
	 * Gets the source from the referer and URL params.
	 *
	 * @param string $referer    The referer.
	 * @param array  $get_params GET params.
	 *
	 * @return string The source or empty string if the source is unsupported.
	 */
	public static function get_source( string $referer, array $get_params ) : string {
		$wcpay_connect_param = sanitize_text_field( wp_unslash( $get_params['wcpay-connect'] ) );
		if ( 'WCADMIN_PAYMENT_TASK' === $wcpay_connect_param ) {
			return self::SOURCE_WCADMIN_PAYMENT_TASK;
		}
		// Payments tab in Woo Admin Settings page.
		if ( false !== strpos( $referer, 'page=wc-settings&tab=checkout' ) ) {
			return self::SOURCE_WCADMIN_SETTINGS_PAGE;
		}
		// Payments tab in the sidebar.
		if ( false !== strpos( $referer, 'path=%2Fwc-pay-welcome-page' ) ) {
			return self::SOURCE_WCADMIN_INCENTIVE_PAGE;
		}
		if ( false !== strpos( $referer, 'path=%2Fpayments%2Fconnect' ) ) {
			return self::SOURCE_WCPAY_CONNECT_PAGE;
		}
		if ( isset( $get_params['wcpay-disable-onboarding-test-mode'] ) ) {
			return self::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS;
		}
		if ( isset( $get_params['wcpay-reset-account'] ) ) {
			return self::SOURCE_WCPAY_RESET_ACCOUNT;
		}
		return '';
	}
}
