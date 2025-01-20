<?php
/**
 * Class WC_Payments_Onboarding_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\Notes\DataStore;
use Automattic\WooCommerce\Admin\Notes\Note;
use WCPay\Database_Cache;
use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * Class handling onboarding related business logic.
 */
class WC_Payments_Onboarding_Service {

	const TEST_MODE_OPTION                           = 'wcpay_onboarding_test_mode';
	const ONBOARDING_ELIGIBILITY_MODAL_OPTION        = 'wcpay_onboarding_eligibility_modal_dismissed';
	const ONBOARDING_CONNECTION_SUCCESS_MODAL_OPTION = 'wcpay_connection_success_modal_dismissed';

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
	const FROM_ONBOARDING_KYC    = 'WCPAY_ONBOARDING_KYC'; // The embedded Stripe KYC step/page.
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
	const FROM_STRIPE_EMBEDDED  = 'STRIPE_EMBEDDED';

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
	 * Session service.
	 *
	 * @var WC_Payments_Session_Service instance for working with session information
	 */
	private $session_service;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client      $payments_api_client Payments API client.
	 * @param Database_Cache              $database_cache      Database cache util.
	 * @param WC_Payments_Session_Service $session_service     Session service.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, Database_Cache $database_cache, WC_Payments_Session_Service $session_service ) {
		$this->payments_api_client = $payments_api_client;
		$this->database_cache      = $database_cache;
		$this->session_service     = $session_service;
	}

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_classes' ] );
		add_filter( 'wc_payments_get_onboarding_data_args', [ $this, 'maybe_add_test_drive_settings_to_new_account_request' ] );
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
	 * Retrieve and cache the account recommended payment methods list.
	 *
	 * @param string $country_code The account's business location country code. Provide a 2-letter ISO country code.
	 * @param string $locale       Optional. The locale to use to i18n the data.
	 *
	 * @return ?array The recommended payment methods list.
	 *                NULL on retrieval or validation error.
	 */
	public function get_recommended_payment_methods( string $country_code, string $locale = '' ): ?array {
		$cache_key = Database_Cache::RECOMMENDED_PAYMENT_METHODS . '__' . $country_code;
		if ( ! empty( $locale ) ) {
			$cache_key .= '__' . $locale;
		}

		return \WC_Payments::get_database_cache()->get_or_add(
			$cache_key,
			function () use ( $country_code, $locale ) {
				try {
					return $this->payments_api_client->get_recommended_payment_methods( $country_code, $locale );
				} catch ( API_Exception $e ) {
					// Return NULL to signal retrieval error.
					return null;
				}
			},
			'is_array'
		);
	}

	/**
	 * Get the onboarding capabilities from the request.
	 *
	 * The capabilities are expected to be passed as an array of capabilities keyed by the capability ID and
	 * with boolean values. If the value is true, the capability is requested when the account is created.
	 *
	 * @return array The standardized capabilities that were passed in the request.
	 *               Empty array if no capabilities were passed or none were valid.
	 */
	public function get_capabilities_from_request(): array {
		$capabilities = [];

		if ( empty( $_REQUEST['capabilities'] ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return $capabilities;
		}

		// Try to extract the capabilities.
		// They might be already decoded or not, so we need to handle both cases.
		// We expect them to be an array.
		// We disable the warning because we have our own sanitization and validation.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$capabilities = wp_unslash( $_REQUEST['capabilities'] );
		if ( ! is_array( $capabilities ) ) {
			$capabilities = json_decode( $capabilities, true ) ?? [];
		}

		if ( empty( $capabilities ) ) {
			return [];
		}

		// Sanitize and validate.
		$capabilities = array_combine(
			array_map(
				function ( $key ) {
					// Keep numeric keys as integers so we can remove them later.
					if ( is_numeric( $key ) ) {
						return intval( $key );
					}

					return sanitize_text_field( $key );
				},
				array_keys( $capabilities )
			),
			array_map(
				function ( $value ) {
					return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				},
				$capabilities
			)
		);

		// Filter out any invalid entries.
		$capabilities = array_filter(
			$capabilities,
			function ( $value, $key ) {
				return is_string( $key ) && is_bool( $value );
			},
			ARRAY_FILTER_USE_BOTH
		);

		return $capabilities;
	}

	/**
	 * Retrieve the embedded KYC session and handle initial account creation (if necessary).
	 *
	 * Will return the session key used to initialise the embedded onboarding session.
	 *
	 * @param array   $self_assessment_data Self assessment data.
	 * @param boolean $progressive Whether the onboarding is progressive.
	 *
	 * @return array Session data.
	 *
	 * @throws API_Exception|Exception
	 */
	public function create_embedded_kyc_session( array $self_assessment_data, bool $progressive = false ): array {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		$setup_mode = WC_Payments::mode()->is_live() ? 'live' : 'test';

		// Make sure the onboarding test mode DB flag is set.
		self::set_test_mode( 'live' !== $setup_mode );

		$site_data      = [
			'site_username' => wp_get_current_user()->user_login,
			'site_locale'   => get_locale(),
		];
		$user_data      = $this->get_onboarding_user_data();
		$account_data   = $this->get_account_data(
			$setup_mode,
			$self_assessment_data,
			$this->get_capabilities_from_request()
		);
		$actioned_notes = self::get_actioned_notes();

		/**
		 * ==================
		 * Enforces the update of payment methods to 'enabled' based on the capabilities
		 * provided during the NOX onboarding process.
		 *
		 * @see self::update_enabled_payment_methods_ids
		 * ==================
		 */
		$capabilities = $this->get_capabilities_from_request();
		$gateway      = WC_Payments::get_gateway();

		// Activate enabled Payment Methods IDs.
		if ( ! empty( $capabilities ) ) {
			$this->update_enabled_payment_methods_ids( $gateway, $capabilities );
		}

		try {
			$account_session = $this->payments_api_client->initialize_onboarding_embedded_kyc(
				'live' === $setup_mode,
				$site_data,
				WC_Payments_Utils::array_filter_recursive( $user_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
				WC_Payments_Utils::array_filter_recursive( $account_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
				$actioned_notes,
				$progressive
			);
		} catch ( API_Exception $e ) {
			// If we fail to create the session, return an empty array.
			return [];
		}

		// Set the embedded KYC in progress flag.
		$this->set_embedded_kyc_in_progress();

		// Remember if we should enable WooPay by default.
		set_transient(
			WC_Payments_Account::WOOPAY_ENABLED_BY_DEFAULT_TRANSIENT,
			filter_var( $account_session['woopay_enabled_by_default'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			DAY_IN_SECONDS
		);

		return [
			'clientSecret'   => $account_session['client_secret'] ?? '',
			'expiresAt'      => $account_session['expires_at'] ?? 0,
			'accountId'      => $account_session['account_id'] ?? '',
			'isLive'         => $account_session['is_live'] ?? false,
			'accountCreated' => $account_session['account_created'] ?? false,
			'publishableKey' => $account_session['publishable_key'] ?? '',
		];
	}

	/**
	 * Finalize the embedded KYC session.
	 *
	 * @param string $locale The locale to use to i18n the data.
	 * @param string $source The source of the onboarding flow.
	 * @param array  $actioned_notes The actioned notes for this onboarding.
	 *
	 * @return array Containing the following keys: success, account_id, mode.
	 *
	 * @throws API_Exception
	 */
	public function finalize_embedded_kyc( string $locale, string $source, array $actioned_notes ): array {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [
				'success' => false,
			];
		}

		$result = $this->payments_api_client->finalize_onboarding_embedded_kyc( $locale, $source, $actioned_notes );

		$success           = $result['success'] ?? false;
		$details_submitted = $result['details_submitted'] ?? false;

		if ( ! $result || ! $success ) {
			throw new API_Exception( __( 'Failed to finalize onboarding session.', 'woocommerce-payments' ), 'wcpay-onboarding-finalize-error', 400 );
		}

		// Clear the embedded KYC in progress option, since the onboarding flow is now complete.
		$this->clear_embedded_kyc_in_progress();

		return [
			'success'           => $success,
			'details_submitted' => $details_submitted,
			'account_id'        => $result['account_id'] ?? '',
			'mode'              => $result['mode'],
			'promotion_id'      => $result['promotion_id'] ?? null,
		];
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
	 * Get account data for onboarding from self assessment data.
	 *
	 * @param string $setup_mode           Setup mode.
	 * @param array  $self_assessment_data Self assessment data.
	 * @param array  $capabilities         Optional. List keyed by capabilities IDs (payment methods) with boolean values.
	 *                                     If the value is true, the capability is requested when the account is created.
	 *                                     If the value is false, the capability is not requested when the account is created.
	 *
	 * @return array Account data.
	 */
	public function get_account_data( string $setup_mode, array $self_assessment_data, array $capabilities = [] ): array {
		$home_url = get_home_url();
		// If the site is running on localhost, use a bogus URL. This is to avoid Stripe's errors.
		// wp_http_validate_url does not check that, unfortunately.
		$home_is_localhost = 'localhost' === wp_parse_url( $home_url, PHP_URL_HOST );
		$fallback_url      = ( 'live' !== $setup_mode || $home_is_localhost ) ? 'https://wcpay.test' : null;
		$current_user      = get_userdata( get_current_user_id() );

		// The general account data.
		$account_data = [
			'setup_mode'    => $setup_mode,
			// We use the store base country to create a customized account.
			'country'       => WC()->countries->get_base_country() ?? null,
			'url'           => ! $home_is_localhost && wp_http_validate_url( $home_url ) ? $home_url : $fallback_url,
			'business_name' => get_bloginfo( 'name' ),
		];

		foreach ( $capabilities as $capability => $should_request ) {
			// Remove the `_payments` suffix from the capability, if present.
			if ( strpos( $capability, '_payments' ) === strlen( $capability ) - 9 ) {
				$capability = str_replace( '_payments', '', $capability );
			}

			// Skip the special 'apple_google' because it is not a payment method.
			// Skip the 'woopay' because it is automatically handled by the API.
			if ( 'apple_google' === $capability || 'woopay' === $capability ) {
				continue;
			}

			if ( 'card' === $capability ) {
				// Card is always requested.
				$account_data['capabilities']['card_payments'] = [ 'requested' => 'true' ];
				// When requesting card, we also need to request transfers.
				// The platform should handle this automatically, but it is best to be thorough.
				$account_data['capabilities']['transfers'] = [ 'requested' => 'true' ];
				continue;
			}

			// We only request, not unrequest capabilities.
			if ( $should_request ) {
				$account_data['capabilities'][ $capability . '_payments' ] = [ 'requested' => 'true' ];
			}
		}

		if ( ! empty( $self_assessment_data ) ) {
			$business_type = $self_assessment_data['business_type'] ?? null;
			$account_data  = WC_Payments_Utils::array_merge_recursive_distinct(
				$account_data,
				[
					// Overwrite the country if the merchant chose a different one than the Woo base location.
					'country'       => $self_assessment_data['country'] ?? null,
					'email'         => $self_assessment_data['email'] ?? null,
					'business_name' => $self_assessment_data['business_name'] ?? null,
					'url'           => $self_assessment_data['url'] ?? null,
					'mcc'           => $self_assessment_data['mcc'] ?? null,
					'business_type' => $business_type,
					'company'       => [
						'structure' => 'company' === $business_type ? ( $self_assessment_data['company']['structure'] ?? null ) : null,
					],
					'individual'    => [
						'first_name' => $self_assessment_data['individual']['first_name'] ?? null,
						'last_name'  => $self_assessment_data['individual']['last_name'] ?? null,
						'phone'      => $self_assessment_data['phone'] ?? null,
					],
					'store'         => [
						'annual_revenue'    => $self_assessment_data['annual_revenue'] ?? null,
						'go_live_timeframe' => $self_assessment_data['go_live_timeframe'] ?? null,
					],
				]
			);
		} elseif ( 'test_drive' === $setup_mode ) {
			$account_data = WC_Payments_Utils::array_merge_recursive_distinct(
				$account_data,
				[
					'individual' => [
						'first_name' => $current_user->first_name ?? null,
						'last_name'  => $current_user->last_name ?? null,
					],
				]
			);
		} elseif ( 'test' === $setup_mode ) {
			$account_data = WC_Payments_Utils::array_merge_recursive_distinct(
				$account_data,
				[
					'business_type' => 'individual',
					'mcc'           => '5734',
					'individual'    => [
						'first_name' => $current_user->first_name ?? null,
						'last_name'  => $current_user->last_name ?? null,
					],
				]
			);
		}

		return $account_data;
	}

	/**
	 * Get user data to send to the onboarding flow.
	 *
	 * @return array The user data.
	 */
	public function get_onboarding_user_data(): array {
		return [
			'user_id'           => get_current_user_id(),
			'sift_session_id'   => $this->session_service->get_sift_session_id(),
			'ip_address'        => \WC_Geolocation::get_ip_address(),
			'browser'           => [
				'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'accept_language'  => isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? wc_clean( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '',
				'content_language' => empty( get_user_locale() ) ? 'en-US' : str_replace( '_', '-', get_user_locale() ),
			],
			'referer'           => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'onboarding_source' => self::get_source(),
		];
	}

	/**
	 * Determine whether an embedded KYC flow is in progress.
	 *
	 * @return bool True if embedded KYC is in progress, false otherwise.
	 */
	public function is_embedded_kyc_in_progress(): bool {
		return filter_var( get_option( WC_Payments_Account::EMBEDDED_KYC_IN_PROGRESS_OPTION, 'no' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Mark the embedded KYC flow as in progress.
	 *
	 * @return bool Whether we successfully marked the flow as in progress.
	 */
	public function set_embedded_kyc_in_progress(): bool {
		return update_option( WC_Payments_Account::EMBEDDED_KYC_IN_PROGRESS_OPTION, 'yes' );
	}

	/**
	 * Clear any embedded KYC in progress flags.
	 *
	 * @return boolean Whether we successfully cleared the flags.
	 */
	public function clear_embedded_kyc_in_progress(): bool {
		return delete_option( WC_Payments_Account::EMBEDDED_KYC_IN_PROGRESS_OPTION );
	}

	/**
	 * Get actioned notes.
	 *
	 * @return array
	 */
	public static function get_actioned_notes(): array {
		$wcpay_note_names = [];

		try {
			/**
			 * Data Store for admin notes
			 *
			 * @var DataStore $data_store
			 */
			$data_store = WC_Data_Store::load( 'admin-note' );
		} catch ( Exception $e ) {
			// Don't stop the on-boarding process if something goes wrong here. Log the error and return the empty array
			// of actioned notes.
			Logger::error( $e );
			return $wcpay_note_names;
		}

		// Fetch the last 10 actioned wcpay-promo admin notifications.
		$add_like_clause = function ( $where_clause ) {
			return $where_clause . " AND name like 'wcpay-promo-%'";
		};

		add_filter( 'woocommerce_note_where_clauses', $add_like_clause );

		$wcpay_promo_notes = $data_store->get_notes(
			[
				'status'     => [ Note::E_WC_ADMIN_NOTE_ACTIONED ],
				'is_deleted' => false,
				'per_page'   => 10,
			]
		);

		remove_filter( 'woocommerce_note_where_clauses', $add_like_clause );

		// If we didn't get an array back from the data store, return an empty array of results.
		if ( ! is_array( $wcpay_promo_notes ) ) {
			return $wcpay_note_names;
		}

		// Copy the name of each note into the results.
		foreach ( (array) $wcpay_promo_notes as $wcpay_note ) {
			$note               = new Note( $wcpay_note->note_id );
			$wcpay_note_names[] = $note->get_name();
		}

		return $wcpay_note_names;
	}

	/**
	 * Clear any account options we may want to reset when a new onboarding flow is initialised.
	 * Currently, this deletes two options that store whether the eligibility and connection success modals have been dismissed.
	 *
	 * @return void
	 */
	public static function clear_account_options(): void {
		delete_option( self::ONBOARDING_ELIGIBILITY_MODAL_OPTION );
		delete_option( self::ONBOARDING_CONNECTION_SUCCESS_MODAL_OPTION );
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
		if ( false !== strpos( $referer, 'path=/payments/deposits' ) || false !== strpos( $referer, 'path=/payments/payouts' ) ) {
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
		) || ( 2 === count(
			array_intersect_assoc(
				$referer_params,
				[
					'page' => 'wc-admin',
					'path' => '/payments/payouts',
				]
			)
		) ) ) {
			return self::SOURCE_WCPAY_PAYOUTS_PAGE;
		}

		// Default to an unknown source.
		return self::SOURCE_UNKNOWN;
	}

	/**
	 * If settings are collected from the test-drive account,
	 * include them in the existing arguments when creating the new account.
	 *
	 * @param array $args The request args to create new account.
	 *
	 * @return array The request args, possible updated with the test drive account settings, used to create new account.
	 */
	public function maybe_add_test_drive_settings_to_new_account_request( array $args ): array {
		if (
			get_transient( WC_Payments_Account::ONBOARDING_TEST_DRIVE_SETTINGS_FOR_LIVE_ACCOUNT ) &&
			is_array( get_transient( WC_Payments_Account::ONBOARDING_TEST_DRIVE_SETTINGS_FOR_LIVE_ACCOUNT ) )
		) {
			$args['account_data'] = array_merge(
				$args['account_data'],
				get_transient( WC_Payments_Account::ONBOARDING_TEST_DRIVE_SETTINGS_FOR_LIVE_ACCOUNT )
			);
			delete_transient( WC_Payments_Account::ONBOARDING_TEST_DRIVE_SETTINGS_FOR_LIVE_ACCOUNT );
		}

		return $args;
	}

	/**
	 * Update payment methods to 'enabled' based on the capabilities
	 * provided during the NOX onboarding process. Merchants can preselect their preferred
	 * payment methods as part of this flow.
	 *
	 * The capabilities are provided in the following format:
	 *
	 * [
	 *   'card' => true,
	 *   'affirm' => true,
	 *   ...
	 * ]
	 *
	 * @param WC_Payment_Gateway_WCPay $gateway Payment gateway instance.
	 * @param array                    $capabilities Provided capabilities.
	 */
	public function update_enabled_payment_methods_ids( $gateway, $capabilities = [] ): void {
		$enabled_gateways = $gateway->get_upe_enabled_payment_method_ids();

		$enabled_payment_methods = array_unique(
			array_merge(
				$enabled_gateways,
				$this->exclude_placeholder_payment_methods( $capabilities )
			)
		);

		// Update the gateway option.
		$gateway->update_option( 'upe_enabled_payment_method_ids', $enabled_payment_methods );

		/**
		 * Keeps the list of enabled payment method IDs synchronized between the default
		 * `woocommerce_woocommerce_payments_settings` and duplicates in individual gateway settings.
		 */
		foreach ( $enabled_payment_methods as $payment_method_id ) {
			$payment_gateway = WC_Payments::get_payment_gateway_by_id( $payment_method_id );
			if ( $payment_gateway ) {
				$payment_gateway->enable();
				$payment_gateway->update_option( 'upe_enabled_payment_method_ids', $enabled_payment_methods );
			}
		}

		// If WooPay is enabled, update the gateway option.
		if ( ! empty( $capabilities['woopay'] ) ) {
			$gateway->update_is_woopay_enabled( true );
		}

		// If Apple Pay and Google Pay are disabled update the gateway option,
		// otherwise they are enabled by default.
		if ( empty( $capabilities['apple_google'] ) ) {
			$gateway->update_option( 'payment_request', 'no' );
		}
	}

	/**
	 * Excludes placeholder payment methods and removes duplicates.
	 *
	 * WooPay and Apple Pay & Google Pay are considered placeholder payment methods and are excluded.
	 *
	 * @param array $payment_methods Array of payment methods to process.
	 *
	 * @return array Filtered array of unique payment methods.
	 */
	private function exclude_placeholder_payment_methods( array $payment_methods ): array {
		// Placeholder payment methods.
		$excluded_methods = [ 'woopay', 'apple_google' ];

		return array_filter(
			array_unique(
				array_keys( array_filter( $payment_methods ) )
			),
			function ( $payment_method ) use ( $excluded_methods ) {
				return ! in_array( $payment_method, $excluded_methods, true );
			}
		);
	}
}
