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

	// Onboarding flow sources.
	// We use these to identify the originating place for the current onboarding flow.
	// This should be very sticky as opposed to the `from` value which is meant to represent the immediately previous step.
	const SOURCE_WCADMIN_PAYMENT_TASK               = 'wcadmin-payment-task';
	const SOURCE_WCADMIN_SETTINGS_PAGE              = 'wcadmin-settings-page';
	const SOURCE_WCADMIN_INCENTIVE_PAGE             = 'wcadmin-incentive-page';
	const SOURCE_WCPAY_CONNECT_PAGE                 = 'wcpay-connect-page';
	const SOURCE_WCPAY_OVERVIEW_PAGE                = 'wcpay-overview-page';
	const SOURCE_WCPAY_PAYOUTS_PAGE                 = 'wcpay-payouts-page';
	const SOURCE_WCPAY_RESET_ACCOUNT                = 'wcpay-reset-account';
	const SOURCE_WCPAY_SETUP_LIVE_PAYMENTS          = 'wcpay-setup-live-payments';
	const SOURCE_WCPAY_FINISH_SETUP_TASK            = 'wcpay-finish-setup-task';
	const SOURCE_WCPAY_UPDATE_BUSINESS_DETAILS_TASK = 'wcpay-update-business-details-task';
	const SOURCE_WCPAY_PO_BANK_ACCOUNT_TASK         = 'wcpay-po-bank-account-task';
	const SOURCE_WCPAY_RECONNECT_WPCOM_TASK         = 'wcpay-reconnect-wpcom-task';
	const SOURCE_WCPAY_ADD_APMS_TASK                = 'wcpay-add-apms-task';
	const SOURCE_WCPAY_GO_LIVE_TASK                 = 'wcpay-go-live-task';
	const SOURCE_WCPAY_FINISH_SETUP_TOOL            = 'wcpay-finish-setup-tool';
	const SOURCE_WCPAY_PAYOUT_FAILURE_NOTICE        = 'wcpay-payout-failure-notice';
	const SOURCE_WCPAY_ACCOUNT_DETAILS              = 'wcpay-account-details';
	const SOURCE_UNKNOWN                            = 'unknown';

	// Values for the `from` GET param to indicate what was the immediately previous step.
	// Woo core places.
	const FROM_WCADMIN_PAYMENTS_TASK     = 'WCADMIN_PAYMENT_TASK';
	const FROM_WCADMIN_PAYMENTS_SETTINGS = 'WCADMIN_PAYMENT_SETTINGS';
	const FROM_WCADMIN_INCENTIVE         = 'WCADMIN_PAYMENT_INCENTIVE';
	// WooPayments places.
	const FROM_CONNECT_PAGE      = 'WCPAY_CONNECT';
	const FROM_OVERVIEW_PAGE     = 'WCPAY_OVERVIEW';
	const FROM_ACCOUNT_DETAILS   = 'WCPAY_ACCOUNT_DETAILS';
	const FROM_ONBOARDING_WIZARD = 'WCPAY_ONBOARDING_WIZARD';
	const FROM_SETTINGS          = 'WCPAY_SETTINGS';
	const FROM_PAYOUTS           = 'WCPAY_PAYOUTS';
	const FROM_TEST_TO_LIVE      = 'WCPAY_TEST_TO_LIVE';
	const FROM_GO_LIVE_TASK      = 'WCPAY_GO_LIVE_TASK';
	const FROM_RESET_ACCOUNT     = 'WCPAY_RESET_ACCOUNT';
	const FROM_PLUGIN_ACTIVATION = 'WCPAY_ACTIVE';
	// External places.
	const FROM_WPCOM            = 'WPCOM';
	const FROM_WPCOM_CONNECTION = 'WPCOM_CONNECTION';
	const FROM_STRIPE           = 'STRIPE';

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
	 *
	 * Will also switch the WC_Payments onboarding mode immediately.
	 *
	 * @param boolean $test_mode Whether to enable test mode.
	 * @return void
	 */
	public static function set_test_mode( bool $test_mode ): void {
		update_option( self::TEST_MODE_OPTION, $test_mode ? 'yes' : 'no', true );

		// Switch WC_Payments onboarding mode immediately.
		if ( $test_mode ) {
			\WC_Payments::mode()->test_mode_onboarding();
		} else {
			\WC_Payments::mode()->live_mode_onboarding();
		}
	}

	/**
	 * Determine if test mode onboarding is enabled.
	 *
	 * @return bool Whether test mode onboarding is enabled or not.
	 */
	public static function is_test_mode_enabled(): bool {
		// We support the `1` option value also for backward compatibility with version 8.1.0.
		return in_array( get_option( self::TEST_MODE_OPTION, 'no' ), [ 'yes', '1' ], true );
	}

	/**
	 * Determine what was the immediate previous step that landed us to the current request.
	 *
	 * We take into account the referer and GET params, with the referer having the lowest priority.
	 * The primary intention of the from value is to inform the current step logic and allow it to customize the
	 * behavior and/or UX based on the previous step.
	 *
	 * Note: Consider carefully when carrying over the from value to the next step.
	 *       Doing so should mean that we didn't complete any step right now, but just moved the merchant around
	 *       (probably through redirects).
	 *
	 * @param string|null $referer    Optional. The referer URL. Defaults to wp_get_raw_referer().
	 * @param array|null  $get_params Optional. GET params. Defaults to $_GET.
	 *
	 * @return string The from value or empty string if we could not identify a known value.
	 */
	public static function get_from( ?string $referer = null, ?array $get_params = null ): string {
		$referer = $referer ?? wp_get_raw_referer();
		// Ensure we decode the referer URL in case it contains encoded characters in its GET parameters.
		// This way we don't need to distinguish between `%2F` and `/`.
		$referer    = urldecode( $referer );
		$get_params = $get_params ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/**
		 * =================
		 * First, we check the `from` GET param.
		 * If the `from` param is already set and not empty, use it.
		 * =================
		 */
		$from_param = isset( $get_params['from'] ) ? sanitize_text_field( wp_unslash( $get_params['from'] ) ) : '';
		if ( ! empty( $from_param ) ) {
			return $from_param;
		}

		/**
		 * =================
		 * Next, we check the action-type GET params as they should only be set when the user takes a certain action.
		 * This means they have higher priority than the other "clues" like `wcpay-connect`, `from`, referer.
		 * =================
		 */
		if ( isset( $get_params['wcpay-disable-onboarding-test-mode'] ) && 'true' === $get_params['wcpay-disable-onboarding-test-mode'] ) {
			return self::FROM_TEST_TO_LIVE;
		}
		if ( isset( $get_params['wcpay-reset-account'] ) && 'true' === $get_params['wcpay-reset-account'] ) {
			return self::FROM_RESET_ACCOUNT;
		}

		/**
		 * =================
		 * Next, we check the `wcpay-connect` GET param. This should hold valid from values.
		 * If it has a known from value, use it.
		 * =================
		 */
		$wcpay_connect_param = isset( $get_params['wcpay-connect'] ) ? sanitize_text_field( wp_unslash( $get_params['wcpay-connect'] ) ) : '';
		if ( in_array(
			$wcpay_connect_param,
			[
				self::FROM_WCADMIN_PAYMENTS_TASK,
				self::FROM_WCADMIN_PAYMENTS_SETTINGS,
				self::FROM_WCADMIN_INCENTIVE,
				self::FROM_CONNECT_PAGE,
				self::FROM_OVERVIEW_PAGE,
				self::FROM_ACCOUNT_DETAILS,
				self::FROM_ONBOARDING_WIZARD,
				self::FROM_TEST_TO_LIVE,
				self::FROM_RESET_ACCOUNT,
				self::FROM_WPCOM,
				self::FROM_WPCOM_CONNECTION,
				self::FROM_STRIPE,
			],
			true
		) ) {
			return $wcpay_connect_param;
		}

		/**
		 * =================
		 * Finally, we check the referer URL as it has the lowest priority.
		 * =================
		 */
		if ( false !== strpos( $referer, 'page=wc-admin&task=payments' ) ) {
			return self::FROM_WCADMIN_PAYMENTS_TASK;
		}
		if ( false !== strpos( $referer, 'page=wc-settings&tab=checkout' ) ) {
			return self::FROM_WCADMIN_PAYMENTS_SETTINGS;
		}
		if ( false !== strpos( $referer, 'path=/wc-pay-welcome-page' ) ) {
			return self::FROM_WCADMIN_INCENTIVE;
		}
		if ( false !== strpos( $referer, 'path=/payments/connect' ) ) {
			return self::FROM_CONNECT_PAGE;
		}
		if ( false !== strpos( $referer, 'path=/payments/overview' ) ) {
			return self::FROM_OVERVIEW_PAGE;
		}
		if ( false !== strpos( $referer, 'path=/payments/onboarding' ) ) {
			return self::FROM_ONBOARDING_WIZARD;
		}
		if ( false !== strpos( $referer, 'path=/payments/deposits' ) ) {
			return self::FROM_PAYOUTS;
		}
		if ( false !== strpos( $referer, 'wordpress.com' ) ) {
			return self::FROM_WPCOM;
		}
		if ( false !== strpos( $referer, 'stripe.com' ) ) {
			return self::FROM_STRIPE;
		}

		// Default to empty string.
		return '';
	}

	/**
	 * Determine the initial onboarding source from the referer and URL params.
	 *
	 * NOTE: Avoid basing business logic on this since it is primarily intended for tracking purposes.
	 *       It is greedy in determining the onboarding source and may not always be accurate.
	 *
	 * @param string|null $referer    Optional. The referer URL. Defaults to wp_get_raw_referer().
	 * @param array|null  $get_params Optional. GET params. Defaults to $_GET.
	 *
	 * @return string The source or WC_Payments_Onboarding_Service::SOURCE_UNKNOWN if the source is unknown.
	 */
	public static function get_source( ?string $referer = null, ?array $get_params = null ): string {
		$referer = $referer ?? wp_get_raw_referer();
		// Ensure we decode the referer URL in case it contains encoded characters in its GET parameters.
		// This way we don't need to distinguish between `%2F` and `/`.
		$referer    = urldecode( $referer );
		$get_params = $get_params ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$valid_sources = [
			self::SOURCE_WCADMIN_PAYMENT_TASK,
			self::SOURCE_WCADMIN_SETTINGS_PAGE,
			self::SOURCE_WCADMIN_INCENTIVE_PAGE,
			self::SOURCE_WCPAY_CONNECT_PAGE,
			self::SOURCE_WCPAY_OVERVIEW_PAGE,
			self::SOURCE_WCPAY_PAYOUTS_PAGE,
			self::SOURCE_WCPAY_RESET_ACCOUNT,
			self::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS,
			self::SOURCE_WCPAY_FINISH_SETUP_TASK,
			self::SOURCE_WCPAY_UPDATE_BUSINESS_DETAILS_TASK,
			self::SOURCE_WCPAY_PO_BANK_ACCOUNT_TASK,
			self::SOURCE_WCPAY_RECONNECT_WPCOM_TASK,
			self::SOURCE_WCPAY_ADD_APMS_TASK,
			self::SOURCE_WCPAY_GO_LIVE_TASK,
			self::SOURCE_WCPAY_FINISH_SETUP_TOOL,
			self::SOURCE_WCPAY_PAYOUT_FAILURE_NOTICE,
			self::SOURCE_WCPAY_ACCOUNT_DETAILS,
		];

		/**
		 * =================
		 * First, we check the `source` GET param.
		 * If the source param is already set and a valid value, use it.
		 * =================
		 */
		$source_param = isset( $get_params['source'] ) ? sanitize_text_field( wp_unslash( $get_params['source'] ) ) : '';
		if ( in_array( $source_param, $valid_sources, true ) ) {
			return $source_param;
		}

		/**
		 * =================
		 * Next, we check the action-type GET params as they should only be set when the user takes a certain action.
		 * This means they have higher priority than the other "clues" like `wcpay-connect`, `from`, referer.
		 * =================
		 */
		if ( isset( $get_params['wcpay-disable-onboarding-test-mode'] ) && 'true' === $get_params['wcpay-disable-onboarding-test-mode'] ) {
			return self::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS;
		}
		if ( isset( $get_params['wcpay-reset-account'] ) && 'true' === $get_params['wcpay-reset-account'] ) {
			return self::SOURCE_WCPAY_RESET_ACCOUNT;
		}

		$wcpay_connect_param = isset( $get_params['wcpay-connect'] ) ? sanitize_text_field( wp_unslash( $get_params['wcpay-connect'] ) ) : '';
		$from_param          = isset( $get_params['from'] ) ? sanitize_text_field( wp_unslash( $get_params['from'] ) ) : '';

		/**
		 * =================
		 * Next, we check the `wcpay-connect` GET param as it has higher priority than `from` GET param or referer.
		 * =================
		 */
		switch ( $wcpay_connect_param ) {
			case self::FROM_WCADMIN_PAYMENTS_TASK:
				return self::SOURCE_WCADMIN_PAYMENT_TASK;
			case self::FROM_WCADMIN_PAYMENTS_SETTINGS:
				return self::SOURCE_WCADMIN_SETTINGS_PAGE;
			case self::FROM_WCADMIN_INCENTIVE:
				return self::SOURCE_WCADMIN_INCENTIVE_PAGE;
			default:
				break;
		}

		/**
		 * =================
		 * Next, we check the `from` GET param as it has a higher priority than the referer.
		 *
		 * Not all `from` values are taken into account (e.g. we ignore 'WCPAY_ONBOARDING_WIZARD').
		 * =================
		 */
		switch ( $from_param ) {
			case self::FROM_WCADMIN_PAYMENTS_TASK:
				return self::SOURCE_WCADMIN_PAYMENT_TASK;
			case self::FROM_SETTINGS:
			case self::FROM_WCADMIN_PAYMENTS_SETTINGS:
				return self::SOURCE_WCADMIN_SETTINGS_PAGE;
			case self::FROM_WCADMIN_INCENTIVE:
				return self::SOURCE_WCADMIN_INCENTIVE_PAGE;
			case self::FROM_CONNECT_PAGE:
				return self::SOURCE_WCPAY_CONNECT_PAGE;
			case self::FROM_PAYOUTS:
				return self::SOURCE_WCPAY_PAYOUTS_PAGE;
			case self::FROM_GO_LIVE_TASK:
				return self::SOURCE_WCPAY_GO_LIVE_TASK;
			case self::FROM_ACCOUNT_DETAILS:
				return self::SOURCE_WCPAY_ACCOUNT_DETAILS;
			default:
				break;
		}

		$referer_params = [];
		wp_parse_str( wp_parse_url( $referer, PHP_URL_QUERY ), $referer_params );

		/**
		 * =================
		 * Use the source from the referer URL, if present and valid.
		 * =================
		 */
		$source_param = isset( $referer_params['source'] ) ? sanitize_text_field( wp_unslash( $referer_params['source'] ) ) : '';
		if ( ! empty( $source_param ) && in_array( $source_param, $valid_sources, true ) ) {
			return $source_param;
		}

		/**
		 * =================
		 * Finally, we try to determine the source by what page the request came from.
		 * =================
		 */
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'task' => 'payments',
				]
			)
		) ) {
			return self::SOURCE_WCADMIN_PAYMENT_TASK;
		}
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				]
			)
		) ) {
			return self::SOURCE_WCADMIN_SETTINGS_PAGE;
		}
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'path' => '/wc-pay-welcome-page',
				]
			)
		) ) {
			return self::SOURCE_WCADMIN_INCENTIVE_PAGE;
		}
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'path' => '/payments/connect',
				]
			)
		) ) {
			return self::SOURCE_WCPAY_CONNECT_PAGE;
		}
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				]
			)
		) ) {
			return self::SOURCE_WCPAY_OVERVIEW_PAGE;
		}
		if ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'path' => '/payments/deposits',
				]
			)
		) ) {
			return self::SOURCE_WCPAY_PAYOUTS_PAGE;
		}

		// Default to an unknown source.
		return self::SOURCE_UNKNOWN;
	}
}
