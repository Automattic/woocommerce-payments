<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Transactions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Exception;
use WC_Payments_API_Client;
use WC_Payments_DB;
use WCPay\Logger;

/**
 * Request class for listing transactions.
 */
class List_Disputes extends Paginated {

	use Order_Info;

	/**
	 * Get api URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DISPUTES_API;
	}

	/**
	 * Return formatted response.
	 *
	 * @param mixed $response Transactions from server.
	 *
	 * @return mixed|array
	 */
	public function format_response( $response ) {
		// Add WooCommerce order information to each dispute.
		if ( isset( $response['data'] ) ) {
			$wcpay_db = new WC_Payments_DB();
			foreach ( $response['data'] as &$dispute ) {
				try {
					// Wrap with try/catch to avoid failing whole request because of a single dispute.
					$dispute['order'] = null;
					$order            = $wcpay_db->order_from_charge_id( $dispute['charge_id'] );
					if ( $order ) {
						$dispute['order'] = $this->build_order_info( $order );
					}
				} catch ( Exception $e ) {
					Logger::error( 'Error adding order info to dispute ' . $dispute['dispute_id'] . ' : ' . $e->getMessage() );
					continue;
				}
			}
		}

		return $response;
	}



}
