<?php
/**
 * Class WC_Payments_Duplicate_Payment_Prevention_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Order;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WCPay\Constants\Intent_Status;
use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\WooPay\WooPay_Utilities;

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
	 * Key name for saving the ID of the WC Session the processing order_id above was stored in.
	 *
	 * @type string
	 */
	const SESSION_KEY_PROCESSING_ORDER_OWNER = 'wcpay_processing_order_session';

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
	 * @return array|void A successful response in case the attached intent was successful, a failed one when an
	 *                    earlier attempt still waiting for the shopper could not be cancelled and the request
	 *                    brings no payment of its own, null otherwise.
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

		$intent = $this->fetch_attached_intent( $intent_id, $order );
		if ( null === $intent || ! $this->is_intent_for_order( $intent, $order ) ) {
			return;
		}

		// An attempt left waiting for 3DS or a redirect can still be completed after the payment that is about
		// to start, which would charge the shopper twice.
		if ( Intent_Status::REQUIRES_ACTION === $intent->get_status() && $this->can_replace_unfinished_attempt( $order, $intent_id ) ) {
			if ( $this->order_service->cancel_attempt_replaced_by_new_payment( $order, $intent_id ) ) {
				return;
			}

			// The cancel fails when the shopper finished the attempt in the meantime, so look again before
			// charging. The order has stopped naming the attempt, and names it again unless it turns out to be
			// cancelled: a 3DS result reported for a cancelled intent the order names would cancel the order.
			try {
				$intent = $this->fetch_attached_intent( $intent_id, $order );
			} catch ( \Error $e ) {
				// Nothing may skip what follows: the order has stopped naming the attempt, and must name it again.
				Logger::error( 'Failed to fetch attached payment intent after a failed cancel: ' . $e );
				$intent = null;
			}
			if ( null !== $intent && Intent_Status::CANCELED === $intent->get_status() ) {
				// The cancel call errored after it went through, or another request cancelled the attempt first.
				$this->order_service->detach_cancelled_attempt( $order, $intent_id );
				return;
			}

			// A payment that went through settles the order below, one that failed can no longer be completed,
			// and one that is still open, or can't be read, is there for the shopper's next try to cancel.
			$this->order_service->reattach_attempt( $order, $intent_id );

			// An attempt that is still open, or can't be read, could yet charge the shopper.
			if ( null === $intent || Intent_Status::REQUIRES_ACTION === $intent->get_status() ) {
				if ( '' === $this->get_posted_woopay_intent_id() ) {
					// The new payment waits for the shopper's next try.
					return $this->refuse_payment_while_attempt_is_open( $order, $intent_id );
				}

				// WooPay charged the payment it posts before this request, so holding the request back prevents
				// nothing and leaves the shopper charged with no order. If the earlier attempt completes too,
				// its webhook finds the order settled and flags it for a refund.
				$this->add_note_once(
					$order,
					/* translators: %1: the ID of a WooPayments payment. */
					__( 'The earlier WooPayments payment <code>%1$s</code> could not be cancelled before a WooPay payment for this order. If it still completes, it will need a refund.', 'woocommerce-payments' ),
					$intent_id
				);
				return;
			}
		}

		$intent_status = $intent->get_status();
		if ( ! $intent->is_authorized() ) {
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

		if ( Intent_Status::REQUIRES_CAPTURE === $intent_status ) {
			$this->order_service->attach_intent_info_to_order( $order, $intent );
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
	 * Fetches the payment intent attached to an order.
	 *
	 * @param string   $intent_id The intent attached to the order.
	 * @param WC_Order $order     The order.
	 *
	 * @return \WC_Payments_API_Payment_Intention|null The intent, or null when fetching it threw an exception. A
	 *                                                 PHP error is not caught here: a response that is not an
	 *                                                 intent says nothing about the attached payment.
	 */
	private function fetch_attached_intent( string $intent_id, WC_Order $order ) {
		try {
			$request = Get_Intention::create( $intent_id );
			$request->set_hook_args( $order );

			return $request->send();
		} catch ( \Exception $e ) {
			Logger::error( 'Failed to fetch attached payment intent: ' . $e );
			return null;
		}
	}

	/**
	 * Whether an intent was created for an order, going by the intent's own metadata.
	 *
	 * An order can store an intent created for another order, so the ID on the order is not enough.
	 *
	 * @param \WC_Payments_API_Payment_Intention $intent The intent attached to the order.
	 * @param WC_Order                           $order  The order.
	 *
	 * @return bool
	 */
	private function is_intent_for_order( $intent, WC_Order $order ): bool {
		$metadata                     = $intent->get_metadata();
		$intent_meta_order_id_raw     = $metadata['order_id'] ?? '';
		$intent_meta_order_id         = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		$intent_meta_order_number_raw = $metadata['order_number'] ?? '';
		$intent_meta_order_number     = is_numeric( $intent_meta_order_number_raw ) ? intval( $intent_meta_order_number_raw ) : 0;
		$paid_on_woopay               = filter_var( $metadata['paid_on_woopay'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$is_woopay_order              = $order->get_id() === $intent_meta_order_number;

		return ( $paid_on_woopay && $is_woopay_order ) || $intent_meta_order_id === $order->get_id();
	}

	/**
	 * Whether a new payment may cancel the attempt of the order's intent: the shopper can still pay the order,
	 * and the intent is not the payment the request itself brings.
	 *
	 * @param WC_Order $order     The order being paid.
	 * @param string   $intent_id The intent attached to the order.
	 *
	 * @return bool
	 */
	private function can_replace_unfinished_attempt( WC_Order $order, string $intent_id ): bool {
		if ( ! $order->has_status( $this->get_statuses_a_retry_cancels_in( $order ) ) ) {
			return false;
		}

		// WooPay brings its own intent, which the gateway then uses for this payment.
		return $this->get_posted_woopay_intent_id() !== $intent_id;
	}

	/**
	 * The order statuses in which a new payment cancels an attempt left waiting for the shopper: those in
	 * which the shopper can pay the order again.
	 *
	 * Pending and failed always count: the classic checkout takes an order up again in them whatever a store
	 * declares, and a filter callback can replace the list as well as add to it. A status the store adds counts
	 * too, as a custom "awaiting payment" status does: an order created in it keeps it while a challenge is
	 * open. On hold never counts. An order on hold with an intent waiting for the shopper is waiting on a
	 * voucher, which stays payable.
	 *
	 * @param WC_Order $order The order being paid.
	 *
	 * @return string[] Status slugs, without the `wc-` prefix.
	 */
	private function get_statuses_a_retry_cancels_in( WC_Order $order ): array {
		$statuses = [ Order_Status::PENDING, Order_Status::FAILED ];

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce core hook, not defined by WooPayments.
		$declared = apply_filters( 'woocommerce_valid_order_statuses_for_payment', $statuses, $order );

		// Any callback can return anything.
		$declared = array_filter( (array) $declared, 'is_string' );

		return array_values( array_diff( array_unique( array_merge( $statuses, $declared ) ), [ Order_Status::ON_HOLD ] ) );
	}

	/**
	 * The intent a request brings with it, as WooPay does for a payment it has already taken.
	 *
	 * @return string The intent ID, or an empty string when the request posts none.
	 */
	private function get_posted_woopay_intent_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted_intent = wp_unslash( $_POST['platform-checkout-intent'] ?? '' );

		return is_string( $posted_intent ) ? WooPay_Utilities::sanitize_intent_id( $posted_intent ) : '';
	}

	/**
	 * Adds an order note naming a WooPayments payment, unless the order already has it.
	 *
	 * @param WC_Order $order     The order.
	 * @param string   $message   The translated message, with the `<code>` tag around `%1$s`.
	 * @param string   $intent_id The payment to name.
	 *
	 * @return void
	 */
	private function add_note_once( WC_Order $order, string $message, string $intent_id ): void {
		// A note that can't be added must not fail a payment, least of all one WooPay has already charged.
		try {
			$note = sprintf( \WC_Payments_Utils::esc_interpolated_html( $message, [ 'code' => '<code>' ] ), $intent_id );
			if ( ! $this->order_service->order_note_exists( $order, $note ) ) {
				$order->add_order_note( $note );
			}
		} catch ( \Throwable $e ) {
			Logger::error( sprintf( 'Could not add a note about intent %1$s to order %2$d: %3$s', $intent_id, $order->get_id(), $e->getMessage() ) );
		}
	}

	/**
	 * Refuses a new payment while the order's earlier attempt could still charge the shopper, and tells the
	 * merchant once. The order keeps its status, since nothing about it failed.
	 *
	 * @param WC_Order $order     The order being paid.
	 * @param string   $intent_id The intent of the earlier attempt.
	 *
	 * @return array The failed payment result.
	 */
	private function refuse_payment_while_attempt_is_open( WC_Order $order, string $intent_id ): array {
		$this->add_note_once(
			$order,
			/* translators: %1: the ID of a WooPayments payment. */
			__( 'The earlier WooPayments payment <code>%1$s</code> could not be cancelled, so a new payment was not started for this order. The shopper was asked to try again.', 'woocommerce-payments' ),
			$intent_id
		);

		// The classic checkout shows the notice. The block checkout shows what the gateway's error action puts
		// on the payment result.
		$message = __( 'Your earlier payment attempt for this order is still in progress. Please wait a moment and try again.', 'woocommerce-payments' );
		wc_add_notice( $message, 'error' );
		/** This action is documented in includes/class-wc-payment-gateway-wcpay.php */
		do_action( 'wcpay_update_payment_result_on_error', new Process_Payment_Exception( $message, 'earlier_payment_attempt_in_progress' ), $order );

		return [
			'result'   => 'fail',
			'redirect' => '',
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

		/**
		 * Filters whether a payment for an already-paid order should be prevented.
		 *
		 * Escape hatch for a flow that legitimately re-runs payment against an entity that was
		 * already paid, and cannot say so through the order's status — the subscription
		 * payment-method change exempted above is one such flow. Returning false lets the payment
		 * through, so only do it for a specific flow you recognise: a blanket false restores the
		 * double-charge this guard exists to stop.
		 *
		 * @since 11.1.0
		 *
		 * @param bool     $should_prevent Whether to stop the payment. Default true.
		 * @param WC_Order $order          The order about to be paid a second time.
		 * @param string   $status         The order's stored status.
		 */
		if ( ! apply_filters( 'wcpay_should_prevent_payment_for_paid_order', true, $order, $status ) ) {
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
			WC()->session->set( self::SESSION_KEY_PROCESSING_ORDER_OWNER, (string) WC()->session->get_customer_id() );
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

		// The order is only used in the session that stored it.
		if ( null === $val || $session->get( self::SESSION_KEY_PROCESSING_ORDER_OWNER ) !== (string) $session->get_customer_id() ) {
			return null;
		}

		return absint( $val );
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

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uncached by design; the table name comes from OrderUtil or $wpdb->prefix, not from input.
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
