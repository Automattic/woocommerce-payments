<?php
/**
 * Class file for WCPay\Core\Server\Request\Add_Account_Tos_Agreement.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for recording a new Terms of Service agreement.
 * Expected response is an array, containing a `success` flag.
 */
class Add_Account_Tos_Agreement extends Request {
	const REQUIRED_PARAMS = [
		'source',
		'user_name',
	];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_add_account_tos_agreement';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::ACCOUNTS_API . '/tos_agreements';
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
	 * Sets the source (location) that the merchant agreed to the terms.
	 *
	 * @param  string $source Location that the merchant agreed to the terms.
	 *
	 * @return void
	 */
	public function set_source( string $source ) {
		$this->set_param( 'source', $source );
	}

	/**
	 * Sets the user name.
	 *
	 * @param  string $user_name The user_login of the provided user.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_user_name( string $user_name ) {
		$this->validate_user_name( $user_name );
		$this->set_param( 'user_name', $user_name );
	}
}
