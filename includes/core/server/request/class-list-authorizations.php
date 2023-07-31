<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Authorizations.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;

/**
 * Request class for listing authorizations.
 */
class List_Authorizations extends Paginated {


	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::AUTHORIZATIONS_API;
	}
}
