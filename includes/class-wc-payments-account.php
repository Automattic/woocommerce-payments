<?php
/**
 * Class WC_Payments_Account
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\Notes\DataStore;
use Automattic\WooCommerce\Admin\Notes\Note;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Core\Server\Request\Get_Account;
use WCPay\Core\Server\Request\Get_Account_Capital_Link;
use WCPay\Core\Server\Request\Get_Account_Login_Data;
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
	const ON_BOARDING_DISABLED_TRANSIENT                        = 'wcpay_on_boarding_disabled';
	const ON_BOARDING_STARTED_TRANSIENT                         = 'wcpay_on_boarding_started';
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
	 * WC_Payments_Session_Service instance for working with session information
	 *
	 * @var WC_Payments_Session_Service
	 */
	private $session_service;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client               $payments_api_client      Payments API client.
	 * @param Database_Cache                       $database_cache           Database cache util.
	 * @param WC_Payments_Action_Scheduler_Service $action_scheduler_service Action scheduler service.
	 * @param WC_Payments_Session_Service          $session_service          Session service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		Database_Cache $database_cache,
		WC_Payments_Action_Scheduler_Service $action_scheduler_service,
		WC_Payments_Session_Service $session_service
	) {
		$this->payments_api_client      = $payments_api_client;
		$this->database_cache           = $database_cache;
		$this->action_scheduler_service = $action_scheduler_service;
		$this->session_service          = $session_service;
	}

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Add admin init hooks.
		add_action( 'admin_init', [ $this, 'maybe_handle_onboarding' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_onboarding' ], 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_wcpay_connect' ], 12 ); // Run this after the redirect to onboarding logic.
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_capital_offer' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_to_server_link' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_settings_to_connect_or_overview' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_onboarding_flow_to_overview' ] );
		add_action( 'admin_init', [ $this, 'maybe_redirect_onboarding_flow_to_connect' ] );
		add_action( 'admin_init', [ $this, 'maybe_activate_woopay' ] );

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
	 * Checks if the account is valid: which means it's connected and has valid card_payments capability status (requested, pending_verification, active and other valid ones).
	 * Card_payments capability is crucial for account to function properly. If it is unrequested, we shouldn't show
	 * any other options for the merchants since it'll lead to various errors.
	 *
	 * @see https://github.com/Automattic/woocommerce-payments/issues/5275
	 *
	 * @return bool True if the account have valid stripe account, false otherwise.
	 */
	public function is_stripe_account_valid(): bool {
		if ( ! $this->is_stripe_connected() ) {
			return false;
		}

		$account = $this->get_cached_account_data();

		if ( ! isset( $account['capabilities']['card_payments'] ) ) {
			return false;
		}

		return 'unrequested' !== $account['capabilities']['card_payments'];
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
			'paymentsEnabled'       => $account['payments_enabled'],
			'detailsSubmitted'      => $account['details_submitted'] ?? true,
			'deposits'              => $account['deposits'] ?? [],
			'depositsStatus'        => $account['deposits']['status'] ?? $account['deposits_status'] ?? '',
			'currentDeadline'       => $account['current_deadline'] ?? false,
			'pastDue'               => $account['has_overdue_requirements'] ?? false,
			'accountLink'           => $this->get_login_url(),
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
	public function get_fees() {
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
	 * Checks if the request is for the Capital view offer redirection page, and redirects to the offer if so.
	 *
	 * Only admins are be able to perform this action. The redirect doesn't happen if the request is an AJAX request.
	 * This method will end execution after the redirect if the user requests and is allowed to view the loan offer.
	 */
	public function maybe_redirect_to_capital_offer() {
		if ( wp_doing_ajax() ) {
			return;
		}

		// Safety check to prevent non-admin users to be redirected to the view offer page.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// This is an automatic redirection page, used to authenticate users that come from the offer email. For this reason
		// we're not using a nonce. The GET parameter accessed here is just to indicate that we should process the redirection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wcpay-loan-offer'] ) ) {
			return;
		}

		$return_url  = static::get_overview_page_url();
		$refresh_url = add_query_arg( [ 'wcpay-loan-offer' => '' ], admin_url( 'admin.php' ) );

		try {
			$request = Get_Account_Capital_Link::create();
			$type    = 'capital_financing_offer';
			$request->set_type( $type );
			$request->set_return_url( $return_url );
			$request->set_refresh_url( $refresh_url );

			$capital_link = $request->send();
			$this->redirect_to( $capital_link['url'] );
		} catch ( Exception $e ) {
			$error_url = add_query_arg(
				[ 'wcpay-loan-offer-error' => '1' ],
				self::get_overview_page_url()
			);

			$this->redirect_to( $error_url );
		}
	}

	/**
	 * Checks if the request is for the server links handler, and redirects to the link if it's valid.
	 *
	 * Only admins are be able to perform this action. The redirect doesn't happen if the request is an AJAX request.
	 * This method will end execution after the redirect if the user is allowed to view the link and the link is valid.
	 */
	public function maybe_redirect_to_server_link() {
		if ( wp_doing_ajax() ) {
			return;
		}

		// Safety check to prevent non-admin users to be redirected to the view offer page.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// This is an automatic redirection page, used to authenticate users that come from an email link. For this reason
		// we're not using a nonce. The GET parameter accessed here is just to indicate that we should process the redirection.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wcpay-link-handler'] ) ) {
			return;
		}

		// Get all request arguments to be forwarded and remove the link handler identifier.
		$args = $_GET;
		unset( $args['wcpay-link-handler'] );

		$this->redirect_to_account_link( $args );
	}

	/**
	 * Function to immediately redirect to the account link.
	 *
	 * @param array $args The arguments to be sent with the link request.
	 */
	private function redirect_to_account_link( array $args ) {
		try {
			$link = $this->payments_api_client->get_link( $args );

			if ( isset( $args['type'] ) && 'complete_kyc_link' === $args['type'] && isset( $link['state'] ) ) {
				set_transient( 'wcpay_stripe_onboarding_state', $link['state'], DAY_IN_SECONDS );
			}

			$this->redirect_to( $link['url'] );
		} catch ( API_Exception $e ) {
			$error_url = add_query_arg(
				[ 'wcpay-server-link-error' => '1' ],
				self::get_overview_page_url()
			);

			$this->redirect_to( $error_url );
		}
	}

	/**
	 * Utility function to immediately redirect to the main "Welcome to WooPayments" onboarding page.
	 * Note that this function immediately ends the execution.
	 *
	 * @param string $error_message Optional error message to show in a notice.
	 */
	public function redirect_to_onboarding_welcome_page( $error_message = null ) {
		if ( isset( $error_message ) ) {
			set_transient( self::ERROR_MESSAGE_TRANSIENT, $error_message, 30 );
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];
		if ( count( $params ) === count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			// We are already in the onboarding page, do nothing.
			return;
		}

		wp_safe_redirect( admin_url( add_query_arg( $params, 'admin.php' ) ) );
		exit();
	}

	/**
	 * Checks if Stripe account is connected and redirects to the onboarding page if it is not.
	 *
	 * @return bool True if the redirection happened.
	 */
	public function maybe_redirect_to_onboarding() {
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

		$account = $this->get_cached_account_data();
		if ( false === $account ) {
			// Failed to retrieve account data. Exception is logged in http client.
			return false;
		}

		if ( $should_redirect_to_onboarding ) {
			// Update the option. If there's an account connected, we won't need to redirect in the future.
			// If not, we will redirect once and will not want to redirect again.
			update_option( 'wcpay_should_redirect_to_onboarding', false );
		}

		if ( ! empty( $account ) ) {
			// Do not redirect if connected.
			return false;
		}

		// Redirect directly to onboarding page if come from WC Admin task.
		$http_referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );
		if ( 0 < strpos( $http_referer, 'task=payments' ) ) {
			$this->redirect_to_onboarding_flow_page( WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK );
		}

		// Redirect if not connected.
		$this->redirect_to_onboarding_welcome_page();
		return true;
	}

	/**
	 * Redirects to the wcpay-connect URL, which then redirects to the KYC flow.
	 *
	 * This URL is used by the KYC reminder email. We can't take the merchant
	 * directly to the wcpay-connect URL because it's nonced, and the
	 * nonce will likely be expired by the time the user follows the link.
	 * That's why we need this middleman instead.
	 *
	 * @return bool True if the redirection happened, false otherwise.
	 */
	public function maybe_redirect_to_wcpay_connect(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		// We're not in the onboarding page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! isset( $_GET['wcpay-connect-redirect'] ) ) {
			return false;
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

		// Take the user to the 'wcpay-connect' URL.
		// We handle creating and redirecting to the account link there.
		$connect_url = add_query_arg(
			[
				'wcpay-connect' => '1',
				'_wpnonce'      => wp_create_nonce( 'wcpay-connect' ),
			],
			admin_url( 'admin.php' )
		);

		$this->redirect_to( $connect_url );
		return true;
	}

	/**
	 * Redirects WooPayments settings to the overview page for partially
	 * onboarded accounts, and the connect page when there is no account.
	 *
	 * Every WooPayments page except connect are already hidden, but merchants can still access
	 * it through WooCommerce settings.
	 *
	 * @return bool True if a redirection happened, false otherwise.
	 */
	public function maybe_redirect_settings_to_connect_or_overview(): bool {
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

		// Not able to establish Stripe connection, redirect to the Connect page.
		if ( ! $this->is_stripe_connected() ) {
			$this->redirect_to(
				admin_url(
					add_query_arg(
						[
							'page' => 'wc-admin',
							'path' => '/payments/connect',
						],
						'admin.php'
					)
				)
			);
			return true;
		}

		if ( $this->is_details_submitted() ) {
			// Account fully onboarded, don't redirect.
			return false;
		} else {
			// Account not yet fully onboarded so redirect to overview page.
			$this->redirect_to(
				admin_url(
					add_query_arg(
						[
							'page' => 'wc-admin',
							'path' => '/payments/overview',
						],
						'admin.php'
					)
				)
			);
		}

		return true;
	}

	/**
	 * Redirects onboarding flow page (payments/onboarding) to the overview page for accounts that have Stripe connected.
	 *
	 * Payments onboarding flow page is already hidden for those who has Stripe account connected, but merchants can still access
	 * it by clicking back in the browser tab.
	 *
	 * @return bool True if the redirection happened, false otherwise.
	 */
	public function maybe_redirect_onboarding_flow_to_overview(): bool {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/onboarding',
		];

		// We're not in the onboarding flow page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return false;
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

		$this->redirect_to(
			admin_url(
				add_query_arg(
					[
						'page' => 'wc-admin',
						'path' => '/payments/overview',
					],
					'admin.php'
				)
			)
		);

		return true;
	}

	/**
	 * Prevent access to onboarding flow if the server is not connected.
	 * Redirect back to the connect page with an error message.
	 *
	 * @return void
	 */
	public function maybe_redirect_onboarding_flow_to_connect(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/onboarding',
		];

		// We're not in the onboarding flow page, don't redirect.
		if ( count( $params ) !== count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Server is connected, don't redirect.
		if ( $this->payments_api_client->is_server_connected() ) {
			return;
		}

		$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );

		// Track unsuccessful Jetpack connection.
		if ( strpos( $referer, 'wordpress.com' ) ) {
			$this->tracks_event(
				self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_FAILURE,
				[ 'mode' => WC_Payments::mode()->is_test() ? 'test' : 'live' ]
			);
		}

		$this->redirect_to_onboarding_welcome_page(
			sprintf(
			/* translators: %s: WooPayments */
				__( 'Please connect to WordPress.com to start using %s.', 'woocommerce-payments' ),
				'WooPayments'
			)
		);
	}

	/**
	 * Filter function to add Stripe to the list of allowed redirect hosts
	 *
	 * @param array $hosts - array of allowed hosts.
	 *
	 * @return array allowed hosts
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

		if ( isset( $_GET['wcpay-login'] ) && check_admin_referer( 'wcpay-login' ) ) {
			try {
				if ( $this->is_stripe_connected() && ! $this->is_details_submitted() ) {
					$args         = $_GET;
					$args['type'] = 'complete_kyc_link';

					// Allow progressive onboarding accounts to continue onboarding without payout collection.
					if ( $this->is_progressive_onboarding_in_progress() ) {
						$args['is_progressive_onboarding'] = $this->is_progressive_onboarding_in_progress() ?? false;
					}

					$this->redirect_to_account_link( $args );
				}

				$this->redirect_to_login();
			} catch ( Exception $e ) {
				Logger::error( 'Failed redirect_to_login: ' . $e );

				wp_safe_redirect(
					add_query_arg(
						[ 'wcpay-login-error' => '1' ],
						self::get_overview_page_url()
					)
				);
				exit;
			}
			return;
		}

		if ( isset( $_GET['wcpay-reconnect-wpcom'] ) && check_admin_referer( 'wcpay-reconnect-wpcom' ) ) {
			// Track the Jetpack connection start.
			$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START );

			$this->payments_api_client->start_server_connection( WC_Payments_Admin_Settings::get_settings_url() );
			return;
		}

		if ( isset( $_GET['wcpay-connect'] ) && check_admin_referer( 'wcpay-connect' ) ) {
			$incentive              = ! empty( $_GET['promo'] ) ? sanitize_text_field( wp_unslash( $_GET['promo'] ) ) : '';
			$progressive            = ! empty( $_GET['progressive'] ) && 'true' === $_GET['progressive'];
			$create_builder_account = ! empty( $_GET['create_builder_account'] ) && 'true' === $_GET['create_builder_account'];

			// Track connection start.
			if ( ! isset( $_GET['wcpay-connect-jetpack-success'] ) ) {

				$test_mode        = isset( $_GET['test_mode'] ) ? boolval( wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) : false;
				$event_properties = [
					'incentive' => $incentive,
					'mode'      => $test_mode || WC_Payments::mode()->is_test() ? 'test' : 'live',
				];
				$this->tracks_event(
					self::TRACKS_EVENT_ACCOUNT_CONNECT_START,
					$event_properties
				);
			}

			$connect_page_source = WC_Payments_Onboarding_Service::get_source( (string) wp_get_referer(), $_GET );
			// Redirect to the onboarding flow page if the account is not onboarded otherwise to the overview page.
			// Builder accounts are handled below and redirected to Stripe KYC directly.
			if ( ! $create_builder_account && in_array(
				$connect_page_source,
				[
					WC_Payments_Onboarding_Service::SOURCE_WCADMIN_PAYMENT_TASK,
					WC_Payments_Onboarding_Service::SOURCE_WCPAY_CONNECT_PAGE,
					WC_Payments_Onboarding_Service::SOURCE_WCADMIN_SETTINGS_PAGE,
					WC_Payments_Onboarding_Service::SOURCE_WCADMIN_INCENTIVE_PAGE,
				],
				true
			) ) {
				// Redirect non-onboarded account to the onboarding flow, otherwise to payments overview page.
				if ( ! $this->is_stripe_connected() ) {
					$should_onboard_in_test_mode = isset( $_GET['test_mode'] ) ? boolval( wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) : false;
					if ( ! $should_onboard_in_test_mode && WC_Payments_Onboarding_Service::is_test_mode_enabled() ) {
						// If there is no test mode in the URL informing us to onboard in test mode,
						// but the onboarding test mode is enabled in our DB, we should disable it.
						// This is most likely a leftover from a previous onboarding attempt.
						WC_Payments_Onboarding_Service::set_test_mode( false );
					}

					$this->redirect_to_onboarding_flow_page( $connect_page_source );
				} else {
					// Accounts with Stripe account connected will be redirected to the overview page.
					$this->redirect_to( static::get_overview_page_url() );
				}
			}

			// Handle the flow for a builder moving from test to live.
			if ( WC_Payments_Onboarding_Service::SOURCE_WCPAY_SETUP_LIVE_PAYMENTS === $connect_page_source ) {
				$test_mode = WC_Payments_Onboarding_Service::is_test_mode_enabled();

				// Delete the account if the test mode is enabled otherwise it'll cause issues to onboard again.
				if ( $test_mode ) {
					$this->payments_api_client->delete_account( $test_mode );
				}

				// Set the test mode to false now that we are handling a real onboarding.
				WC_Payments_Onboarding_Service::set_test_mode( false );
				$this->redirect_to_onboarding_flow_page( $connect_page_source );
				return;
			}

			if ( WC_Payments_Onboarding_Service::SOURCE_WCPAY_RESET_ACCOUNT === $connect_page_source ) {
				$test_mode = WC_Payments_Onboarding_Service::is_test_mode_enabled() || WC_Payments::mode()->is_dev();

				// Delete the account.
				$this->payments_api_client->delete_account( $test_mode );
				$this->redirect_to_onboarding_flow_page( $connect_page_source );
				return;
			}

			// Hide menu notification badge upon starting setup.
			update_option( 'wcpay_menu_badge_hidden', 'yes' );

			if ( isset( $_GET['wcpay-connect-jetpack-success'] ) ) {
				if ( ! $this->payments_api_client->is_server_connected() ) {
					// Track unsuccessful Jetpack connection.
					$this->tracks_event(
						self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_FAILURE,
						$event_properties
					);

					$this->redirect_to_onboarding_welcome_page(
						sprintf(
						/* translators: %s: WooPayments */
							__( 'Connection to WordPress.com failed. Please connect to WordPress.com to start using %s.', 'woocommerce-payments' ),
							'WooPayments'
						)
					);

					return;
				}

				// Track successful Jetpack connection.
				$test_mode        = isset( $_GET['test_mode'] ) ? boolval( wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) : false;
				$event_properties = [
					'incentive' => $incentive,
					'mode'      => $test_mode || WC_Payments::mode()->is_test() ? 'test' : 'live',
				];
				$this->tracks_event(
					self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_SUCCESS,
					$event_properties
				);
			}

			$wcpay_connect_param = sanitize_text_field( wp_unslash( $_GET['wcpay-connect'] ) );

			try {
				$this->maybe_init_jetpack_connection(
					$wcpay_connect_param,
					[
						'promo'       => $incentive,
						'progressive' => $progressive,
					]
				);
			} catch ( Exception $e ) {
				$this->redirect_to_onboarding_welcome_page(
				/* translators: error message. */
					sprintf( __( 'There was a problem connecting this site to WordPress.com: "%s"', 'woocommerce-payments' ), $e->getMessage() )
				);
				return;
			}

			try {
				$this->init_stripe_onboarding(
					$wcpay_connect_param,
					[
						'promo'       => $incentive,
						'progressive' => $progressive,
					]
				);
			} catch ( Exception $e ) {
				Logger::error( 'Init Stripe onboarding flow failed. ' . $e );
				$this->redirect_to_onboarding_welcome_page(
					__( 'There was a problem redirecting you to the account connection page. Please try again.', 'woocommerce-payments' )
				);
			}
			return;
		}

		if (
			isset( $_GET['wcpay-state'] )
			&& isset( $_GET['wcpay-mode'] )
		) {
			$state = sanitize_text_field( wp_unslash( $_GET['wcpay-state'] ) );
			$mode  = sanitize_text_field( wp_unslash( $_GET['wcpay-mode'] ) );
			$this->finalize_connection( $state, $mode );
			return;
		}
	}

	/**
	 * Get Stripe login url
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
	 * Get Stripe connect url
	 *
	 * @see WC_Payments_Account::get_onboarding_return_url(). The $wcpay_connect_from param relies on this function returning the corresponding URL.
	 * @param string $wcpay_connect_from Optional. A page ID representing where the user should be returned to after connecting. Default is '1' - redirects back to the WC Payments overview page.
	 *
	 * @return string Stripe account login url.
	 */
	public static function get_connect_url( $wcpay_connect_from = '1' ) {
		return wp_nonce_url( add_query_arg( [ 'wcpay-connect' => $wcpay_connect_from ], admin_url( 'admin.php' ) ), 'wcpay-connect' );
	}

	/**
	 * Payments task page url
	 *
	 * @return string payments task page url
	 */
	public static function get_payments_task_page_url() {
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
		return get_transient( self::ON_BOARDING_DISABLED_TRANSIENT );
	}

	/**
	 * Calls wp_safe_redirect and exit.
	 *
	 * This method will end the execution immediately after the redirection.
	 *
	 * @param string $location The URL to redirect to.
	 */
	protected function redirect_to( $location ) {
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Starts the Jetpack connection flow if it's not already fully connected.
	 *
	 * @param string $wcpay_connect_from - where the user should be returned to after connecting.
	 * @param array  $additional_args    - additional query args to add to the return URL.
	 *
	 * @throws API_Exception If there was an error when registering the site on WP.com.
	 */
	private function maybe_init_jetpack_connection( $wcpay_connect_from, $additional_args = [] ) {
		$is_jetpack_fully_connected = $this->payments_api_client->is_server_connected() && $this->payments_api_client->has_server_connection_owner();
		if ( $is_jetpack_fully_connected ) {
			return;
		}

		// Track the Jetpack connection start.
		$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START );

		$redirect = add_query_arg(
			array_merge(
				[
					'wcpay-connect'                 => $wcpay_connect_from,
					'wcpay-connect-jetpack-success' => '1',
					'_wpnonce'                      => wp_create_nonce( 'wcpay-connect' ),
				],
				$additional_args
			),
			$this->get_onboarding_return_url( $wcpay_connect_from )
		);
		$this->payments_api_client->start_server_connection( $redirect );
	}

	/**
	 * For the connected account, fetches the login url from the API and redirects to it
	 */
	private function redirect_to_login() {
		// Clear account transient when generating Stripe dashboard's login link.
		$this->clear_cache();
		$redirect_url = static::get_overview_page_url();

		$request = Get_Account_Login_Data::create();
		$request->set_redirect_url( $redirect_url );

		$response   = $request->send();
		$login_data = $response->to_array();
		wp_safe_redirect( $login_data['url'] );
		exit;
	}

	/**
	 * Builds the URL to return the user to after the Jetpack/Onboarding flow.
	 *
	 * @param string $wcpay_connect_from - Constant to decide where the user should be returned to after connecting.
	 * @return string
	 */
	private function get_onboarding_return_url( $wcpay_connect_from ) {
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

		// If connection originated on the WCADMIN payment task page, return there.
		// else goto the overview page, since now it is GA (earlier it was redirected to plugin settings page).
		switch ( $wcpay_connect_from ) {
			case 'WCADMIN_PAYMENT_TASK':
				return static::get_payments_task_page_url();
			case 'WC_SUBSCRIPTIONS_TABLE':
				return admin_url( add_query_arg( [ 'post_type' => 'shop_subscription' ], 'edit.php' ) );
			default:
				return static::get_overview_page_url();
		}
	}

	/**
	 * Initializes the onboarding flow by fetching the URL from the API and redirecting to it.
	 *
	 * @param string $wcpay_connect_from - where the user should be returned to after connecting.
	 * @param array  $additional_args    - additional query args to add to the return URL.
	 */
	private function init_stripe_onboarding( $wcpay_connect_from, $additional_args = [] ) {
		if ( get_transient( self::ON_BOARDING_STARTED_TRANSIENT ) ) {
			$this->redirect_to_onboarding_welcome_page(
				__( 'There was a duplicate attempt to initiate account setup. Please wait a few seconds and try again.', 'woocommerce-payments' )
			);
			return;
		}

		// Set a quickly expiring transient to save the current onboarding state and avoid duplicate requests.
		set_transient( self::ON_BOARDING_STARTED_TRANSIENT, true, 10 );

		// Clear account transient when generating Stripe's oauth data.
		$this->clear_cache();

		// Flags to enable progressive onboarding and collect payout requirements.
		$progressive                 = ! empty( $_GET['progressive'] ) && 'true' === $_GET['progressive'];
		$collect_payout_requirements = ! empty( $_GET['collect_payout_requirements'] ) && 'true' === $_GET['collect_payout_requirements'];

		// Enable dev mode if the test_mode query param is set.
		$test_mode = isset( $_GET['test_mode'] ) ? boolval( wc_clean( wp_unslash( $_GET['test_mode'] ) ) ) : false;

		if ( $test_mode ) {
			WC_Payments_Onboarding_Service::set_test_mode( true );
		}

		if ( ! $collect_payout_requirements ) {
			// Clear onboarding related account options if this is an initial onboarding attempt.
			WC_Payments_Onboarding_Service::clear_account_options();
		} else {
			// Since we assume user has already either gotten here from the eligibility modal,
			// or has already dismissed it, we should set the modal as dismissed so it doesn't display again.
			WC_Payments_Onboarding_Service::set_onboarding_eligibility_modal_dismissed();
		}

		$return_url = $this->get_onboarding_return_url( $wcpay_connect_from );
		if ( ! empty( $additional_args ) ) {
			$return_url = add_query_arg( $additional_args, $return_url );
		}

		// Onboarding self-assessment data.
		$self_assessment_data = isset( $_GET['self_assessment'] ) ? wc_clean( wp_unslash( $_GET['self_assessment'] ) ) : [];
		if ( $self_assessment_data ) {
			$business_type = $self_assessment_data['business_type'] ?? null;
			$account_data  = [
				'setup_mode'    => 'live',  // If there is self assessment data, the user chose the 'live' setup mode.
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
			];
		} elseif ( $test_mode ) {
			$home_url    = get_home_url();
			$default_url = 'http://wcpay.test';
			$url         = wp_http_validate_url( $home_url ) ? $home_url : $default_url;
			// If the site is running on localhost, use the default URL. This is to avoid Stripe's errors.
			// wp_http_validate_url does not check that, unfortunately.
			if ( wp_parse_url( $home_url, PHP_URL_HOST ) === 'localhost' ) {
				$url = $default_url;
			}
			$account_data = [
				'setup_mode'    => 'test',
				'business_type' => 'individual',
				'mcc'           => '5734',
				'url'           => $url,
				'business_name' => get_bloginfo( 'name' ),
			];
		} else {
			$account_data = [];
		}

		$site_data = [
			'site_username' => wp_get_current_user()->user_login,
			'site_locale'   => get_locale(),
		];

		$user_data = $this->get_onboarding_user_data();

		$onboarding_data = $this->payments_api_client->get_onboarding_data(
			$return_url,
			$site_data,
			array_filter( $user_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
			array_filter( $account_data ), // nosemgrep: audit.php.lang.misc.array-filter-no-callback -- output of array_filter is escaped.
			$this->get_actioned_notes(),
			$progressive,
			$collect_payout_requirements
		);

		delete_transient( self::ON_BOARDING_STARTED_TRANSIENT );

		// If an account already exists for this site, we're done.
		if ( false === $onboarding_data['url'] ) {
			WC_Payments::get_gateway()->update_option( 'enabled', 'yes' );
			update_option( '_wcpay_onboarding_stripe_connected', [ 'is_existing_stripe_account' => true ] );
			wp_safe_redirect(
				add_query_arg(
					[ 'wcpay-connection-success' => '1' ],
					$return_url
				)
			);
			exit;
		}

		set_transient( 'woopay_enabled_by_default', $onboarding_data['woopay_enabled_by_default'], DAY_IN_SECONDS );
		set_transient( 'wcpay_stripe_onboarding_state', $onboarding_data['state'], DAY_IN_SECONDS );

		wp_safe_redirect( $onboarding_data['url'] );
		exit;
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
	 * Once the API redirects back to the site after the onboarding flow, verifies the parameters and stores the data
	 *
	 * @param string $state Secret string.
	 * @param string $mode Mode in which this account has been connected. Either 'test' or 'live'.
	 */
	private function finalize_connection( $state, $mode ) {
		if ( get_transient( 'wcpay_stripe_onboarding_state' ) !== $state ) {
			$this->redirect_to_onboarding_welcome_page(
				__( 'There was a problem processing your account data. Please try again.', 'woocommerce-payments' )
			);
			return;
		}
		delete_transient( 'wcpay_stripe_onboarding_state' );
		$this->clear_cache();

		$gateway = WC_Payments::get_gateway();
		$gateway->update_option( 'enabled', 'yes' );
		$gateway->update_option( 'test_mode', 'test' === $mode ? 'yes' : 'no' );

		// Store a state after completing KYC for tracks. This is stored temporarily in option because
		// user might not have agreed to TOS yet.
		update_option( '_wcpay_onboarding_stripe_connected', [ 'is_existing_stripe_account' => false ] );

		// Track account connection finish.
		$incentive        = ! empty( $_GET['promo'] ) ? sanitize_text_field( wp_unslash( $_GET['promo'] ) ) : '';
		$progressive      = ! empty( $_GET['progressive'] ) && 'true' === $_GET['progressive'];
		$event_properties = [
			'incentive' => $incentive,
			'mode'      => 'test' === $mode ? 'test' : 'live',
		];
		$this->tracks_event(
			self::TRACKS_EVENT_ACCOUNT_CONNECT_FINISHED,
			$event_properties
		);

		$params = [
			'wcpay-state'                => false,
			'wcpay-account-id'           => false,
			'wcpay-live-publishable-key' => false,
			'wcpay-test-publishable-key' => false,
			'wcpay-mode'                 => false,
		];
		if ( empty( $_GET['wcpay-connection-error'] ) ) {
			$params['wcpay-connection-success'] = '1';
		}

		wp_safe_redirect( add_query_arg( $params ) );
		exit;
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
					// Since we're about to call the server again, clear out the on-boarding disabled flag. We can let the code
					// below re-create it if the server tells us on-boarding is still disabled.
					delete_transient( self::ON_BOARDING_DISABLED_TRANSIENT );

					$request  = Get_Account::create();
					$response = $request->send();
					$account  = $response->to_array();

				} catch ( API_Exception $e ) {
					if ( 'wcpay_account_not_found' === $e->get_error_code() ) {
						// Special case - detect account not connected and cache it.
						$account = [];
					} elseif ( 'wcpay_on_boarding_disabled' === $e->get_error_code() ) {
						// Special case - detect account not connected and on-boarding disabled. This will get updated the
						// next time we call the server for account information, but just in case we set the expiry time for
						// this setting an hour longer than the account details transient.
						$account = [];
						set_transient( self::ON_BOARDING_DISABLED_TRANSIENT, true, 2 * HOUR_IN_SECONDS );
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
	public function update_cached_account_data( $property, $data ) {
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
	 * Updates the account data.
	 *
	 * @param string $property Property to update.
	 * @param mixed  $data     Data to update.
	 */
	public function update_account_data( $property, $data ) {
		return $this->update_cached_account_data( $property, $data );
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

		// empty array - special value to indicate that there's no account connected.
		if ( empty( $account ) ) {
			return true;
		}

		// live accounts are always valid.
		if ( $account['is_live'] ) {
			return true;
		}

		// test accounts are valid only when in dev mode.
		if ( WC_Payments::mode()->is_dev() ) {
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
	 * Returns an array containing the names of all the WCPay related notes that have been actioned.
	 *
	 * @return array
	 */
	private function get_actioned_notes(): array {
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
	public function get_account_default_currency() {
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
	 * Redirects to the onboarding flow page.
	 * Also checks if the server is connected and try to connect it otherwise.
	 *
	 * @param string $source The source of the redirect.
	 *
	 * @return void
	 */
	private function redirect_to_onboarding_flow_page( string $source ) {
		if ( ! WC_Payments_Utils::should_use_new_onboarding_flow() ) {
			return;
		}

		// Track the Jetpack connection start.
		$this->tracks_event( self::TRACKS_EVENT_ACCOUNT_CONNECT_WPCOM_CONNECTION_START );

		$onboarding_url = add_query_arg(
			[ 'source' => $source ],
			admin_url( 'admin.php?page=wc-admin&path=/payments/onboarding' )
		);

		if ( ! $this->payments_api_client->is_server_connected() ) {
			try {
				$this->payments_api_client->start_server_connection( $onboarding_url );
			} catch ( API_Exception $e ) {
				// If we can't connect to the server, return, the error will be shown on the relevant page.
				return;
			}
		} else {
			$this->redirect_to( $onboarding_url );
		}
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

	/**
	 * Get user data to send to the onboarding flow.
	 *
	 * @return array The user data.
	 */
	private function get_onboarding_user_data(): array {
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
			'onboarding_source' => sanitize_text_field( wp_unslash( $_GET['source'] ) ),
		];
	}
}
