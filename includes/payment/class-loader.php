<?php
/**
 * Class Manager
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use Exception;
use WC_Order;
use WCPay\Payment\Strategy\Strategy;
use WCPay\Payment\Storage\File_Storage;
use WCPay\Payment\Payment_Method\Payment_Method_Factory;
use WCPay\Payment\State\{ Initial_State, Intent_Without_Order_State, Prepared_State, Processed_State, Verified_State };

/**
 * Orchestrates the payment objects.
 */
class Loader {
	/**
	 * Storage for payments.
	 *
	 * @var File_Storage
	 */
	protected $storage;

	/**
	 * Payment method factory for the payment process.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Instantiates the manager.
	 */
	public function __construct() {
		$this->storage                = new File_Storage();
		$this->payment_method_factory = new Payment_Method_Factory();
	}

	/**
	 * Instantiates a new payment.
	 *
	 * @param WC_Order $order Order to use for the payment (Optional, but recommended).
	 * @return Payment        Newly created payment.
	 */
	public function create_payment( WC_Order $order = null ) {
		$payment = $this->create_payment_object();
		if ( $order ) {
			$payment->set_order( $order );
		}
		return $payment;
	}

	/**
	 * Loads the payment for an order.
	 *
	 * @param WC_Order $order Order to use for the payment.
	 * @return Payment        Loaded payment.
	 */
	public function load_payment( WC_Order $order ) {
		$payment = $this->create_payment_object();
		$payment->set_order( $order );
		$this->storage->load_from_order( $order, $payment );
		return $payment;
	}

	/**
	 * Attempts to load the payment for an order, and creates one if not found.
	 *
	 * @param WC_Order $order Order to look for.
	 * @return Payment
	 */
	public function load_or_create_payment( WC_Order $order ) {
		$payment = $this->create_payment_object();
		$payment->set_order( $order );
		if ( $this->storage->order_has_payment( $order ) ) {
			$this->storage->load_from_order( $order, $payment );
		}
		return $payment;
	}

	/**
	 * Loads the payment for an order.
	 *
	 * @param string $id    The ID of the payment.
	 * @return Payment|null Loaded payment.
	 */
	public function load_payment_by_id( string $id ) {
		$payment = $this->create_payment_object();
		$this->storage->load_by_id( $id, $payment );
		return $payment;
	}

	/**
	 * Creates a new payment object with the correct dependencies.
	 *
	 * @return Payment
	 */
	protected function create_payment_object() {
		return new Payment( $this->storage, $this->payment_method_factory );
	}
}
