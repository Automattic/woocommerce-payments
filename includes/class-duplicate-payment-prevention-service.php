<?php
/**
 * Class WC_Payments_Duplicate_Payment_Prevention_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use WC_Order;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Process_Payment_Exception;

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
	 * @throws Process_Payment_Exception When order amount doesn't match the charged amount.
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
			/** @var \WC_Payments_API_Payment_Intention $intent */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$intent        = $request->send();
			$intent_status = $intent->get_status();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to fetch attached payment intent: ' . $e );
			return;
		}

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

		// Check if the order amount matches the charged amount.
		$order_total_in_cents = \WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() );
		$charged_amount       = $intent->get_amount();

		// If amounts don't match, this indicates the order was modified after payment.
		// Throw an exception to prevent duplicate payment and inform the customer.
		if ( $order_total_in_cents !== $charged_amount ) {
			// Throw exception with customer-friendly message.
			throw new Process_Payment_Exception(
				sprintf(
					/* translators: 1: charged amount, 2: current order total */
					__( 'This order was already paid for %1$s, but the order total has since changed to %2$s, so we prevented an overpayment. Please create a new order for any additional items.', 'woocommerce-payments' ),
					wc_price( \WC_Payments_Utils::interpret_stripe_amount( $charged_amount, $order->get_currency() ), [ 'currency' => $order->get_currency() ] ),
					wc_price( \WC_Payments_Utils::interpret_stripe_amount( $order_total_in_cents, $order->get_currency() ), [ 'currency' => $order->get_currency() ] )
				),
				'duplicate_payment_amount_mismatch'
			);
		}

		// Amounts match, proceed with normal status update.
		$this->order_service->update_order_status_from_intent( $order, $intent );

		$return_url = $this->gateway->get_return_url( $order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_SUCCESSFUL_INTENT, 'yes', $return_url );
		return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
			'result'   => 'success',
			'redirect' => $return_url,
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
			'result'   => 'success',
			'redirect' => $return_url,
		];
	}

	/**
	 * Checks whether the order has already been paid, and stops a second payment for it.
	 *
	 * Last-resort guard for a resubmission that reuses the same order.
	 * `check_payment_intent_attached_to_order_succeeded()` covers that case with a richer response,
	 * but it needs `_intent_id` to have been written and the `Get_Intention` lookup to succeed, and
	 * returns silently otherwise — including on an API timeout, which is when shoppers resubmit.
	 * This reads the stored order status instead, so it holds when the server does not respond.
	 *
	 * @param WC_Order $order Current order in process_payment.
	 *
	 * @return array|void A successful response when the order was already paid, null if not.
	 */
	public function check_order_already_paid( WC_Order $order ) {
		// A subscription payment method change re-runs payment processing against an entity
		// that was already paid once, so it must not be treated as a duplicate.
		if ( $this->gateway->is_changing_payment_method_for_subscription() ) {
			return;
		}

		// The instance loaded by process_payment() may be stale, so the stored status decides.
		$status = $this->get_stored_order_status( $order->get_id() ) ?? $order->get_status();

		if ( ! in_array( $status, wc_get_is_paid_statuses(), true ) ) {
			return;
		}

		/**
		 * A store can declare one of its paid statuses still payable — deposit and partial-payment
		 * extensions do exactly that, collecting the balance through pay-for-order. Defer to that
		 * declaration rather than blocking a payment the store expects.
		 *
		 * `WC_Order::needs_payment()` cannot answer this: it reads the in-memory status, which is
		 * the stale value this guard exists to look past.
		 */
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook, not defined by WooPayments.
		$payable_statuses = apply_filters( 'woocommerce_valid_order_statuses_for_payment', [ Order_Status::PENDING, Order_Status::FAILED ], $order );

		if ( in_array( $status, $payable_statuses, true ) ) {
			return;
		}

		$order->add_order_note(
			__( 'WooPayments: detected and prevented a second payment for this order, which had already been paid.', 'woocommerce-payments' )
		);

		$this->remove_session_processing_order( $order->get_id() );

		$return_url = $this->gateway->get_return_url( $order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_SUCCESSFUL_INTENT, 'yes', $return_url );

		return [ // nosemgrep: audit.php.wp.security.xss.query-arg -- https://woocommerce.github.io/code-reference/classes/WC-Payment-Gateway.html#method_get_return_url is passed in.
			'result'   => 'success',
			'redirect' => $return_url,
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

	/**
	 * Reads an order's status straight from the orders table.
	 *
	 * Both checkout surfaces only begin payment while `needs_payment()` is true, so a second request
	 * that reaches `process_payment()` loaded the order while it was still unpaid. The request that
	 * pays it runs in a different PHP process, so nothing invalidates this one's caches, and the
	 * loaded instance stays stale for the rest of the request.
	 *
	 * Deliberately bypasses every cache layer. Going through the data store would consult the
	 * `orders_data` cache under HPOS, which only a write invalidates, and the write happened in
	 * another process.
	 *
	 * @param int $order_id Order to read.
	 *
	 * @return string|null Status without the `wc-` prefix, or null when the order is not found.
	 */
	private function get_stored_order_status( int $order_id ): ?string {
		global $wpdb;

		if ( \WC_Payments_Utils::is_hpos_tables_usage_enabled() ) {
			// OrderUtil::get_table_for_orders() only exists from WooCommerce 7.9, and the plugin supports 7.6.
			$orders_table = method_exists( OrderUtil::class, 'get_table_for_orders' )
				? OrderUtil::get_table_for_orders()
				: $wpdb->prefix . 'wc_orders';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uncached by design; the table name comes from OrderUtil, not from input.
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders_table} WHERE id = %d", $order_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uncached by design.
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $order_id ) );
		}

		if ( null === $status ) {
			return null;
		}

		// Stripped inline rather than through OrderUtil::remove_status_prefix(), which only exists from
		// WooCommerce 9.2. This runs on every checkout, so an undefined method here is a checkout outage.
		$status = (string) $status;

		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}
}
