<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Transactions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WC_Payments_DB;
use WCPay\Core\Server\Response;

/**
 * Request class for listing transactions.
 */
class List_Transactions extends Paginated {

	use Order_Info;

	const DEFAULTS = [
		'sort'       => 'date',
		'direction'  => 'desc',
		'deposit_id' => null,
	];

	/**
	 * Set deposit id.
	 *
	 * @param mixed $deposit_id Deposit id.
	 *
	 * @return void
	 */
	public function set_deposit_id( $deposit_id ) {
		$this->set_param( 'deposit_id', $deposit_id );
	}

	/**
	 * Get api URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::TRANSACTIONS_API;
	}

	/**
	 * Return formatted response.
	 *
	 * @param mixed $response Transactions from server.
	 *
	 * @return Response
	 */
	public function format_response( $response ) {
		$wcpay_db               = new WC_Payments_DB();
		$charge_ids             = array_column( $response['data'], 'charge_id' );
		$orders_with_charge_ids = count( $charge_ids ) ? $wcpay_db->orders_with_charge_id_from_charge_ids( $charge_ids ) : [];

		// Add order information to each transaction available.
		// TODO: Throw exception when `$response` or `$transaction` don't have the fields expected?
		if ( isset( $response['data'] ) ) {
			foreach ( $response['data'] as &$transaction ) {
				foreach ( $orders_with_charge_ids as $order_with_charge_id ) {
					if ( $order_with_charge_id['charge_id'] === $transaction['charge_id'] && ! empty( $transaction['charge_id'] ) ) {
						$order                            = $order_with_charge_id['order'];
						$transaction['order']             = $this->build_order_info( $order );
						$transaction['payment_intent_id'] = $order->get_meta( '_intent_id' );
					}
				}
			}
			// Securing future changes from modifying reference content.
			unset( $transaction );
		}

		return new Response( $response );
	}



}
