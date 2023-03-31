<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Account_Capital_Link.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting a one-time capital link.
 * Expected response is an account link object with create, expires_at, and url fields.
 */
class Get_Account_Capital_Link extends Request {
	const REQUIRED_PARAMS = [
		'type',
		'redirect_url',
		'refresh_url',
	];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::ACCOUNTS_API . '/capital_links';
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
	 * Sets the type of capital link.
	 *
	 * @param  string $type The type of link to be requested.
	 *
	 * @return void
	 */
	public function set_type( string $type ) {
		$this->set_param( 'type', $type );
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

	/**
	 * Sets the refresh URL.
	 *
	 * @param  string $refresh_url URL to navigate to if the link expired, has been previously-visited, or is otherwise invalid.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_refresh_url( string $refresh_url ) {
		$this->validate_redirect_url( $refresh_url );
		$this->set_param( 'refresh_url', $refresh_url );
	}
}
