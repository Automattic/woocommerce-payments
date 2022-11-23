<?php
/**
 * Class file for WCPay\Core\WC_Payments_Order
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core;

defined( 'ABSPATH' ) || exit;

use \WC_Order;
use WC_Payments_Utils;

/**
 * WC_Payments_Order
 */
class WC_Payments_Order {

	/**
	 * The WC_Order corresponding to this WC_Payments_Order.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Constructor when order_id is provided.
	 *
	 * @param WC_Order|int $order_id The order Id.
	 * @return void
	 */
	public function __construct( $order ) {
		$this->order = wc_get_order( $order );
	}

	/**
	 * Get the payment metadata for key _intent_id.
	 *
	 * @return string
	 */
	public function get_payment_intent_id() : string {
		return $this->order->get_meta( '_intent_id', true );
	}


	/**
	 * Set the payment metadata for key _intent_id.
	 *
	 * @param  string $intent_id
	 * @return void
	 */
	public function set_payment_intent_id( $intent_id ) {
		return $this->order->update_meta_data( '_intent_id', $intent_id );
	}

	/**
	 * Get the payment metadata for key _payment_method_id.
	 *
	 * @return string
	 */
	public function get_payment_method_id() : string {
		return $this->order->get_meta( '_payment_method_id', true );
	}

	/**
	 * Set the payment metadata for key _payment_method_id.
	 *
	 * @param  string $payment_method_id
	 * @return void
	 */
	public function set_payment_method_id( $payment_method_id ) {
		return $this->order->update_meta_data( '_payment_method_id', $payment_method_id );
	}

	/**
	 * Get the WC_Order object backing WC_Payments_Order
	 *
	 * @return WC_Order
	 */
	public function get_wc_order() : WC_Order {
		return $this->order;
	}

	/**
	 * Given the payment intent data, adds it to the given order as metadata and parses any notes that need to be added
	 *
	 * @param string $intent_id The intent ID.
	 * @param string $intent_status Intent status.
	 * @param string $payment_method Payment method ID.
	 * @param string $customer_id Customer ID.
	 * @param string $charge_id Charge ID.
	 * @param string $currency Currency code.
	 */
	public function attach_intent_info_to_order( $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency ) {
		// first, let's save all the metadata that needed for refunds, required for status change etc.
		$this->order->set_transaction_id( $intent_id );
		$this->set_payment_intent_id( $intent_id );
		$this->order->update_meta_data( '_charge_id', $charge_id );
		$this->order->update_meta_data( '_intention_status', $intent_status );
		$this->order->update_meta_data( '_payment_method_id', $payment_method );
		$this->order->update_meta_data( '_stripe_customer_id', $customer_id );
		WC_Payments_Utils::set_order_intent_currency( $this->order, $currency );
		$this->order->save();
	}

}
