<?php
/**
 * Trait Duplicate_Payment_Prevention_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use Exception;
use WC_Order;
use WC_Session;
use WC_Session_Handler;
use WC_Payment_Gateway_WCPay;
use WCPay\Logger;
use WCPay\Core\Server\Request\Get_Intention;

/**
 * A service, which helps try to prevent duplicate payments.
 */
class Duplicate_Payment_Prevention_Service {
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
	 * Flag to indicate that a previous intention attached to the order was successful.
	 */
	const FLAG_PREVIOUS_SUCCESSFUL_INTENT = 'wcpay_previous_successful_intent';

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
	 *
	 * @param WC_Payment_Gateway_WCPay      $gateway Active gateway.
	 * @param WC_Session|WC_Session_Handler $session Session object.
	 */
	public function __construct( WC_Payment_Gateway_WCPay $gateway, $session ) {
		$this->gateway = $gateway;
		$this->session = $session;
	}

	/**
	 * Checks if the current order has the same content with the session processing order, which was already paid (ex. by a webhook).
	 *
	 * @param  WC_Order $current_order Current order in process_payment.
	 *
	 * @return array|void A successful response in case the session processing order was paid, null if none.
	 */
	public function check_against_session_processing_order( WC_Order $current_order ) {
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
	public function maybe_update_session_processing_order( int $order_id ) {
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
	 * Checks whether an order has already an intent attached,
	 * and the intent is successful. This would prevent another intent
	 * from being generated.
	 *
	 * @param WC_Order $order The order to check.
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention|null
	 */
	public function check_attached_intent_success( WC_Order $order ) {
		$intent_id = (string) $order->get_meta( '_intent_id', true );
		if ( empty( $intent_id ) ) {
			return;
		}

		// We only care about payment intent.
		$is_payment_intent = 'pi_' === substr( $intent_id, 0, 3 );
		if ( ! $is_payment_intent ) {
			return;
		}

		try {
			$request = Get_Intention::create( $intent_id );
			$intent  = $request->send( 'wcpay_get_intention_request' );

			$intent_status = $intent->get_status();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to fetch attached payment intent: ' . $e );
			return;
		};

		if ( ! in_array( $intent_status, WC_Payment_Gateway_WCPay::SUCCESSFUL_INTENT_STATUS, true ) ) {
			return;
		}

		// @todo: This meta key, this way, is susceptible to change.
		$intent_meta_order_id_raw = $intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		if ( $intent_meta_order_id !== $order->get_id() ) {
			return;
		}

		return $intent;
	}

	/**
	 * Generates a redirect (order received page) URL, including the
	 * necessary parameters to display a message about a previous intent.
	 *
	 * @param WC_Order $order The order to redirect to.
	 * @return array          A WooCommerce gateway successful response.
	 */
	public function get_successful_intent_response( WC_Order $order ) {
		$return_url = $this->gateway->get_return_url( $order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_SUCCESSFUL_INTENT, 'yes', $return_url );

		return [
			'result'                               => 'success',
			'redirect'                             => $return_url,
			'wcpay_upe_previous_successful_intent' => 'yes', // This flag is needed for UPE flow.
		];
	}
}
