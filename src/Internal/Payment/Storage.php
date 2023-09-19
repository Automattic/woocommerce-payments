<?php
/**
 * Class Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;
use WCPay\Internal\Payment\State\InitialState;
use WCPay\Internal\Payment\State\State;
use WCPay\Internal\Service\PaymentMethodService;

/**
 * Storage for payments.
 */
class Storage {
	/**
	 * Key for storing data in order meta.
	 *
	 * @var string
	 */
	const ORDER_META_KEY = '_wcpay_payment_data';

	/**
	 * State factory.
	 *
	 * @var StateFactory
	 */
	private $state_factory;

	/**
	 * Payment methods service.
	 *
	 * @var PaymentMethodService
	 */
	private $payment_method_service;

	/**
	 * Class constructor.
	 *
	 * @param StateFactory         $state_factory          A factory for payment states.
	 * @param PaymentMethodService $payment_method_service PM service.
	 */
	public function __construct(
		StateFactory $state_factory,
		PaymentMethodService $payment_method_service
	) {
		$this->state_factory          = $state_factory;
		$this->payment_method_service = $payment_method_service;
	}

	/**
	 * Loads or creates the payment object for an order,
	 * returning the one true payment object.
	 *
	 * @param WC_Order $order Order that requires/has payment.
	 * @return State          Current state of the order payment.
	 */
	public function get_order_payment( WC_Order $order ) {
		$payment     = new Payment( $order );
		$state_class = InitialState::class;

		$data = $order->get_meta( self::ORDER_META_KEY );
		if ( is_array( $data ) && ! empty( $data ) ) {
			$state_class = $data['state'];
			$this->import_payment_data( $payment, $data );
		}

		$state = $this->state_factory->create_state( $state_class );
		$state->set_context( $payment );

		return $state;
	}

	/**
	 * Prepares a payment for storage.
	 *
	 * @param State $payment_state The current payment state.
	 * @return array
	 */
	public function save_order_payment( State $payment_state ): array {
		$payment = $payment_state->get_context();
		$data    = $this->extract_payment_data( $payment );

		// State is not a part of the payment object, but should be saved.
		$data['state'] = get_class( $payment_state );

		// Simply store as meta, and save the order immediately.
		$order = $payment->get_order();
		$order->update_meta_data( self::ORDER_META_KEY, $data );
		$order->save();

		return $data;
	}

	/**
	 * Delete all stored payment data from an order.
	 *
	 * WARNING: This method is destructive!
	 * It is useful for development, but should be used with caution.
	 *
	 * @param WC_Order $order Order that might have payment.
	 */
	public function cleanup_order_payment( WC_Order $order ) {
		$order->delete_meta_data( self::ORDER_META_KEY );
		$order->save();
	}

	/**
	 * Extracts all data from a payment object in the form of simple values.
	 *
	 * @param Payment $payment Payment object.
	 * @return array
	 */
	private function extract_payment_data( Payment $payment ): array {
		$data = [];

		$payment_method         = $payment->get_payment_method();
		$data['payment_method'] = $payment_method ? $payment_method->get_data() : null;

		return $data;
	}

	/**
	 * Loads data into a payment object.
	 *
	 * Note: This method relies on the output shape of `extract_payment_data`,
	 * assuming that the data from it was stored in the database, and provided
	 * without modifications after loading from the database.
	 *
	 * @param Payment $payment Payment object.
	 * @param array   $data    Data to import into the payment.
	 */
	private function import_payment_data( Payment $payment, array $data ) {
		$payment_method = $data['payment_method'];
		if ( $payment_method ) {
			$pm_object = $this->payment_method_service->get_from_data( $payment_method );
			$payment->set_payment_method( $pm_object );
		}
	}
}
