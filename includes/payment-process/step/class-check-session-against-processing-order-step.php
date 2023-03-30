<?php
/**
 * Class Check_Session_Against_Processing_Order
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Session;
use WC_Session_Handler;
use WC_Payments;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Checks if the session contains an order, which is already in the processing state.
 *
 * This will prevent a second payment for an order, which is already paid.
 */
final class Check_Session_Against_Processing_Order_Step extends Abstract_Step {
	/**
	 * Key name for saving the current processing order_id to WC Session with the purpose
	 * of preventing duplicate payments in a single order.
	 *
	 * @type string
	 */
	const SESSION_KEY_PROCESSING_ORDER = 'wcpay_processing_order';

	/**
	 * Flag to indicate that a previous order with the same cart content has already paid.
	 *
	 * @type string
	 */
	const FLAG_PREVIOUS_ORDER_PAID = 'wcpay_paid_for_previous_order';

	/**
	 * Holds the active hateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Holds the WC session.
	 *
	 * @var WC_Session|WC_Session_Handler
	 */
	protected $session;

	/**
	 * Gathers all required dependencies.
	 */
	public function __construct() {
		$this->gateway = WC_Payments::get_gateway();
		$this->session = WC()->session;
	}

	/**
	 * Checks if the step is applicable to the process.
	 *
	 * @param Payment $payment The payment process.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	/**
	 * Before the other steps get to process the payment, checks if
	 * maybe the intent was to pay for another order, and it's paid for.
	 *
	 * @param Payment $payment Payment proccess.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();

		// Check if there was another order, and complete the process already.
		$check_session_order = $this->check_against_session_processing_order( $order );
		if ( is_array( $check_session_order ) ) {
			$payment->complete( $check_session_order );
			return;
		}

		// Store the current order in session.
		$this->maybe_update_session_processing_order( $order->get_id() );
	}

	/**
	 * Checks if the current order has the same content with the session processing order, which was already paid (ex. by a webhook).
	 *
	 * @param  WC_Order $current_order Current order in process_payment.
	 *
	 * @return array|void A successful response in case the session processing order was paid, null if none.
	 */
	protected function check_against_session_processing_order( WC_Order $current_order ) {
		$session_order_id = $this->get_session_processing_order();
		if ( null === $session_order_id ) {
			return;
		}

		$session_order = wc_get_order( $session_order_id );
		if ( ! is_a( $session_order, 'WC_Order' ) ) {
			return;
		}

		if ( $current_order->get_cart_hash() !== $session_order->get_cart_hash() ) {
			return;
		}

		if ( ! $session_order->has_status( wc_get_is_paid_statuses() ) ) {
			return;
		}

		$session_order->add_order_note(
			sprintf(
				/* translators: order ID integer number */
				__( 'WooCommerce Payments: detected and deleted order ID %d, which has duplicate cart content with this order.', 'woocommerce-payments' ),
				$current_order->get_id()
			)
		);
		$current_order->delete();

		$this->remove_session_processing_order( $session_order_id );

		$return_url = $this->gateway->get_return_url( $session_order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_ORDER_PAID, 'yes', $return_url );

		return [
			'result'                            => 'success',
			'redirect'                          => $return_url,
			'wcpay_upe_paid_for_previous_order' => 'yes', // This flag is needed for UPE flow.
		];
	}

	/**
	 * Update the processing order ID for the current session.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return void
	 */
	protected function maybe_update_session_processing_order( int $order_id ) {
		if ( $this->session ) {
			$this->session->set( self::SESSION_KEY_PROCESSING_ORDER, $order_id );
		}
	}

	/**
	 * Remove the provided order ID from the current session if it matches with the ID in the session.
	 *
	 * @param  int $order_id Order ID to remove from the session.
	 *
	 * @return void
	 */
	protected function remove_session_processing_order( int $order_id ) {
		$current_session_id = $this->get_session_processing_order();
		if ( $order_id === $current_session_id && WC()->session ) {
			$this->session->set( self::SESSION_KEY_PROCESSING_ORDER, null );
		}
	}

	/**
	 * Get the processing order ID for the current session.
	 *
	 * @return integer|null Order ID. Null if the value is not set.
	 */
	protected function get_session_processing_order() {
		if ( null === $this->session ) {
			return null;
		}

		$val = $this->session->get( self::SESSION_KEY_PROCESSING_ORDER );
		return null === $val ? null : absint( $val );
	}

	/**
	 * Action to remove the order ID when customers reach its order-received page.
	 *
	 * @param Payment $payment The payment process.
	 * @todo This could be a different clean-up method. Let's see how the process will line up.
	 */
	public function complete( Payment $payment ) {
		global $wp;

		// ToDo: Restore this when complete is also called on the cart.
		if ( false && is_order_received_page() && isset( $wp->query_vars['order-received'] ) ) {
			$order_id = absint( $wp->query_vars['order-received'] );
			$this->remove_session_processing_order( $order_id );
		}

		// Cleanup at the end of checkout.
		if ( $payment instanceof Order_Payment ) {
			$this->remove_session_processing_order( $payment->get_order()->get_id() );
		}
	}
}
