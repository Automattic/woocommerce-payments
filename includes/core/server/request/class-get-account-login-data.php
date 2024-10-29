<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Account_Login_Data.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting one-time dashboard login url.
 */
class Get_Account_Login_Data extends Request {
	use Use_Test_Mode_Only_When_Test_Mode_Onboarding;

	const REQUIRED_PARAMS = [
		'redirect_url',
	];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wpcay_get_account_login_data';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::ACCOUNTS_API . '/login_links';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * If true, the request will be signed with the user token rather than blog token.
	 *
	 * @return bool
	 */
	public function should_use_user_token(): bool {
		return true;
	}

	/**
	 * Sets the redirect URL.
	 *
	 * @param  string $redirect_url URL to navigate back to from the dashboard.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_redirect_url( string $redirect_url ) {
		$this->validate_redirect_url( $redirect_url );
		$this->set_param( 'redirect_url', $redirect_url );
	}
}
