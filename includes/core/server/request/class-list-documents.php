<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Documents.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;

/**
 * Request class for paginated requests.
 */
class List_Documents extends Paginated {


	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DOCUMENTS_API;
	}
}
