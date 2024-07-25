<?php
/**
 * Class WC_Payments_Redirect_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Core\Server\Request\Get_Account_Capital_Link;
use WCPay\Core\Server\Request\Get_Account_Login_Data;
use WCPay\Exceptions\API_Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class handling redirects business logic.
 */
class WC_Payments_Redirect_Service {

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Constructor for WC_Payments_Session_Service.
	 *
	 * @param WC_Payments_API_Client $payments_api_client - WooCommerce Payments API client.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client
	) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Calls wp_safe_redirect and exit.
	 *
	 * This method will end the execution immediately after the redirection.
	 *
	 * @param string $location The URL to redirect to.
	 */
	public function redirect_to( string $location ): void {
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Redirects to the wcpay-connect URL, which then redirects to the KYC flow.
	 *
	 * This URL is used by the KYC reminder email. We can't take the merchant
	 * directly to the wcpay-connect URL because it's nonced, and the
	 * nonce will likely be expired by the time the user follows the link.
	 * That's why we need this middleman instead.
	 *
	 * @param string $from Source of the redirect.
	 */
	public function redirect_to_wcpay_connect( string $from = '' ): void {
		// Take the user to the 'wcpay-connect' URL.
		// We handle creating and redirecting to the account link there.
		$params = [
			'wcpay-connect' => '1',
			'_wpnonce'      => wp_create_nonce( 'wcpay-connect' ),
		];
		if ( '' !== $from ) {
			$params['from'] = $from;
		}
		$connect_url = add_query_arg(
			$params,
			admin_url( 'admin.php' )
		);

		$this->redirect_to( $connect_url );
	}

	/**
	 * Redirects to the capital view offer page or overview page with error message.
	 */
	public function redirect_to_capital_view_offer_page(): void {
		$return_url  = WC_Payments_Account::get_overview_page_url();
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

			$this->redirect_to_overview_page_with_error( [ 'wcpay-loan-offer-error' => '1' ] );
		}
	}

	/**
	 * Function to immediately redirect to the account link.
	 *
	 * @param array $args The arguments to be sent with the link request.
	 */
	public function redirect_to_account_link( array $args ): void {
		try {
			$link = $this->payments_api_client->get_link( $args );

			if ( isset( $args['type'] ) && 'complete_kyc_link' === $args['type'] && isset( $link['state'] ) ) {
				set_transient( 'wcpay_stripe_onboarding_state', $link['state'], DAY_IN_SECONDS );
			}

			$this->redirect_to( $link['url'] );
		} catch ( API_Exception $e ) {
			$this->redirect_to_overview_page_with_error( [ 'wcpay-server-link-error' => '1' ] );
		}
	}

	/**
	 * Immediately redirect to the Connect page.
	 *
	 * Note that this function immediately ends the execution.
	 *
	 * @param string|null $error_message     Optional. Error message to show in a notice.
	 * @param string|null $from              Optional. Source of the redirect.
	 * @param array       $additional_params Optional. Additional URL params to add to the redirect URL.
	 */
	public function redirect_to_connect_page( ?string $error_message = null, ?string $from = null, array $additional_params = [] ): void {
		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/connect',
		];

		if ( count( $params ) === count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			// We are already on the Connect page. Do nothing.
			return;
		}

		// If we were given an error message, store it in a very short-lived transient to show it on the page.
		if ( ! empty( $error_message ) ) {
			set_transient( WC_Payments_Account::ERROR_MESSAGE_TRANSIENT, $error_message, 30 );
		}

		$params = array_merge( $params, $additional_params );

		if ( ! empty( $from ) ) {
			$params['from'] = $from;
		}

		$this->redirect_to( admin_url( add_query_arg( $params, 'admin.php' ) ) );
	}

	/**
	 * Immediately redirect to the onboarding wizard.
	 *
	 * Note that this function immediately ends the execution.
	 *
	 * @param string|null $from              Optional. Source of the redirect.
	 * @param array       $additional_params Optional. Additional URL params to add to the redirect URL.
	 */
	public function redirect_to_onboarding_wizard( ?string $from = null, array $additional_params = [] ): void {
		$params = [
			'page' => 'wc-admin',
			'path' => '/payments/onboarding',
		];

		if ( count( $params ) === count( array_intersect_assoc( $_GET, $params ) ) ) { // phpcs:disable WordPress.Security.NonceVerification.Recommended
			// We are already in the onboarding wizard. Do nothing.
			return;
		}

		$params = array_merge( $params, $additional_params );

		if ( ! empty( $from ) ) {
			$params['from'] = $from;
		}

		$this->redirect_to( admin_url( add_query_arg( $params, 'admin.php' ) ) );
	}

	/**
	 * Redirect to the overview page.
	 *
	 * @param string $from              Optional. Source of the redirect.
	 * @param array  $additional_params Optional. Additional URL params to add to the redirect URL.
	 * */
	public function redirect_to_overview_page( string $from = '', array $additional_params = [] ): void {
		$params = $additional_params;
		if ( '' !== $from ) {
			$params['from'] = $from;
		}

		$this->redirect_to( add_query_arg( $params, WC_Payments_Account::get_overview_page_url() ) );
	}

	/**
	 * Redirect to the overview page with an error message.
	 *
	 * @param array $error The error data to show.
	 */
	public function redirect_to_overview_page_with_error( array $error ): void {
		$overview_url_with_error = add_query_arg(
			$error,
			WC_Payments_Account::get_overview_page_url()
		);
		$this->redirect_to( $overview_url_with_error );
	}

	/**
	 * For the connected account, fetches the login url from the API and redirects to it.
	 */
	public function redirect_to_login(): void {

		$redirect_url = WC_Payments_Account::get_overview_page_url();

		$request = Get_Account_Login_Data::create();
		$request->set_redirect_url( $redirect_url );

		$response   = $request->send();
		$login_data = $response->to_array();
		$this->redirect_to( $login_data['url'] );
	}
}
