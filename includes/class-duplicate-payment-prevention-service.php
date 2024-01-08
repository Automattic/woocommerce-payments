<?php
/**
 * Class WC_Payments_Duplicate_Payment_Prevention_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Exception;
use WC_Order;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WCPay\Constants\Intent_Status;
use WCPay\Core\Server\Request\Get_Intention;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Used for methods, which detect existing payments or payment intents,
 * and prevent creating duplicate payments.
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
	 * WC_Payments_Order_Service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Initializes all dependencies and hooks, related to the service.
	 *
	 * @param WC_Payment_Gateway_WCPay  $gateway The main gateway.
	 * @param WC_Payments_Order_Service $order_service The order service instance.
	 */
	public function init( WC_Payment_Gateway_WCPay $gateway, WC_Payments_Order_Service $order_service ) {
		$this->gateway       = $gateway;
		$this->order_service = $order_service;
	}

	/**
	 * Checks if the attached payment intent was successful for the current order.
	 *
	 * @param WC_Order $order Current order to check.
	 *
	 * @return array|void A successful response in case the attached intent was successful, null if none.
	 */
	public function check_payment_intent_attached_to_order_succeeded( WC_Order $order ) {
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
			$request->set_hook_args( $order );
			/** @var \WC_Payments_API_Abstract_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$intent        = $request->send();
			$intent_status = $intent->get_status();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to fetch attached payment intent: ' . $e );
			return;
		};

		if ( ! $intent->is_authorized() ) {
			return;
		}

		$intent_meta_order_id_raw     = $intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id         = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		$intent_meta_order_number_raw = $intent->get_metadata()['order_number'] ?? '';
		$intent_meta_order_number     = is_numeric( $intent_meta_order_number_raw ) ? intval( $intent_meta_order_number_raw ) : 0;
		$paid_on_woopay               = filter_var( $intent->get_metadata()['paid_on_woopay'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$is_woopay_order              = $order->get_id() === $intent_meta_order_number;
		if ( ! ( $paid_on_woopay && $is_woopay_order ) && $intent_meta_order_id !== $order->get_id() ) {
			return;
		}

		if ( Intent_Status::SUCCEEDED === $intent_status ) {
			$this->remove_session_processing_order( $order->get_id() );
		}
		$this->order_service->update_order_status_from_intent( $order, $intent );

		$return_url = $this->gateway->get_return_url( $order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_SUCCESSFUL_INTENT, 'yes', $return_url );
		return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
			'result'                               => 'success',
			'redirect'                             => $return_url,
			'wcpay_upe_previous_successful_intent' => 'yes', // This flag is needed for UPE flow.
		];
	}

	/**
	 * Checks if the current order has the same content with the session processing order, which was already paid (ex. by a webhook).
	 *
	 * @param WC_Order $current_order Current order in process_payment.
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

		if ( ! $current_order->has_status( wc_get_is_pending_statuses() ) ) {
			return;
		}

		if ( $session_order->get_id() === $current_order->get_id() ) {
			return;
		}

		if ( $session_order->get_customer_id() !== $current_order->get_customer_id() ) {
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

		return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
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
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY_PROCESSING_ORDER, $order_id );
		}
	}

	/**
	 * Remove the provided order ID from the current session if it matches with the ID in the session.
	 *
	 * @param  int $order_id Order ID to remove from the session.
	 *
	 * @return void
	 */
	public function remove_session_processing_order( int $order_id ) {
		$current_session_id = $this->get_session_processing_order();
		if ( $order_id === $current_session_id && WC()->session ) {
			WC()->session->set( self::SESSION_KEY_PROCESSING_ORDER, null );
		}
	}

	/**
	 * Get the processing order ID for the current session.
	 *
	 * @return integer|null Order ID. Null if the value is not set.
	 */
	protected function get_session_processing_order() {
		$session = WC()->session;
		if ( null === $session ) {
			return null;
		}

		$val = $session->get( self::SESSION_KEY_PROCESSING_ORDER );
		return null === $val ? null : absint( $val );
	}
}
