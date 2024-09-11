<?php
/**
 * Class WC_Payments_Account
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Core\Server\Request\Get_Account;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\Update_Account;
use WCPay\Exceptions\API_Exception;
use WCPay\Logger;
use WCPay\Database_Cache;

/**
 * Class handling any account connection functionality
 */
class WC_Payments_Account {

	// ACCOUNT_OPTION is only used in the supporting dev tools plugin, it can be removed once everyone has upgraded.
	const ACCOUNT_OPTION                                        = 'wcpay_account_data';
	const ONBOARDING_DISABLED_TRANSIENT                         = 'wcpay_on_boarding_disabled';
	const ONBOARDING_STARTED_TRANSIENT                          = 'wcpay_on_boarding_started';
	const ONBOARDING_STATE_TRANSIENT                            = 'wcpay_stripe_onboarding_state';
	const EMBEDDED_KYC_IN_PROGRESS_OPTION                       = 'wcpay_onboarding_embedded_kyc_in_progress';
	const ERROR_MESSAGE_TRANSIENT                               = 'wcpay_error_message';
	const INSTANT_DEPOSITS_REMINDER_ACTION                      = 'wcpay_instant_deposit_reminder';
	const TRACKS_EVENT_ACCOUNT_CONNECT_START                    = 'wcpay_account_connect_start';
	const TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START   = 'wcpay_account_connect_wpcom_connection_start';
	const TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_SUCCESS = 'wcpay_account_connect_wpcom_connection_success';
	const TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_FAILURE = 'wcpay_account_connect_wpcom_connection_failure';
	const TRACKS_EVENT_ACCOUNT_CONNECT_FINISHED                 = 'wcpay_account_connect_finished';
	const TRACKS_EVENT_KYC_REMINDER_MERCHANT_RETURNED           = 'wcpay_kyc_reminder_merchant_returned';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Cache util for managing the account data
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Action scheduler service
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	private $action_scheduler_service;

	/**
	 * WC_Payments_Onboarding_Service instance for working with onboarding business logic
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	private $onboarding_service;

	/**
	 * WC_Payments_Redirect_Service instance for handling redirects business logic
	 *
	 * @var WC_Payments_Redirect_Service
	 */
	private $redirect_service;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      Payments API client.
	 * @param Database_Cache                       $database_cache           Database cache util.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service Action scheduler service.
	 * @param WC_Payments_Onboarding_Service       $onboarding_service       Onboarding service.
	 * @param WC_Payments_Redirect_Service         $redirect_service         Redirect service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		Database_Cache $database_cache,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		WC_Payments_Onboarding_Service $onboarding_service,
		WC_Payments_Redirect_Service $redirect_service
	) {
		$this->payments_api_client      = $payments_api_client;
		$this->database_cache           = $database_cache;
		$this->action_scheduler_service = $action_scheduler_service;
		$this->onboarding_service       = $onboarding_service;
		$this->redirect_service         = $redirect_service;
	}

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Add admin init hooks.
		// Our onboarding handling comes first.
		add_action( 'admin_init', [ $this, 'maybe_handle_onboarding' ] );
		add_action( 'admin_init', [ $this, 'maybe_activate_woopay' ] );
		// Second, handle redirections based on context.
		add_action( 'admin_init', [ $this, 'maybe_redirect_after_plugin_activation' ], 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		add_action( 'admin_init', [ $this, 'maybe_redirect_by_get_param' ], 12 ); // Run this after the redirect to onboarding logic.
		// Third, handle page redirections.
		add_action( 'admin_init', [ $this, 'maybe_redirect_from_settings_page' ], 15 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_from_onboarding_wizard_page' ], 15 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_from_connect_page' ], 15 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_from_overview_page' ], 15 );

		// Add handlers for inbox notes and reminders.
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'handle_instant_deposits_inbox_note' ] );
		add_action( 'woocommerce_payments_account_refreshed', [ $this, 'handle_loan_approved_inbox_note' ] );
		add_action( self::INSTANT_DEPOSITS_REMINDER_ACTION, [ $this, 'handle_instant_deposits_inbox_reminder' ] );

		// Add all other hooks.
		add_filter( 'allowed_redirect_hosts', [ $this, 'allowed_redirect_hosts' ] );
		add_action( 'jetpack_site_registered', [ $this, 'clear_cache' ] );
		add_action( 'updated_option', [ $this, 'possibly_update_wcpay_account_locale' ], 10, 3 );
		add_action( 'woocommerce_woocommerce_payments_updated', [ $this, 'clear_cache' ] );
	}

	/**
	 * Wipes the account data option, forcing to re-fetch the account status from WP.com.
	 */
	public function clear_cache() {
		$this->database_cache->delete( Database_Cache::ACCOUNT_KEY );
	}

	/**
	 * Return connected account ID
	 *
	 * @return string|null Account ID if connected, null if not connected or on error
	 */
	public function get_stripe_account_id() {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			return null;
		}

		return $account['account_id'];
	}

	/**
	 * Gets public key for the connected account
	 *
	 * @param bool $is_test true to get the test key, false otherwise.
	 *
	 * @return string|null public key if connected, null if not connected.
	 */
	public function get_publishable_key( $is_test ) {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			return null;
		}

		if ( $is_test ) {
			return $account['test_publishable_key'];
		}

		return $account['live_publishable_key'];
	}

	/**
	 * Determine if the store has a working Jetpack connection.
	 *
	 * @return bool Whether the Jetpack connection is established and working or not.
	 */
	public function has_working_jetpack_connection(): bool {
		return $this->payments_api_client->is_server_connected() && $this->payments_api_client->has_server_connection_owner();
	}

	/**
	 * Check if there is meaningful data in the WooPayments account cache.
	 *
	 * It bypasses WPCOM/Jetpack connection check, the cache expiry check and only checks if the account_id is present.
	 *
	 * @return boolean Whether there is account data.
	 */
	public function has_account_data(): bool {
		$account_data = $this->database_cache->get( Database_Cache::ACCOUNT_KEY );
		if ( ! empty( $account_data['account_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the account is connected, assumes the value of $on_error on server error.
	 *
	 * @param bool $on_error Value to return on server error, defaults to false.
	 *
	 * @return bool True if the account is connected, false otherwise, $on_error on error.
	 */
	public function is_stripe_connected( bool $on_error = false ): bool {
		try {
			return $this->try_is_stripe_connected();
		} catch ( Exception $e ) {
			return $on_error;
		}
	}

	/**
	 * Checks if the account is connected, throws on server error.
	 *
	 * @return bool      True if the account is connected, false otherwise.
	 * @throws Exception Throws exception when unable to detect connection status.
	 */
	public function try_is_stripe_connected(): bool {
		$account = $this->get_cached_account_data();
		if ( false === $account ) {
			throw new Exception( esc_html__( 'Failed to detect connection status', 'woocommerce-payments' ) );
		}

		// The empty array indicates that account is not connected yet.
		return [] !== $account;
	}

	/**
	 * Checks if the account is valid.
	 *
	 * This means:
	 * - it's connected (i.e. we have account data)
	 * - has submitted details (i.e. is not partially onboarded)
	 * - has valid card_payments capability status (requested, pending_verification, active and other valid ones).
	 *
	 * Card_payments capability is crucial for account to function properly. If it is unrequested, we shouldn't show
	 * any other options for the merchants since it'll lead to various errors.
	 *
	 * @see https://github.com/Automattic/woocommerce-payments/issues/5275
	 *
	 * @return bool True if the account is a valid Stripe account, false otherwise.
	 */
	public function is_stripe_account_valid(): bool {
		$account = $this->get_cached_account_data();
		// The account is disconnected or we failed to get the account data.
		if ( empty( $account ) ) {
			return false;
		}

		// The account is partially onboarded.
		if ( empty( $account['details_submitted'] ) ) {
			return false;
		}

		// The account doesn't have the minimum required capabilities.
		if ( ! isset( $account['capabilities']['card_payments'] )
			|| 'unrequested' === $account['capabilities']['card_payments'] ) {
			return false;
		}

		// The account is valid.
		return true;
	}

	/**
	 * Checks if the account has been rejected, assumes the value of false on any account retrieval error.
	 * Returns false if the account is not connected.
	 *
	 * @return bool True if the account is connected and rejected, false otherwise or on error.
	 */
	public function is_account_rejected(): bool {
		if ( ! $this->is_stripe_connected() ) {
			return false;
		}

		$account = $this->get_cached_account_data();
		return strpos( $account['status'] ?? '', 'rejected' ) === 0;
	}

	/**
	 * Checks if the account is under review, assumes the value of false on any account retrieval error.
	 * Returns false if the account is not connected.
	 *
	 * @return bool
	 */
	public function is_account_under_review(): bool {
		if ( ! $this->is_stripe_connected() ) {
			return false;
		}

		$account = $this->get_cached_account_data();
		return 'under_review' === $account['status'];
	}

	/**
	 * Checks if the account "details_submitted" flag is true.
	 * This is a proxy for telling if an account has completed onboarding.
	 * If the "details_submitted" flag is false, it means that the account has not
	 * yet finished the initial KYC.
	 *
	 * @return boolean True if the account is connected and details are not submitted, false otherwise.
	 */
	public function is_details_submitted(): bool {
		$account = $this->get_cached_account_data();

		$details_submitted = $account['details_submitted'] ?? false;
		return true === $details_submitted;
	}

	/**
	 * Gets the account status data for rendering on the settings page.
	 *
	 * @return array An array containing the status data, or [ 'error' => true ] on error or no connected account.
	 */
	public function get_account_status_data(): array {
		$account = $this->get_cached_account_data();

		if ( empty( $account ) ) {
			// empty means no account. This data should not be used when the account is not connected.
			return [
				'error' => true,
			];
		}

		if ( ! isset( $account['status'], $account['payments_enabled'] ) ) {
			// return an error if any of the account data is missing.
			return [
				'error' => true,
			];
		}

		return [
			'email'                 => $account['email'] ?? '',
			'country'               => $account['country'] ?? Country_Code::UNITED_STATES,
			'status'                => $account['status'],
			'created'               => $account['created'] ?? '',
			'testDrive'             => $account['is_test_drive'] ?? false,
			'paymentsEnabled'       => $account['payments_enabled'],
			'detailsSubmitted'      => $account['details_submitted'] ?? true,
			'deposits'              => $account['deposits'] ?? [],
			'currentDeadline'       => $account['current_deadline'] ?? false,
			'pastDue'               => $account['has_overdue_requirements'] ?? false,
			// Test-drive accounts don't have access to the Stripe dashboard.
			'accountLink'           => empty( $account['is_test_drive'] ) ? $this->get_login_url() : false,
			'hasSubmittedVatData'   => $account['has_submitted_vat_data'] ?? false,
			'requirements'          => [
				'errors' => $account['requirements']['errors'] ?? [],
			],
			'progressiveOnboarding' => [
				'isEnabled'            => $account['progressive_onboarding']['is_enabled'] ?? false,
				'isComplete'           => $account['progressive_onboarding']['is_complete'] ?? false,
				'tpv'                  => (int) ( $account['progressive_onboarding']['tpv'] ?? 0 ),
				'firstTransactionDate' => $account['progressive_onboarding']['first_transaction_date'] ?? null,
			],
			'fraudProtection'       => [
				'declineOnAVSFailure' => $account['fraud_mitigation_settings']['avs_check_enabled'] ?? null,
				'declineOnCVCFailure' => $account['fraud_mitigation_settings']['cvc_check_enabled'] ?? null,
			],
		];
	}

	/**
	 * Gets the account statement descriptor for rendering on the settings page.
	 *
	 * @return string Account statement descriptor.
	 */
	public function get_statement_descriptor(): string {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['statement_descriptor'] ) ? $account['statement_descriptor'] : '';
	}

	/**
	 * Gets the account statement descriptor for rendering on the settings page.
	 *
	 * @return string Account statement descriptor.
	 */
	public function get_statement_descriptor_kanji(): string {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['statement_descriptor_kanji'] ) ? $account['statement_descriptor_kanji'] : '';
	}

	/**
	 * Gets the account statement descriptor for rendering on the settings page.
	 *
	 * @return string Account statement descriptor.
	 */
	public function get_statement_descriptor_kana(): string {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['statement_descriptor_kana'] ) ? $account['statement_descriptor_kana'] : '';
	}

	/**
	 * Gets the business name.
	 *
	 * @return string Business profile name.
	 */
	public function get_business_name(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['business_profile']['name'] ) ? $account['business_profile']['name'] : '';
	}

	/**
	 * Gets the business url.
	 *
	 * @return string Business profile url.
	 */
	public function get_business_url(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['business_profile']['url'] ) ? $account['business_profile']['url'] : '';
	}

	/**
	 * Gets the business support address.
	 *
	 * @return array Business profile support address.
	 */
	public function get_business_support_address(): array {
		$account = $this->get_cached_account_data();
		return isset( $account['business_profile']['support_address'] ) ? $account['business_profile']['support_address'] : [];
	}

	/**
	 * Gets the business support email.
	 *
	 * @return string Business profile support email.
	 */
	public function get_business_support_email(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['business_profile']['support_email'] ) ? $account['business_profile']['support_email'] : '';
	}

	/**
	 * Gets the business support phone.
	 *
	 * @return string Business profile support phone.
	 */
	public function get_business_support_phone(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['business_profile']['support_phone'] ) ? $account['business_profile']['support_phone'] : '';
	}

	/**
	 * Gets the branding logo.
	 *
	 * @return string branding logo.
	 */
	public function get_branding_logo(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['branding']['logo'] ) ? $account['branding']['logo'] : '';
	}

	/**
	 * Gets the branding icon.
	 *
	 * @return string branding icon.
	 */
	public function get_branding_icon(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['branding']['icon'] ) ? $account['branding']['icon'] : '';
	}

	/**
	 * Gets the branding primary color.
	 *
	 * @return string branding primary color.
	 */
	public function get_branding_primary_color(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['branding']['primary_color'] ) ? $account['branding']['primary_color'] : '';
	}

	/**
	 * Gets the branding secondary color.
	 *
	 * @return string branding secondary color.
	 */
	public function get_branding_secondary_color(): string {
		$account = $this->get_cached_account_data();
		return isset( $account['branding']['secondary_color'] ) ? $account['branding']['secondary_color'] : '';
	}

	/**
	 * Gets the deposit schedule interval.
	 *
	 * @return string interval e.g. weekly, monthly.
	 */
	public function get_deposit_schedule_interval(): string {
		$account = $this->get_cached_account_data();
		return $account['deposits']['interval'] ?? '';
	}

	/**
	 * Gets the deposit schedule weekly anchor.
	 *
	 * @return string weekly anchor e.g. monday, tuesday.
	 */
	public function get_deposit_schedule_weekly_anchor(): string {
		$account = $this->get_cached_account_data();
		return $account['deposits']['weekly_anchor'] ?? '';
	}

	/**
	 * Gets the deposit schedule monthly anchor.
	 *
	 * @return int|null monthly anchor e.g. 1, 2.
	 */
	public function get_deposit_schedule_monthly_anchor() {
		$account = $this->get_cached_account_data();
		return ! empty( $account['deposits']['monthly_anchor'] ) ? $account['deposits']['monthly_anchor'] : null;
	}

	/**
	 * Gets the number of days payments are delayed for.
	 *
	 * @return int|null e.g. 2, 7.
	 */
	public function get_deposit_delay_days() {
		$account = $this->get_cached_account_data();
		return $account['deposits']['delay_days'] ?? null;
	}

	/**
	 * Gets the deposit status
	 *
	 * @return string  e.g. disabled, blocked, enabled.
	 */
	public function get_deposit_status(): string {
		$account = $this->get_cached_account_data();
		return $account['deposits']['status'] ?? '';
	}

	/**
	 * Gets the deposit restrictions
	 *
	 * @return string  e.g. not_blocked, blocked, schedule locked.
	 */
	public function get_deposit_restrictions(): string {
		$account = $this->get_cached_account_data();
		return $account['deposits']['restrictions'] ?? '';
	}

	/**
	 * Gets whether the account has completed the deposit waiting period.
	 *
	 * @return bool
	 */
	public function get_deposit_completed_waiting_period(): bool {
		$account = $this->get_cached_account_data();
		return $account['deposits']['completed_waiting_period'] ?? false;
	}

	/**
	 * Get card present eligible flag account
	 *
	 * @return bool
	 */
	public function is_card_present_eligible(): bool {
		$account = $this->get_cached_account_data();
		return $account['card_present_eligible'] ?? false;
	}

	/**
	 * Get has account connected readers flag
	 *
	 * @return bool
	 */
	public function has_card_readers_available(): bool {
		$account = $this->get_cached_account_data();
		return $account['has_card_readers_available'] ?? false;
	}

	/**
	 * Gets the current account fees for rendering on the settings page.
	 *
	 * @return array Fees.
	 */
	public function get_fees(): array {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['fees'] ) ? $account['fees'] : [];
	}

	/**
	 * Get the progressive onboarding details needed on the frontend.
	 *
	 * @return array Progressive Onboarding details.
	 */
	public function get_progressive_onboarding_details(): array {
		$account = $this->get_cached_account_data();
		return [
			'isEnabled'                   => $account['progressive_onboarding']['is_enabled'] ?? false,
			'isComplete'                  => $account['progressive_onboarding']['is_complete'] ?? false,
			'isNewFlowEnabled'            => WC_Payments_Utils::should_use_new_onboarding_flow(),
			'isEligibilityModalDismissed' => get_option( WC_Payments_Onboarding_Service::ONBOARDING_ELIGIBILITY_MODAL_OPTION, false ),
		];
	}

	/**
	 * Determine whether Progressive Onboarding is in progress for this account.
	 *
	 * @return boolean
	 */
	public function is_progressive_onboarding_in_progress(): bool {
		$account = $this->get_cached_account_data();
		return ( $account['progressive_onboarding']['is_enabled'] ?? false )
			&& ! ( $account['progressive_onboarding']['is_complete'] ?? false );
	}

	/**
	 * Gets the current account loan data for rendering on the settings pages.
	 *
	 * @return array loan data.
	 */
	public function get_capital() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['capital'] ) && ! empty( $account['capital'] ) ? $account['capital'] : [
			'loans'              => [],
			'has_active_loan'    => false,
			'has_previous_loans' => false,
		];
	}

	/**
	 * Gets the current account email for rendering on the settings page.
	 *
	 * @return string Email.
	 */
	public function get_account_email(): string {
		$account = $this->get_cached_account_data();
		return $account['email'] ?? '';
	}

	/**
	 * Gets the customer currencies supported by Stripe available for the account.
	 *
	 * @return array Currencies.
	 */
	public function get_account_customer_supported_currencies() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['customer_currencies']['supported'] ) ? $account['customer_currencies']['supported'] : [];
	}

	/**
	 * Gets the account live mode value.
	 *
	 * @return bool|null Account is_live value.
	 */
	public function get_is_live() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['is_live'] ) ? $account['is_live'] : null;
	}

	/**
	 * Checks if the request contains specific get param to redirect further, and redirects to the relevant link if so.
	 *
	 * Only admins are be able to perform this action. The redirect doesn't happen if the request is an AJAX request.
	 */
	public function maybe_redirect_by_get_param() {
		// Safety check to prevent non-admin users to be redirected to the view offer page.
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// This is an automatic redirection page, used to authenticate users that come from the KYC reminder email. For this reason
		// we're not using a nonce. The GET parameter accessed here is just to indicate that we should process the redirection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcpay-connect-redirect'] ) ) {
			$params = [
				'page' => 'wc-admin',
				'path' => '/payments/connect',
			];

			// We're not in the connect page, don't redirect.
			if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
				return;
			}

			$redirect_param = sanitize_text_field( wp_unslash( $_GET['wcpay-connect-redirect'] ) );

			// Let's record in Tracks merchants returning via the KYC reminder email.
			if ( 'initial' === $redirect_param ) {
				$offset      = 1;
				$description = 'initial';
			} elseif ( 'second' === $redirect_param ) {
				$offset      = 3;
				$description = 'second';
			} else {
				$follow_number = in_array( $redirect_param, [ '1', '2', '3', '4' ], true ) ? $redirect_param : '0';
				// offset is recorded in days, $follow_number maps to the week number.
				$offset      = (int) $follow_number * 7;
				$description = 'weekly-' . $follow_number;
			}

			$track_props = [
				'offset'      => $offset,
				'description' => $description,
			];
			$this->tracks_event( self::TRACKS_EVENT_KYC_REMINDER_MERCHANT_RETURNED, $track_props );

			$this->redirect_service->redirect_to_wcpay_connect( 'WCPAY_KYC_REMINDER' );
		}

		// This is an automatic redirection page, used to authenticate users that come from the capitcal offer email. For this reason
		// we're not using a nonce. The GET parameter accessed here is just to indicate that we should process the redirection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcpay-loan-offer'] ) ) {
			$this->redirect_service->redirect_to_capital_view_offer_page();
		}

		// This is an automatic redirection page, used to authenticate users that come from an email link. For this reason
		// we're not using a nonce. The GET parameter accessed here is just to indicate that we should process the redirection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wcpay-link-handler'] ) ) {
			// Get all request arguments to be forwarded and remove the link handler identifier.
			$args = $_GET;
			unset( $args['wcpay-link-handler'] );

			$this->redirect_service->redirect_to_account_link( $args );
		}
	}

	/**
	 * Proxy method that's called in other classes that have access to account (not redirect_service)
	 * to immediately redirect to the main "Welcome to WooPayments" onboarding page.
	 * Note that this function immediately ends the execution.
	 *
	 * @param string|null $error_message Optional error message to show in a notice.
	 */
	public function redirect_to_onboarding_welcome_page( $error_message = null ) {
		$this->redirect_service->redirect_to_connect_page( $error_message );
	}

	/**
	 * Checks if everything is in working order and redirects to the connect page if not.
	 *
	 * @return bool True if the redirection happened.
	 */
	public function maybe_redirect_after_plugin_activation(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$is_on_settings_page           = WC_Payments_Admin_Settings::is_current_page_settings();
		$should_redirect_to_onboarding = (bool) get_option( 'wcpay_should_redirect_to_onboarding', false );

		if (
			// If not loading the settings page...
			! $is_on_settings_page
			// ...and we have redirected before.
			&& ! $should_redirect_to_onboarding
		) {
			// Do not attempt to redirect again.
			return false;
		}

		if ( $should_redirect_to_onboarding ) {
			// Update the option. We try to redirect once and will not attempt to redirect again.
			update_option( 'wcpay_should_redirect_to_onboarding', false );
		}

		// If everything is in working order, don't redirect.
		if ( $this->has_working_jetpack_connection() && $this->is_stripe_account_valid() ) {
			return false;
		}

		// Redirect to Connect page.
		$this->redirect_service->redirect_to_connect_page(
			null,
			WC_Payments_Onboarding_Service::FROM_PLUGIN_ACTIVATION,
			[ 'source' => WC_Payments_Onboarding_Service::get_source() ]
		);

		return true;
	}

	/**
	 * Redirects WooPayments settings to the connect page when there is no account or an invalid account.
	 *
	 * Every WooPayments page except connect are already hidden, but merchants can still access
	 * it through WooCommerce settings.
	 *
	 * @return bool True if a redirection happened, false otherwise.
	 */
	public function maybe_redirect_from_settings_page(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'woocommerce_payments',
		];

		// We're not in the WooPayments settings page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		// If everything is NOT in good working condition, redirect to Payments Connect page.
		if ( ! $this->has_working_jetpack_connection() || ! $this->is_stripe_account_valid() ) {
			$this->redirect_service->redirect_to_connect_page(
				sprintf(
				/* translators: 1: WooPayments. */
					__( 'Please <b>complete your %1$s setup</b> to process transactions.', 'woocommerce-payments' ),
					'WooPayments'
				),
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_SETTINGS,
				[ 'source' => WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE ]
			);
			return true;
		}

		// Everything is OK, don't redirect.
		return false;
	}

	/**
	 * Redirects onboarding wizard page (payments/onboarding) to the overview page for accounts that have a valid Stripe account.
	 *
	 * Payments onboarding wizard page is already hidden for those who have a Stripe account connected,
	 * but merchants can still access it by clicking back in the browser tab.
	 *
	 * @return bool True if the redirection happened, false otherwise.
	 */
	public function maybe_redirect_from_onboarding_wizard_page(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/onboarding',
		];

		// We're not in the onboarding wizard page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		// Determine the original source from where the merchant entered the onboarding flow.
		$onboarding_source = WC_Payments_Onboarding_Service::get_source();

		// Prevent access to onboarding wizard if we don't have a working WPCOM/Jetpack connection.
		// Redirect back to the connect page with an error message.
		if ( ! $this->has_working_jetpack_connection() ) {
			$referer = sanitize_text_field( wp_get_raw_referer() );

			// Track unsuccessful Jetpack connection.
			if ( strpos( $referer, 'wordpress.com' ) ) {
				$this->tracks_event(
					self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_FAILURE,
					[
						'mode'   => WC_Payments_Onboarding_Service::is_test_mode_enabled() ? 'test' : 'live',
						// Capture the user source of the connection attempt originating page.
						// This is the same source that is used to track the onboarding flow origin.
						'source' => $onboarding_source,
					]
				);
			}

			$this->redirect_service->redirect_to_connect_page(
				sprintf(
				/* translators: %s: WooPayments */
					__( 'Please connect to WordPress.com to start using %s.', 'woocommerce-payments' ),
					'WooPayments'
				),
				WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
				[ 'source' => $onboarding_source ]
			);
			return true;
		}

		// We check it here after refreshing the cache, because merchant might have clicked back in browser (after Stripe KYC).
		// That will mean that no redirect from Stripe happened and user might be able to go through onboarding again if no webhook processed yet.
		// That might cause issues if user selects sandbox onboarding after live one.
		// Shouldn't be called with force disconnected option enabled, otherwise we'll get current account data.
		if ( ! WC_Payments_Utils::force_disconnected_enabled() ) {
			$this->refresh_account_data();
		}

		// Don't redirect merchants that have no Stripe account connected.
		if ( ! $this->is_stripe_connected() ) {
			return false;
		}

		// Merchants with an invalid Stripe account, need to go to the Stripe KYC, not our onboarding wizard.
		if ( ! $this->is_stripe_account_valid() ) {
			$this->redirect_service->redirect_to_connect_page(
				null,
				WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
				[ 'source' => $onboarding_source ]
			);
			return true;
		}

		$this->redirect_service->redirect_to_overview_page( WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD );
		return true;
	}

	/**
	 * Maybe redirects the connect page (payments/connect)
	 *
	 * We redirect to the overview page for stores that have a working Jetpack connection and a valid Stripe account.
	 *
	 * Note: Connect _page_ links are not the same as connect links.
	 *       Connect links are used to start/re-start/continue the onboarding flow and they are independent of
	 *       the WP dashboard page (based solely on request params).
	 *
	 * IMPORTANT: The logic should be kept in sync with the one in maybe_redirect_from_overview_page to avoid loops.
	 *
	 * @see self::maybe_redirect_from_overview_page() for the opposite redirection.
	 * @see self::maybe_handle_onboarding() for connect links handling.
	 *
	 * @return bool True if the redirection happened, false otherwise.
	 */
	public function maybe_redirect_from_connect_page(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		// We're not on the Connect page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		// There are certain cases where it is best to refresh the account data
		// to be sure we are dealing with the current account state on the Connect page:
		// - When the merchant is coming from the onboarding wizard it is best to refresh the account data because
		// the merchant might have started the embedded Stripe KYC.
		// - When the merchant is coming from the embedded KYC, definitely refresh the account data.
		// The account data shouldn't be refreshed with force disconnected option enabled.
		if ( ! WC_Payments_Utils::force_disconnected_enabled()
			&& in_array(
				WC_Payments_Onboarding_Service::get_from(),
				[
					WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD,
					WC_Payments_Onboarding_Service::FROM_ONBOARDING_KYC,
				],
				true
			) ) {

			$this->refresh_account_data();
		}

		// If everything is in good working condition, redirect to Payments Overview page.
		if ( $this->has_working_jetpack_connection() && $this->is_stripe_account_valid() ) {
			$this->redirect_service->redirect_to_overview_page( WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE );
			return true;
		}

		// Determine from where the merchant was directed to the Connect page.
		$from = WC_Payments_Onboarding_Service::get_from();

		// If the user came from the core Payments task list item,
		// we run an experiment to skip the Connect page
		// and go directly to the Jetpack connection flow and/or onboarding wizard.
		if ( WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK === $from
			&& WC_Payments_Utils::is_in_core_payments_task_onboarding_flow_treatment_mode() ) {

			// We use a connect link to allow our logic to determine what comes next:
			// the Jetpack connection setup and/or onboarding wizard (MOX).
			$this->redirect_service->redirect_to_wcpay_connect(
				// The next step should treat the merchant as coming from the Payments task list item,
				// not the Connect page.
				WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK,
				[
					'source' => WC_Payments_Onboarding_Service::get_source(),
				]
			);
			return true;
		}

		return false;
	}

	/**
	 * Redirects overview page (payments/overview) to the connect page for stores that
	 * don't have a working Jetpack connection or a valid connected Stripe account.
	 *
	 * IMPORTANT: The logic should be kept in sync with the one in maybe_redirect_from_connect_page to avoid loops.
	 *
	 * @see self::maybe_redirect_from_connect_page() for the opposite redirection.
	 * @see self::maybe_handle_onboarding() for connect links handling.
	 *
	 * @return bool True if the redirection happened, false otherwise.
	 */
	public function maybe_redirect_from_overview_page(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/overview',
		];

		// We're not on the Overview page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		// If everything is NOT in good working condition, redirect to Payments Connect page.
		if ( ! $this->has_working_jetpack_connection() || ! $this->is_stripe_account_valid() ) {
			$this->redirect_service->redirect_to_connect_page(
				sprintf(
				/* translators: 1: WooPayments. */
					__( 'Please <b>complete your %1$s setup</b> to process transactions.', 'woocommerce-payments' ),
					'WooPayments'
				),
				WC_Payments_Onboarding_Service::FROM_OVERVIEW_PAGE,
				[
					'test_mode' => ( ! empty( $_GET['test_mode'] ) && wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) ? 'true' : false,
					'source'    => WC_Payments_Onboarding_Service::get_source(),
				]
			);
			return true;
		}

		return false;
	}

	/**
	 * Filter function to add Stripe to the list of allowed redirect hosts
	 *
	 * @param array $hosts Array of allowed hosts.
	 *
	 * @return array Filtered allowed hosts
	 */
	public function allowed_redirect_hosts( $hosts ) {
		$hosts[] = 'connect.stripe.com';
		return $hosts;
	}

	/**
	 * Handle onboarding (login/init/redirect) routes
	 */
	public function maybe_handle_onboarding() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Determine what was the immediately previous step of the onboarding flow.
		$from = WC_Payments_Onboarding_Service::get_from();
		// Determine the original/initial place from where the merchant entered the onboarding flow.
		$onboarding_source = WC_Payments_Onboarding_Service::get_source();

		/**
		 * ==================
		 * Handle Stripe dashboard login links.
		 * ==================
		 */
		if ( isset( $_GET['wcpay-login'] ) && check_admin_referer( 'wcpay-login' ) ) {
			try {
				if ( $this->is_stripe_connected() && ! $this->is_details_submitted() ) {
					$args         = $_GET;
					$args['type'] = 'complete_kyc_link';

					// Allow progressive onboarding accounts to continue onboarding without payout collection.
					if ( $this->is_progressive_onboarding_in_progress() ) {
						$args['is_progressive_onboarding'] = $this->is_progressive_onboarding_in_progress() ?? false;
					}

					$this->redirect_service->redirect_to_account_link( $args );
				}

				// Clear account cache when generating Stripe dashboard's login link.
				$this->clear_cache();

				$this->redirect_service->redirect_to_login();
			} catch ( Exception $e ) {
				Logger::error( 'Failed redirect_to_login: ' . $e->getMessage() );

				$this->redirect_service->redirect_to_overview_page_with_error( [ 'wcpay-login-error' => '1' ] );
			}

			// We should not reach this point as we either redirect to the Stripe dashboard
			// or to the Payments > Overview page with an error message.
			return;
		}

		/**
		 * ==================
		 * Handle the Jetpack re-connection in case of missing connection owner.
		 *
		 * @see self::get_wpcom_reconnect_url()
		 * ==================
		 */
		if ( isset( $_GET['wcpay-reconnect-wpcom'] ) && check_admin_referer( 'wcpay-reconnect-wpcom' ) ) {
			// Track the Jetpack connection start.
			$this->tracks_event(
				self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START,
				[
					'is_reconnect' => true,
					'from'         => $from,
				]
			);

			$this->payments_api_client->start_server_connection( WC_Payments_Admin_Settings::get_settings_url() );
			return;
		}

		/**
		 * ==================
		 * Handle connect links that trigger either the onboarding flow start or its continuation.
		 *
		 * "Onboarding flow" = the Jetpack connection + onboarding wizard + Stripe KYC.
		 *
		 * IMPORTANT: Connect links are NOT THE SAME as Connect page links:
		 *    - Connect links are used to start/re-start/continue the onboarding flow.
		 *      Connect links are WP admin links with the `wcpay-connect` GET parameter and a 'wcpay-connect' action nonce.
		 *      Connect links imply actions being taken on the merchant store and account setup (hence the need to be protected by a nonce).
		 *    - Connect page links are just the WC admin URL of the Connect page (page=wc-admin&path=/payments/connect).
		 *
		 * The BUSINESS LOGIC of handling connect links, in the order of priority:
		 *
		 * 0. Make changes to the account data if needed (e.g. reset account, disable test mode onboarding)
		 *    as instructed by the GET params.
		 *    0.1 If we reset the account -> redirect to CONNECT PAGE
		 * 1. Returning from the WPCOM/Jetpack connection screen.
		 *      1.1 SUCCESSFUL connection
		 *          1.1.1 NO Stripe account connected -> redirect to ONBOARDING WIZARD
		 *          1.1.2 Stripe account connected -> redirect to OVERVIEW PAGE
		 *      1.2 UNSUCCESSFUL connection -> redirect to CONNECT PAGE with ERROR message
		 * 2. Working WPCOM/Jetpack connection and fully onboarded Stripe account -> redirect to OVERVIEW PAGE
		 * 3. Specific `from` places -> redirect to CONNECT PAGE regardless of the account status
		 * 4. NO [working] WPCOM/Jetpack connection:
		 *    Initialize the WPCOM registration and:
		 *      4.1 On SUCCESS -> redirect to WPCOM/Jetpack connection screen (Calypso).
		 *      4.2 On ERROR -> redirect to CONNECT PAGE with ERROR message
		 * 5. Working WPCOM/Jetpack connection and:
		 *    5.1 If NO Stripe account connected:
		 *         5.1.1 If we are setting up a test drive account and the auto-start onboarding is enabled,
		 *               we redirect to the CONNECT PAGE to let the JS logic orchestrate the Stripe account creation.
		 *         5.1.2 If we come from the ONBOARDING WIZARD:
		 *                Initialize the Stripe account and:
		 *                5.1.1.1 On SUCCESS -> redirect to STRIPE KYC
		 *                5.1.1.2 On existing account -> redirect to OVERVIEW PAGE
		 *                5.1.1.3 On ERROR -> redirect to CONNECT PAGE with ERROR message
		 *         5.1.3 All other cases -> redirect to ONBOARDING WIZARD
		 *    5.2 If PARTIALLY onboarded Stripe account connected -> redirect to STRIPE KYC
		 *    5.3 If fully onboarded Stripe account connected -> redirect to OVERVIEW PAGE
		 *
		 * This logic is so complex because we use connect links as a catch-all place to
		 * handle everything and anything related to the WooPayments account setup. It reduces the complexity on the
		 * "outer-edges" of our ecosystem (e.g. Woo core, emails, etc.) and centralizes the handling in one place.
		 *
		 * IMPORTANT: Whenever we decide to change the business logic we should UPDATE THE COMMENT ABOVE!!!
		 *            Do NOT let this comment become stale/out-of-sync!!!
		 * ==================
		 */
		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			$wcpay_connect_param         = sanitize_text_field( wp_unslash( $_GET['wcpay-connect'] ) );
			$incentive_id                = ! empty( $_GET['promo'] ) ? sanitize_text_field( wp_unslash( $_GET['promo'] ) ) : '';
			$progressive                 = ! empty( $_GET['progressive'] ) && 'true' === $_GET['progressive'];
			$collect_payout_requirements = ! empty( $_GET['collect_payout_requirements'] ) && 'true' === $_GET['collect_payout_requirements'];
			$create_test_drive_account   = ! empty( $_GET['test_drive'] ) && 'true' === $_GET['test_drive'];
			// There is no point in auto starting test drive onboarding if we are not in the test drive mode.
			$auto_start_test_drive_onboarding = $create_test_drive_account &&
												! empty( $_GET['auto_start_test_drive_onboarding'] ) &&
												'true' === $_GET['auto_start_test_drive_onboarding'];
			// We will onboard in test mode if the test_mode GET param is set, if we are creating a test drive account,
			// or if we are in dev mode.
			$should_onboard_in_test_mode = ( isset( $_GET['test_mode'] ) && wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) ||
											$create_test_drive_account ||
											WC_Payments::mode()->is_dev();

			// Hide menu notification badge upon starting setup.
			update_option( 'wcpay_menu_badge_hidden', 'yes' );

			// By default, the next step will be informed that the user came from the Connect page.
			$next_step_from = WC_Payments_Onboarding_Service::FROM_CONNECT_PAGE;

			// Default props we want to attach to onboarding Tracks events.
			$tracks_props = [
				'incentive' => $incentive_id,
				'mode'      => $should_onboard_in_test_mode ? 'test' : 'live',
				'from'      => $from,
				'source'    => $onboarding_source,
			];

			// Handle the return from Stripe KYC flow (via a connect link).
			// The state of the WPCOM/Jetpack connection should not matter - if we received the state data,
			// we need to try and capture it.
			if ( isset( $_GET['wcpay-state'] ) && isset( $_GET['wcpay-mode'] ) ) {
				$state = sanitize_text_field( wp_unslash( $_GET['wcpay-state'] ) );
				$mode  = sanitize_text_field( wp_unslash( $_GET['wcpay-mode'] ) );

				$this->finalize_connection(
					$state,
					$mode,
					[
						'from'                  => $from,
						'source'                => $onboarding_source,
						// Carry over some parameters as they may be used by our frontend logic.
						'wcpay-sandbox-success' => ! empty( $_GET['wcpay-sandbox-success'] ) ? 'true' : false,
						'test_drive_error'      => ! empty( $_GET['test_drive_error'] ) ? 'true' : false,
					]
				);
				return;
			}

			// Remove the previously stored onboarding state if the merchant wants to start a new onboarding session
			// or if we came back from Stripe with an error but no state.
			// This will allow us to avoid errors when finalizing the account connection.
			if ( ( ! empty( $_GET['wcpay-discard-started-onboarding'] ) && 'true' === $_GET['wcpay-discard-started-onboarding'] )
				|| ( WC_Payments_Onboarding_Service::FROM_STRIPE === $from && ! empty( $_GET['wcpay-connection-error'] ) ) ) {

				delete_transient( self::ONBOARDING_STATE_TRANSIENT );
				delete_option( self::EMBEDDED_KYC_IN_PROGRESS_OPTION );
			}

			// Make changes to account data as instructed by action GET params.
			// This needs to happen early because we need to make things "not OK" for the rest of the logic.
			if ( ! empty( $_GET['wcpay-reset-account'] ) && 'true' === $_GET['wcpay-reset-account'] ) {
				try {
					// Delete the currently Stripe connected account, in the onboarding mode we are currently in.
					$this->payments_api_client->delete_account( WC_Payments_Onboarding_Service::is_test_mode_enabled() );
				} catch ( API_Exception $e ) {
					// In case we fail to delete the account, log and redirect to the Overview page.
					Logger::error( 'Failed to delete account: ' . $e->getMessage() );

					$this->redirect_service->redirect_to_overview_page_with_error( [ 'wcpay-reset-account-error' => '1' ] );
					return;
				}

				$this->cleanup_on_account_reset();

				// When we reset the account we want to always go the Connect page. Redirect immediately!
				$this->redirect_service->redirect_to_connect_page(
					null,
					WC_Payments_Onboarding_Service::FROM_RESET_ACCOUNT,
					[ 'source' => $onboarding_source ]
				);
				return;
			} elseif ( ! empty( $_GET['wcpay-disable-onboarding-test-mode'] ) && 'true' === $_GET['wcpay-disable-onboarding-test-mode'] ) {
				// If the test mode onboarding is enabled:
				// - Delete the current account;
				// - Cleanup the gateway state for a fresh onboarding flow.
				// Otherwise, we are already using a live account and the request is invalid (it will be handled below,
				// in the "everything OK" scenario).
				if ( WC_Payments_Onboarding_Service::is_test_mode_enabled() ) {
					try {
						// Delete the currently connected Stripe account.
						$this->payments_api_client->delete_account( true );
					} catch ( API_Exception $e ) {
						// In case we fail to delete the account, log and carry on.
						Logger::error( 'Failed to delete account in test mode: ' . $e->getMessage() );
					}

					$this->cleanup_on_account_reset();
				}

				// Since we are moving from test to live, we will only onboard in test mode if we are in dev mode.
				// Otherwise, we will do a live onboarding.
				$should_onboard_in_test_mode = WC_Payments::mode()->is_dev();

				$next_step_from = WC_Payments_Onboarding_Service::FROM_TEST_TO_LIVE;
				// These from values are allowed to be passed through, when going from test to live.
				if ( in_array(
					$from,
					[
						WC_Payments_Onboarding_Service::FROM_SETTINGS,
						WC_Payments_Onboarding_Service::FROM_OVERVIEW_PAGE,
						WC_Payments_Onboarding_Service::FROM_GO_LIVE_TASK,
					],
					true
				) ) {
					$next_step_from = $from;
				}
			}

			// Handle the return from the WPCOM/Jetpack connection screens.
			// The merchant either completed the connection or failed. We handle both scenarios.
			// Note: this should be handled early since the Jetpack connection is the first requirement
			// in our onboarding stack.
			if ( isset( $_GET['wcpay-connect-jetpack-success'] ) ) {
				// If the merchant failed to set up the WPCOM/Jetpack connection,
				// we redirect them back to the Connect page with an error message.
				if ( ! $this->has_working_jetpack_connection() ) {
					// Track unsuccessful Jetpack connection.
					$this->tracks_event(
						self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_FAILURE,
						// Use the DB stored onboarding mode, not the one determined from the current request params.
						array_merge( $tracks_props, [ 'mode' => WC_Payments_Onboarding_Service::is_test_mode_enabled() ? 'test' : 'live' ] )
					);

					$this->redirect_service->redirect_to_connect_page(
						sprintf(
						/* translators: %s: WooPayments */
							__( 'Connection to WordPress.com failed. Please connect to WordPress.com to start using %s.', 'woocommerce-payments' ),
							'WooPayments'
						),
						WC_Payments_Onboarding_Service::FROM_WPCOM_CONNECTION,
						[
							'source' => $onboarding_source,
						]
					);

					return;
				}

				// Track successful Jetpack connection.
				$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_SUCCESS, $tracks_props );

				// Always clear the account cache after establishing the Jetpack/WPCOM connection.
				// An account may already be available on our platform for this store.
				$this->clear_cache();
			}

			// Handle the "everything OK" scenario.
			// Handle this _after_ we've handled the actions that make things "not OK" (e.g. resetting account or
			// moving from test to live).
			// Payout requirement collection needs to bypass the "everything OK" scenario.
			if ( ! $collect_payout_requirements
				&& $this->has_working_jetpack_connection()
				&& $this->is_stripe_account_valid() ) {

				$this->redirect_service->redirect_to_overview_page(
					$from,
					[
						'source'                   => $onboarding_source,
						// Carry over some parameters as they may be used by our frontend logic.
						'wcpay-connection-success' => ! empty( $_GET['wcpay-connection-success'] ) ? '1' : false,
						'wcpay-sandbox-success'    => ! empty( $_GET['wcpay-sandbox-success'] ) ? 'true' : false,
						'test_drive_error'         => ! empty( $_GET['test_drive_error'] ) ? 'true' : false,
					]
				);
				return;
			}

			// Handle the specific from places that need to go to the Connect page first and start onboarding from there.
			if (
				in_array(
					$from,
					[
						WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_SETTINGS,
						WC_Payments_Onboarding_Service::FROM_STRIPE,
					],
					true
				)
				/**
				 * We are running an experiment to skip the Connect page for Payments Task flows.
				 * Only redirect to the Connect page if the user is not in the experiment's treatment mode.
				 *
				 * @see self::maybe_redirect_from_connect_page()
				 */
				|| ( WC_Payments_Onboarding_Service::FROM_WCADMIN_PAYMENTS_TASK === $from
					&& ! WC_Payments_Utils::is_in_core_payments_task_onboarding_flow_treatment_mode() )
				// This is a weird case, but it is best to handle it.
				|| ( WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD === $from && ! $this->has_working_jetpack_connection() )
			) {
				$this->redirect_service->redirect_to_connect_page(
					! empty( $_GET['wcpay-connection-error'] ) ? sprintf(
					/* translators: 1: WooPayments. */
						__( 'Please <b>complete your %1$s setup</b> to process transactions.', 'woocommerce-payments' ),
						'WooPayments'
					) : null,
					null, // Do not carry over the `from` value to avoid redirect loops.
					[
						'promo'                       => ! empty( $incentive_id ) ? $incentive_id : false,
						'progressive'                 => $progressive ? 'true' : false,
						'collect_payout_requirements' => $collect_payout_requirements ? 'true' : false,
						'source'                      => $onboarding_source,
					]
				);
				return;
			}

			// Track WooPayments onboarding (aka account connection) start.
			// We should not have a connected Stripe account. If we do, it means we are not at the very start,
			// but somewhere in between.
			// Exclude returns from the WPCOM/Jetpack connection.
			// This needs to happen _before_ we attempt to init the WPCOM/Jetpack connection!
			if ( ! isset( $_GET['wcpay-connect-jetpack-success'] ) && ! $this->is_stripe_connected() ) {
				$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_START, $tracks_props );
			}

			// First requirement: handle the WPCOM/Jetpack connection.
			// If there is a working one, we can proceed with the Stripe account handling.
			try {
				$this->maybe_init_jetpack_connection(
				// Carry over all the important GET params, so we have them after the Jetpack connection setup.
					add_query_arg(
						[
							'promo'                       => ! empty( $incentive_id ) ? $incentive_id : false,
							'progressive'                 => $progressive ? 'true' : false,
							'collect_payout_requirements' => $collect_payout_requirements ? 'true' : false,
							'test_mode'                   => $should_onboard_in_test_mode ? 'true' : false,
							'test_drive'                  => $create_test_drive_account ? 'true' : false,
							'auto_start_test_drive_onboarding' => $auto_start_test_drive_onboarding ? 'true' : false,
							'from'                        => WC_Payments_Onboarding_Service::FROM_WPCOM_CONNECTION,
							'source'                      => $onboarding_source,

						],
						self::get_connect_url( $wcpay_connect_param ) // Instruct Jetpack to return here (connect link).
					),
					$tracks_props
				);
			} catch ( API_Exception $e ) {
				Logger::error( 'Init Jetpack connection failed. ' . $e->getMessage() );
				$this->redirect_service->redirect_to_connect_page(
				/* translators: %s: error message. */
					sprintf( __( 'There was a problem connecting your store to WordPress.com: "%s"', 'woocommerce-payments' ), $e->getMessage() ),
					WC_Payments_Onboarding_Service::FROM_WPCOM_CONNECTION,
					[
						'source' => $onboarding_source,
					]
				);
				return;
			}

			// Handle the scenarios that need to point to the onboarding wizard before initializing the Stripe onboarding.
			// All other more specific scenarios should have been handled by this point.
			if ( ! $create_test_drive_account
				// When we come from the onboarding wizard we obviously don't want to go back to it!
				&& WC_Payments_Onboarding_Service::FROM_ONBOARDING_WIZARD !== $from
				&& ! $this->is_stripe_connected() ) {

				$this->redirect_service->redirect_to_onboarding_wizard(
					// When we redirect to the onboarding wizard, we carry over the `from`, if we have it.
					// This is because there is no interim step between the user clicking the connect link and the onboarding wizard.
					! empty( $from ) ? $from : $next_step_from,
					[
						'source' => $onboarding_source,
					]
				);
				return;
			}

			// Handle the Stripe account initialization and/or redirect to the Stripe KYC.
			// This is used at the end of our onboarding wizard (MOX) and whenever the merchant needs to
			// finish Stripe KYC verifications (like in the case of partially onboarded accounts).
			// In case everything is already OK and there is no need for Stripe KYC,
			// the merchant will get redirected to the Payments > Overview page.
			try {
				// Prevent duplicate requests to start the onboarding flow.
				if ( get_transient( self::ONBOARDING_STARTED_TRANSIENT ) ) {
					Logger::warning( 'Duplicate onboarding attempt detected.' );
					$this->redirect_service->redirect_to_connect_page(
						__( 'There was a duplicate attempt to initiate account setup. Please wait a few seconds and try again.', 'woocommerce-payments' )
					);
					return;
				}

				// If we are creating a test-drive account, we do things a little different.
				if ( $create_test_drive_account ) {
					// Since there should be no Stripe KYC needed, make sure we start with a clean state.
					delete_transient( self::ONBOARDING_STATE_TRANSIENT );
					delete_option( self::EMBEDDED_KYC_IN_PROGRESS_OPTION );

					// If we have the auto_start_test_drive_onboarding flag, we redirect to the Connect page
					// to let the JS logic take control and orchestrate things.
					if ( $auto_start_test_drive_onboarding ) {
						$this->redirect_service->redirect_to_connect_page(
							null,
							$from, // Carry over `from` since we are doing a short-circuit.
							[
								'promo'      => ! empty( $incentive_id ) ? $incentive_id : false,
								'test_drive' => 'true',
								'auto_start_test_drive_onboarding' => 'true', // This is critical.
								'test_mode'  => $should_onboard_in_test_mode ? 'true' : false,
								'source'     => $onboarding_source,
							]
						);
						return;
					}
				}

				// Check if there is already an onboarding flow started.
				if ( get_transient( self::ONBOARDING_STATE_TRANSIENT ) ) {
					// Carry over all relevant GET params to the confirmation URL.
					// We don't need to carry over reset account or test_to_live params because those actions
					// automatically discard any ongoing onboarding.
					// Also, do not carry over auto_start_test_drive_onboarding as we want the merchant to see the notice.
					$confirmation_url = add_query_arg(
						[
							'promo'                       => ! empty( $incentive_id ) ? $incentive_id : false,
							'progressive'                 => $progressive ? 'true' : false,
							'collect_payout_requirements' => $collect_payout_requirements ? 'true' : false,
							'test_drive'                  => $create_test_drive_account ? 'true' : false,
							'test_mode'                   => ( ! empty( $_GET['test_mode'] ) && wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) ? 'true' : false,
							'from'                        => $from, // Use the same from.
							'source'                      => $onboarding_source,
							'wcpay-discard-started-onboarding' => 'true',

						],
						self::get_connect_url( $wcpay_connect_param ) // Instruct Jetpack to return here (connect link).
					);
					$this->redirect_service->redirect_to_connect_page(
						sprintf(
						/* translators: 1: anchor opening markup 2: closing anchor markup */
							__( 'Another account setup session is already in progress. Please finish it or %1$sclick here to start again%2$s.', 'woocommerce-payments' ),
							'<a href="' . esc_url( $confirmation_url ) . '">',
							'</a>'
						)
					);
					return;
				}

				// Set a quickly expiring transient to avoid duplicate requests.
				// The duration should be sufficient for our platform to respond.
				// There is no danger in having this transient expire too late
				// because we delete it after we initiate the onboarding.
				set_transient( self::ONBOARDING_STARTED_TRANSIENT, true, MINUTE_IN_SECONDS );

				$redirect_to = $this->init_stripe_onboarding(
					$create_test_drive_account ? 'test_drive' : ( $should_onboard_in_test_mode ? 'test' : 'live' ),
					$wcpay_connect_param,
					[
						'promo'       => ! empty( $incentive_id ) ? $incentive_id : false,
						'progressive' => $progressive ? 'true' : false,
						'source'      => $onboarding_source,
						'from'        => WC_Payments_Onboarding_Service::FROM_STRIPE,
					]
				);

				delete_transient( self::ONBOARDING_STARTED_TRANSIENT );

				// Always clear the account cache after a Stripe onboarding init attempt.
				// This allows the merchant to use connect links to refresh its account cache, in case something is wrong.
				$this->clear_cache();

				// Make sure the redirect URL is safe.
				$redirect_to = wp_sanitize_redirect( $redirect_to );
				$redirect_to = wp_validate_redirect( $redirect_to );

				// When creating test-drive accounts,
				// reply with a JSON so the JS logic can pick it up and redirect the merchant.
				if ( $create_test_drive_account && ! empty( $redirect_to ) ) {
					wp_send_json_success( [ 'redirect_to' => $redirect_to ] );
				} else {
					// Redirect the user to where our Stripe onboarding instructed (or to our own embedded Stripe KYC).
					$this->redirect_service->redirect_to( $redirect_to );
				}
			} catch ( API_Exception $e ) {
				delete_transient( self::ONBOARDING_STARTED_TRANSIENT );

				// Always clear the account cache in case of errors.
				$this->clear_cache();

				Logger::error( 'Init Stripe onboarding failed. ' . $e->getMessage() );
				$this->redirect_service->redirect_to_connect_page(
					sprintf(
					/* translators: %s: WooPayments. */
						__( 'There was a problem setting up your %s account. Please try again.', 'woocommerce-payments' ),
						'WooPayments'
					),
					null,
					[
						'source' => $onboarding_source,
					]
				);
				return;
			}

			// Stop here when running unit tests.
			if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
				return;
			}

			// We should not reach this point as the merchant should be redirected to the proper place already.
			// But, as a failsafe, redirect to either the Payments > Overview page or the Connect page.
			Logger::warning( 'Doing the failsafe WooPayments connect link redirect.' );
			if ( $this->is_stripe_connected() && $this->has_working_jetpack_connection() ) {
				$this->redirect_service->redirect_to_overview_page();
			} else {
				$this->redirect_service->redirect_to_connect_page( null, null, [ 'source' => $onboarding_source ] );
			}
			return;
		}

		/**
		 * ==================
		 * Handle the redirect back from the Stripe KYC (proxied through our platform)
		 * when it didn't come through a connect link.
		 *
		 * @see self::finalize_connection()
		 * ==================
		 */
		if ( isset( $_GET['wcpay-state'] ) && isset( $_GET['wcpay-mode'] ) ) {
			$state = sanitize_text_field( wp_unslash( $_GET['wcpay-state'] ) );
			$mode  = sanitize_text_field( wp_unslash( $_GET['wcpay-mode'] ) );

			$this->finalize_connection(
				$state,
				$mode,
				[
					'from'   => $from,
					'source' => $onboarding_source,
				]
			);

			return;
		}
	}

	/**
	 * Sets things up for a fresh onboarding flow.
	 *
	 * @return void
	 */
	private function cleanup_on_account_reset() {
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', 'no' );
		$gateway->update_option( 'test_mode', 'no' );

		update_option( '_wcpay_onboarding_stripe_connected', [] );
		update_option( WC_Payments_Onboarding_Service::TEST_MODE_OPTION, 'no' );

		// Discard any ongoing onboarding session.
		delete_transient( self::ONBOARDING_STATE_TRANSIENT );
		delete_transient( self::ONBOARDING_STARTED_TRANSIENT );
		delete_option( self::EMBEDDED_KYC_IN_PROGRESS_OPTION );
		delete_transient( 'woopay_enabled_by_default' );

		// Clear the cache to avoid stale data.
		$this->clear_cache();
	}

	/**
	 * Get Stripe login url.
	 *
	 * @return string Stripe account login url.
	 */
	private function get_login_url() {
		return add_query_arg( // nosemgrep: audit.php.wp.security.xss.query-arg -- no user input data used.
			[
				'wcpay-login' => '1',
				'_wpnonce'    => wp_create_nonce( 'wcpay-login' ),
			]
		);
	}

	/**
	 * Get connect url.
	 *
	 * @param string $wcpay_connect_from Optional. A value to inform the connect logic where the user came from.
	 *                                   It will allow us to adapt behavior and/or UX (e.g. redirect to different places).
	 *
	 * @return string Connect URL.
	 */
	public static function get_connect_url( $wcpay_connect_from = '1' ) {
		// The minimal params that make a connect URL.
		$url_params = [
			'wcpay-connect' => $wcpay_connect_from,
			'_wpnonce'      => wp_create_nonce( 'wcpay-connect' ),
		];

		// Attach our best guess of the onboarding source.
		$url_params['source'] = WC_Payments_Onboarding_Service::get_source();

		return add_query_arg( $url_params, admin_url( 'admin.php' ) );
	}

	/**
	 * Payments task page url
	 *
	 * @deprecated 7.8.0
	 *
	 * @return string payments task page url
	 */
	public static function get_payments_task_page_url() {
		wc_deprecated_function( __FUNCTION__, '7.8.0' );

		return add_query_arg(
			[
				'page'   => 'wc-admin',
				'task'   => 'payments',
				'method' => 'wcpay',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get Connect page url.
	 *
	 * @return string
	 */
	public static function get_connect_page_url(): string {
		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/connect',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get overview page url
	 *
	 * @return string overview page url
	 */
	public static function get_overview_page_url() {
		return add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => '/payments/overview',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Checks if the current page is overview page
	 *
	 * @return boolean
	 */
	public static function is_overview_page() {
		return isset( $_GET['path'] ) && '/payments/overview' === $_GET['path'];
	}

	/**
	 * Get WPCOM/Jetpack reconnect url, for use in case of missing connection owner.
	 *
	 * @return string WPCOM/Jetpack reconnect url.
	 */
	public static function get_wpcom_reconnect_url() {
		return admin_url(
			add_query_arg(
				[
					'wcpay-reconnect-wpcom' => '1',
					'_wpnonce'              => wp_create_nonce( 'wcpay-reconnect-wpcom' ),
				],
				'admin.php'
			)
		);
	}


	/**
	 * Has on-boarding been disabled?
	 *
	 * @return boolean
	 */
	public static function is_on_boarding_disabled() {
		// If the transient isn't set at all, we'll get false indicating that the server hasn't informed us that
		// on-boarding has been disabled (i.e. it's enabled as far as we know).
		return get_transient( self::ONBOARDING_DISABLED_TRANSIENT );
	}

	/**
	 * Starts the Jetpack connection flow if it's not already fully connected.
	 *
	 * @param string $return_url Where to redirect the user back to.
	 * @param array  $tracks_props Additional properties to attach to the Tracks event.
	 *
	 * @throws API_Exception If there was an error when registering the site on WP.com.
	 */
	private function maybe_init_jetpack_connection( string $return_url, array $tracks_props ) {
		// Nothing to do if we already have a working Jetpack connection.
		if ( $this->has_working_jetpack_connection() ) {
			return;
		}

		// Track the Jetpack connection start.
		$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START, $tracks_props );

		// Ensure our success param is present.
		$return_url = add_query_arg( [ 'wcpay-connect-jetpack-success' => '1' ], $return_url );

		$this->payments_api_client->start_server_connection( $return_url );
	}

	/**
	 * Builds the URL to return the user to after the Jetpack/Onboarding flow.
	 *
	 * @param string $wcpay_connect_from - Constant to decide where the user should be returned to after connecting.
	 *
	 * @return string
	 */
	private function get_onboarding_return_url( string $wcpay_connect_from ): string {
		$is_from_subscription_product_publish = preg_match(
			'/WC_SUBSCRIPTIONS_PUBLISH_PRODUCT_(\d+)/',
			$wcpay_connect_from,
			$matches
		);

		if ( 1 === $is_from_subscription_product_publish ) {
			return add_query_arg( // nosemgrep: audit.php.wp.security.xss.query-arg -- specific admin url passed in.
				[ 'wcpay-subscriptions-onboarded' => '1' ],
				get_edit_post_link( $matches[1], 'url' )
			);
		}

		// Custom return URL for the connect page based on the source.
		// Defaults to using a connect link - this way we will route the user to the correct place.
		switch ( $wcpay_connect_from ) {
			case 'WC_SUBSCRIPTIONS_TABLE':
				return admin_url( add_query_arg( [ 'post_type' => 'shop_subscription' ], 'edit.php' ) );
			default:
				return static::get_connect_url();
		}
	}

	/**
	 * Get the URL to the embedded onboarding KYC page.
	 *
	 * @param array $additional_args Additional query args to add to the URL.
	 *
	 * @return string
	 */
	private function get_onboarding_kyc_url( array $additional_args = [] ): string {
		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/onboarding/kyc',
		];

		$params = array_merge( $params, $additional_args );

		return admin_url( add_query_arg( $params, 'admin.php' ) );
	}

	/**
	 * Initializes the onboarding flow by fetching the URL from the API and redirecting to it.
	 *
	 * @param string $setup_mode         The onboarding setup mode. It should only be `live`, `test`, or `test_drive`.
	 *                                   On invalid value, it will default to `live`.
	 * @param string $wcpay_connect_from Where the user should be returned to after connecting.
	 * @param array  $additional_args    Additional query args to add to the return URL.
	 *
	 * @return string The URL to redirect the user to. Empty string if there is no URL to redirect to.
	 * @throws API_Exception
	 */
	private function init_stripe_onboarding( string $setup_mode, string $wcpay_connect_from, array $additional_args = [] ): string {
		if ( ! in_array( $setup_mode, [ 'live', 'test', 'test_drive' ], true ) ) {
			$setup_mode = 'live';
		}
		// Flags to enable progressive onboarding and collect payout requirements.
		$progressive                 = ! empty( $_GET['progressive'] ) && 'true' === $_GET['progressive'];
		$collect_payout_requirements = ! empty( $_GET['collect_payout_requirements'] ) && 'true' === $_GET['collect_payout_requirements'];

		// Make sure the onboarding test mode DB flag is set.
		WC_Payments_Onboarding_Service::set_test_mode( 'live' !== $setup_mode );

		if ( ! $collect_payout_requirements ) {
			// Clear onboarding related account options if this is an initial onboarding attempt.
			WC_Payments_Onboarding_Service::clear_account_options();
		} else {
			// Since we assume user has already either gotten here from the eligibility modal,
			// or has already dismissed it, we should set the modal as dismissed so it doesn't display again.
			WC_Payments_Onboarding_Service::set_onboarding_eligibility_modal_dismissed();
		}

		// If we are in the middle of an embedded onboarding, go to the KYC page.
		// In this case, we don't need to generate a return URL from Stripe, and we
		// can rely on the JS logic to generate the session.
		// Currently under feature flag.
		if ( WC_Payments_Features::is_embedded_kyc_enabled() && $this->onboarding_service->is_embedded_kyc_in_progress() ) {
			// We want to carry over the connect link from value because with embedded KYC
			// there is no interim step for the user.
			$additional_args['from'] = WC_Payments_Onboarding_Service::get_from();

			return $this->get_onboarding_kyc_url( $additional_args );
		}

		// Else, go on with the normal onboarding redirect logic.
		$return_url = $this->get_onboarding_return_url( $wcpay_connect_from );
		if ( ! empty( $additional_args ) ) {
			$return_url = add_query_arg( $additional_args, $return_url );
		}

		$self_assessment_data = isset( $_GET['self_assessment'] ) ? wc_clean( wp_unslash( $_GET['self_assessment'] ) ) : [];
		if ( 'test_drive' === $setup_mode ) {
			// If we get to the overview page, we want to show the success message.
			$return_url = add_query_arg( 'wcpay-sandbox-success', 'true', $return_url );
		} elseif ( 'test' === $setup_mode ) {
			// If we get to the overview page, we want to show the success message.
			$return_url = add_query_arg( 'wcpay-sandbox-success', 'true', $return_url );
		}

		$site_data = [
			'site_username' => wp_get_current_user()->user_login,
			'site_locale'   => get_locale(),
		];

		$user_data    = $this->onboarding_service->get_onboarding_user_data();
		$account_data = $this->onboarding_service->get_account_data( $setup_mode, $self_assessment_data );

		$onboarding_data = $this->payments_api_client->get_onboarding_data(
			'live' === $setup_mode,
			$return_url,
			$site_data,
			WC_Payments_Utils::array_filter_recursive( $user_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
			WC_Payments_Utils::array_filter_recursive( $account_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
			WC_Payments_Onboarding_Service::get_actioned_notes(),
			$progressive,
			$collect_payout_requirements
		);

		// If an account already exists for this site and/or there is no need for KYC verifications, we're done.
		// Our platform will respond with a `false` URL in this case.
		if ( isset( $onboarding_data['url'] ) && false === $onboarding_data['url'] ) {
			// Set the gateway options.
			$gateway = WC_Payments::get_gateway();
			$gateway->update_option( 'enabled', 'yes' );
			$gateway->update_option( 'test_mode', empty( $onboarding_data['is_live'] ) ? 'yes' : 'no' );

			// Store a state after completing KYC for tracks. This is stored temporarily in option because
			// user might not have agreed to TOS yet.
			update_option( '_wcpay_onboarding_stripe_connected', [ 'is_existing_stripe_account' => true ] );

			// Clean up any existing onboarding state.
			delete_transient( self::ONBOARDING_STATE_TRANSIENT );
			delete_option( self::EMBEDDED_KYC_IN_PROGRESS_OPTION );

			return add_query_arg(
				[ 'wcpay-connection-success' => '1' ],
				$return_url
			);
		}

		// We have an account that needs to be verified (has a URL to redirect the merchant to).
		// Store the relevant onboarding data.
		set_transient( 'woopay_enabled_by_default', isset( $onboarding_data['woopay_enabled_by_default'] ) ?? false, DAY_IN_SECONDS );
		// Save the onboarding state for a day.
		// This is used to verify the state when finalizing the onboarding and connecting the account.
		// On finalizing the onboarding, the transient gets deleted.
		set_transient( self::ONBOARDING_STATE_TRANSIENT, $onboarding_data['state'] ?? '', DAY_IN_SECONDS );

		return (string) ( $onboarding_data['url'] ?? '' );
	}

	/**
	 * Activates WooPay when visiting the KYC success page and woopay_enabled_by_default transient is set to true.
	 */
	public function maybe_activate_woopay() {
		if ( ! isset( $_GET['wcpay-connection-success'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( get_transient( 'woopay_enabled_by_default' ) ) {
			WC_Payments::get_gateway()->update_is_woopay_enabled( true );
			delete_transient( 'woopay_enabled_by_default' );
		}
	}

	/**
	 * Handle the finalization of an embedded onboarding. This includes updating the cache, setting the gateway mode,
	 * tracking the event, and redirecting the user to the overview page.
	 *
	 * @param string $mode            The mode in which the account was created. Either 'test' or 'live'.
	 * @param array  $additional_args Additional query args to add to the redirect URLs.
	 *
	 * @return array Returns whether the operation was successful, along with the URL params to handle the redirect.
	 */
	public function finalize_embedded_connection( string $mode, array $additional_args = [] ): array {
		// Clear the account cache.
		$this->clear_cache();

		// Set the gateway options.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', 'yes' );
		$gateway->update_option( 'test_mode', 'live' !== $mode ? 'yes' : 'no' );

		// Store a state after completing KYC for tracks. This is stored temporarily in option because
		// user might not have agreed to TOS yet.
		update_option( '_wcpay_onboarding_stripe_connected', [ 'is_existing_stripe_account' => false ] );

		// Track account connection finish.
		$event_properties = [
			'mode'      => 'test' === $mode ? 'test' : 'live',
			'incentive' => ! empty( $additional_args['promo'] ) ? sanitize_text_field( $additional_args['promo'] ) : '',
			'from'      => ! empty( $additional_args['from'] ) ? sanitize_text_field( $additional_args['from'] ) : '',
			'source'    => ! empty( $additional_args['source'] ) ? sanitize_text_field( $additional_args['source'] ) : '',
		];

		$this->tracks_event(
			self::TRACKS_EVENT_ACCOUNT_CONNECT_FINISHED,
			$event_properties
		);

		$params = $additional_args;

		$params['wcpay-connection-success'] = '1';
		return [
			'success' => true,
			'params'  => $params,
		];
	}

	/**
	 * Once the API redirects back to the site after the onboarding flow, verifies the parameters and stores the data.
	 *
	 * @param string $state           Secret string.
	 * @param string $mode            Mode in which this account has been created. Either 'test' or 'live'.
	 * @param array  $additional_args Additional query args to add to redirect URLs.
	 */
	private function finalize_connection( string $state, string $mode, array $additional_args = [] ) {
		// If the state is not the same as the one we stored, something went wrong.
		// Reject the connection and redirect to the Connect page for another try (this doesn't mean the merchant will
		// need to set up a new account, just that it will need to do another round trip of server requests,
		// with a new secret, etc.).
		if ( get_transient( self::ONBOARDING_STATE_TRANSIENT ) !== $state ) {
			$this->redirect_service->redirect_to_connect_page(
				__( 'There was a problem processing your account data. Please try again.', 'woocommerce-payments' ),
				null, // No need to specify any from as we will carry over the once in the additional args, if present.
				$additional_args
			);
			return;
		}
		// The states match, so we can delete the stored one.
		delete_transient( self::ONBOARDING_STATE_TRANSIENT );

		// Clear the account cache.
		$this->clear_cache();

		// Set the gateway options.
		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', 'yes' );
		$gateway->update_option( 'test_mode', 'live' !== $mode ? 'yes' : 'no' );

		// Store a state after completing KYC for tracks. This is stored temporarily in option because
		// user might not have agreed to TOS yet.
		update_option( '_wcpay_onboarding_stripe_connected', [ 'is_existing_stripe_account' => false ] );

		// Track account connection finish.
		$incentive_id = ! empty( $_GET['promo'] ) ? sanitize_text_field( wp_unslash( $_GET['promo'] ) ) : '';
		$tracks_props = [
			'incentive' => $incentive_id,
			'mode'      => 'live' !== $mode ? 'test' : 'live',
			'from'      => $additional_args['from'] ?? '',
			'source'    => $additional_args['source'] ?? '',
		];
		$this->tracks_event(
			self::TRACKS_EVENT_ACCOUNT_CONNECT_FINISHED,
			$tracks_props
		);

		$params = $additional_args;
		if ( ! empty( $_GET['wcpay-connection-error'] ) ) {
			// If we get this parameter, but we have a valid state, it means the merchant left KYC early and didn't finish it.
			// While we do have an account, it is not yet valid. We need to redirect them back to the connect page.
			$params['wcpay-connection-error'] = '1';

			$this->redirect_service->redirect_to_connect_page( '', WC_Payments_Onboarding_Service::FROM_STRIPE, $params );
			return;
		}

		$params['wcpay-connection-success'] = '1';

		$this->redirect_service->redirect_to_overview_page( WC_Payments_Onboarding_Service::FROM_STRIPE, $params );
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return array|bool Account data or false if failed to retrieve account data.
	 */
	public function get_cached_account_data( bool $force_refresh = false ) {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return [];
		}

		$refreshed = false;

		$account = $this->database_cache->get_or_add(
			Database_Cache::ACCOUNT_KEY,
			function () {
				try {
					// Since we're about to call the server again, clear out the onboarding disabled flag.
					// We can let the code below re-create it if the server tells us onboarding is still disabled.
					delete_transient( self::ONBOARDING_DISABLED_TRANSIENT );

					$request  = Get_Account::create();
					$response = $request->send();
					$account  = $response->to_array();

				} catch ( API_Exception $e ) {
					if ( 'wcpay_account_not_found' === $e->get_error_code() ) {
						// Special case - detect account not connected and cache it.
						$account = [];
					} elseif ( 'wcpay_on_boarding_disabled' === $e->get_error_code() ) {
						// Special case - detect account not connected and onboarding disabled. This will get updated the
						// next time we call the server for account information, but just in case we set the expiry time for
						// this setting an hour longer than the account details transient.
						$account = [];
						set_transient( self::ONBOARDING_DISABLED_TRANSIENT, true, 2 * HOUR_IN_SECONDS );
					} else {
						// Return false to signal account retrieval error.
						return false;
					}
				}

				if ( ! $this->is_valid_cached_account( $account ) ) {
					return false;
				}

				return $account;
			},
			[ $this, 'is_valid_cached_account' ],
			$force_refresh,
			$refreshed
		);

		if ( null === $account ) {
			return false;
		}

		if ( $refreshed ) {
			// Allow us to tie in functionality to an account refresh.
			do_action( 'woocommerce_payments_account_refreshed', $account );
		}

		return $account;
	}

	/**
	 * Updates the cached account data.
	 *
	 * @param string $property Property to update.
	 * @param mixed  $data     Data to update.
	 *
	 * @return void
	 */
	public function update_account_data( $property, $data ) {
		$account_data = $this->database_cache->get( Database_Cache::ACCOUNT_KEY );

		$account_data[ $property ] = is_array( $data ) ? array_merge( $account_data[ $property ] ?? [], $data ) : $data;

		$this->database_cache->add( Database_Cache::ACCOUNT_KEY, $account_data );
	}

	/**
	 * Refetches account data and returns the fresh data.
	 *
	 * @return array|bool|string Either the new account data or false if unavailable.
	 */
	public function refresh_account_data() {
		return $this->get_cached_account_data( true );
	}

	/**
	 * Checks if the cached account can be used in the current plugin state.
	 *
	 * @param bool|string|array $account cached account data.
	 *
	 * @return bool True if the cached account is valid.
	 */
	public function is_valid_cached_account( $account ) {
		// null/false means no account has been cached.
		if ( null === $account || false === $account ) {
			return false;
		}

		// Non-array values are not expected.
		if ( ! is_array( $account ) ) {
			return false;
		}

		// Empty array - special value to indicate that there's no account connected.
		if ( empty( $account ) ) {
			return true;
		}

		// Live accounts are always valid.
		if ( $account['is_live'] ) {
			return true;
		}

		// Handle test accounts.

		// Fix test mode enabled DB state starting with the account data.
		// These two should be in sync when in test mode onboarding.
		// This is a weird case that shouldn't happen under normal circumstances.
		if ( ! WC_Payments_Onboarding_Service::is_test_mode_enabled() && WC_Payments::mode()->is_dev() ) {
			Logger::warning( 'Test mode account, account onboarding is NOT in test mode, but the plugin is in dev mode. Enabling test mode onboarding.' );
			WC_Payments_Onboarding_Service::set_test_mode( true );
		}

		// Test accounts are valid only when onboarding in test mode.
		if ( WC_Payments_Onboarding_Service::is_test_mode_enabled() ) {
			return true;
		}

		return false;
	}

	/**
	 * Updates Stripe account settings.
	 *
	 * @param array $stripe_account_settings Settings to update.
	 *
	 * @return null|WP_Error Account update result.
	 *
	 * @throws Exception
	 */
	public function update_stripe_account( $stripe_account_settings ) {
		try {
			if ( ! $this->settings_changed( $stripe_account_settings ) ) {
				Logger::info( 'Skip updating account settings. Nothing is changed.' );
				return;
			}

			$request         = Update_Account::from_account_settings( $stripe_account_settings );
			$response        = $request->send();
			$updated_account = $response->to_array();

			$this->database_cache->add( Database_Cache::ACCOUNT_KEY, $updated_account );
		} catch ( Exception $e ) {
			Logger::error( 'Failed to update Stripe account ' . $e );

			return new WP_Error( 'wcpay_failed_to_update_stripe_account', $e->getMessage() );
		}
	}

	/**
	 * Checks if account settings changed.
	 *
	 * @param array $changes Account settings changes.
	 *
	 * @return bool True if at least one parameter value is changed.
	 */
	private function settings_changed( $changes = [] ) {
		$account = $this->database_cache->get( Database_Cache::ACCOUNT_KEY );

		// Consider changes as valid if we don't have cached account data.
		if ( ! $this->is_valid_cached_account( $account ) ) {
			return true;
		}

		$diff = array_diff_assoc( $changes, $account );
		return ! empty( $diff );
	}

	/**
	 * Updates the WCPay Account locale with the current site language (WPLANG option).
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $new_value   The new option value.
	 */
	public function possibly_update_wcpay_account_locale( $option_name, $old_value, $new_value ) {
		if ( 'WPLANG' === $option_name && $this->is_stripe_connected() ) {
			try {
				$account_settings = [
					'locale' => $new_value ? $new_value : 'en_US',
				];

				$request         = Update_Account::from_account_settings( $account_settings );
				$response        = $request->send();
				$updated_account = $response->to_array();

				$this->database_cache->add( Database_Cache::ACCOUNT_KEY, $updated_account );
			} catch ( Exception $e ) {
				Logger::error( __( 'Failed to update Account locale. ', 'woocommerce-payments' ) . $e );
			}
		}
	}


	/**
	 * Retrieves the latest ToS agreement for the account.
	 *
	 * @return array|null Either the agreement or null if unavailable.
	 */
	public function get_latest_tos_agreement() {
		$account = $this->get_cached_account_data();
		return ! empty( $account ) && isset( $account['latest_tos_agreement'] )
			? $account['latest_tos_agreement']
			: null;
	}

	/**
	 * Gets the account country.
	 *
	 * @return string Country.
	 */
	public function get_account_country() {
		$account = $this->get_cached_account_data();
		return $account['country'] ?? Country_Code::UNITED_STATES;
	}

	/**
	 * Gets the account default currency.
	 *
	 * @return string Currency code in lowercase.
	 */
	public function get_account_default_currency(): string {
		$account = $this->get_cached_account_data();
		return $account['store_currencies']['default'] ?? strtolower( Currency_Code::UNITED_STATES_DOLLAR );
	}

	/**
	 * Handles adding a note if the merchant is eligible for Instant Deposits.
	 *
	 * @param array $account The account data.
	 *
	 * @return void
	 */
	public function handle_instant_deposits_inbox_note( $account ) {
		if ( empty( $account ) ) {
			return;
		}

		if ( ! $this->is_instant_deposits_eligible( $account ) ) {
			return;
		}

		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
		WC_Payments_Notes_Instant_Deposits_Eligible::possibly_add_note();
		$this->maybe_add_instant_deposit_note_reminder();
	}

	/**
	 * Handles adding a note if the merchant has an loan approved.
	 *
	 * @param array $account The account data.
	 *
	 * @return void
	 */
	public function handle_loan_approved_inbox_note( $account ) {
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-loan-approved.php';

		// If the account cache is empty, don't try to create an inbox note.
		if ( empty( $account ) ) {
			return;
		}

		// Delete the loan note when the user doesn't have an active loan.
		if ( ! isset( $account['capital']['has_active_loan'] ) || ! $account['capital']['has_active_loan'] ) {
			WC_Payments_Notes_Loan_Approved::possibly_delete_note();
			return;
		}

		// Get the loan summary.
		try {
			$request = Request::get( WC_Payments_API_Client::CAPITAL_API . '/active_loan_summary' );
			$request->assign_hook( 'wcpay_get_active_loan_summary_request' );
			$loan_details = $request->send();

		} catch ( API_Exception $ex ) {
			return;
		}

		WC_Payments_Notes_Loan_Approved::set_loan_details( $loan_details );
		WC_Payments_Notes_Loan_Approved::possibly_add_note();
	}

	/**
	 * Handles removing note about merchant Instant Deposits eligibility.
	 * Hands off to handle_instant_deposits_inbox_note to add the new note.
	 *
	 * @return void
	 */
	public function handle_instant_deposits_inbox_reminder() {
		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-instant-deposits-eligible.php';
		WC_Payments_Notes_Instant_Deposits_Eligible::possibly_delete_note();
		$this->handle_instant_deposits_inbox_note( $this->get_cached_account_data() );
	}

	/**
	 * Handles adding scheduled action for the Instant Deposit note reminder.
	 *
	 * @return void
	 */
	public function maybe_add_instant_deposit_note_reminder() {
		$action_hook = self::INSTANT_DEPOSITS_REMINDER_ACTION;

		if ( $this->action_scheduler_service->pending_action_exists( $action_hook ) ) {
			return;
		}

		$reminder_time = time() + ( 90 * DAY_IN_SECONDS );
		$this->action_scheduler_service->schedule_job( $reminder_time, $action_hook );
	}

	/**
	 * Checks to see if the account is eligible for Instant Deposits.
	 *
	 * @param array $account The account data.
	 *
	 * @return bool
	 */
	private function is_instant_deposits_eligible( array $account ): bool {
		if ( empty( $account['instant_deposits_eligible'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get card testing protection eligible flag account
	 *
	 * @return bool
	 */
	public function is_card_testing_protection_eligible(): bool {
		$account = $this->get_cached_account_data();
		return $account['card_testing_protection_eligible'] ?? false;
	}


	/**
	 * Gets tracking info from the server and caches it.
	 *
	 * It's only available after connecting to Jetpack, so we should only cache it after that.
	 *
	 * @param bool $force_refresh Whether to force a refresh of the tracking info.
	 *
	 * @return array|null Array of tracking info or null if unavailable.
	 */
	public function get_tracking_info( $force_refresh = false ): ?array {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return null;
		}

		return $this->database_cache->get_or_add(
			Database_Cache::TRACKING_INFO_KEY,
			function (): array {
				return $this->payments_api_client->get_tracking_info();
			},
			'is_array', // We expect an array back from the cache.
			$force_refresh
		);
	}

	/**
	 * Send a Tracks event.
	 *
	 * By default Woo adds `url`, `blog_lang`, `blog_id`, `store_id`, `products_count`, and `wc_version`
	 * properties to every event.
	 *
	 * @param string $name       The event name.
	 * @param array  $properties Optional. The event custom properties.
	 *
	 * @return void
	 */
	private function tracks_event( string $name, array $properties = [] ) {
		// Add default properties to every event.
		$properties = array_merge(
			$properties,
			[
				'is_test_mode'      => WC_Payments::mode()->is_test(),
				'jetpack_connected' => $this->payments_api_client->is_server_connected(),
				'wcpay_version'     => WCPAY_VERSION_NUMBER,
				'woo_country_code'  => WC()->countries->get_base_country(),
			],
			$this->get_tracking_info() ?? []
		);

		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		// We're not using Tracker::track_admin() here because
		// WC_Pay\record_tracker_events() is never triggered due to the redirects.
		wc_admin_record_tracks_event( $name, $properties );

		Logger::info( 'Tracks event: ' . $name . ' with data: ' . wp_json_encode( WC_Payments_Utils::redact_array( $properties, [ 'woo_country_code' ] ) ) );
	}

	/**
	 * Get the all-time total payment volume.
	 *
	 * @return int The all-time total payment volume, or null if not available.
	 */
	public function get_lifetime_total_payment_volume(): int {
		$account = $this->get_cached_account_data();
		return (int) ! empty( $account ) && isset( $account['lifetime_total_payment_volume'] ) ? $account['lifetime_total_payment_volume'] : 0;
	}
}
