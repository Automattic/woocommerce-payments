<?php
/**
 * Class WC_Payments_Invoice_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Order_Mode;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Rest_Request_Exception;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any subscription invoice functionality.
 */
class WC_Payments_Invoice_Service {

	/**
	 * Subscription meta key used to store subscription's last invoice ID.
	 *
	 * @const string
	 */
	const PENDING_INVOICE_ID_KEY = '_wcpay_pending_invoice_id';

	/**
	 * Meta key used to store invoice IDs on orders.
	 *
	 * @const
	 */
	const ORDER_INVOICE_ID_KEY = '_wcpay_billing_invoice_id';

	/**
	 * Action Scheduler hook used to retry recording an out-of-band invoice payment after a transient failure.
	 *
	 * @const string
	 */
	const RECORD_INVOICE_PAYMENT_RETRY_HOOK = 'wcpay_record_invoice_payment_retry';

	/**
	 * Maximum number of scheduled retries for recording an out-of-band invoice payment.
	 *
	 * @const int
	 */
	const MAX_RECORD_INVOICE_PAYMENT_RETRIES = 4;

	/**
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Order Service
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Whether a request to mark an invoice paid failed with a connection or server error (5xx) during this request.
	 *
	 * Once set, the remaining invoices go straight to the scheduled retry, so an outage costs the shopper one request
	 * timeout rather than one per linked subscription.
	 *
	 * @var bool
	 */
	private $is_server_unavailable = false;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client    $payments_api_client  WooCommerce Payments API client.
	 * @param WC_Payments_Order_Service $order_service         WC payments Order Service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Order_Service $order_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->order_service       = $order_service;
	}

	/**
	 * Registers the hooks for this class.
	 */
	public function init_hooks() {
		/**
		 * When a store is in staging mode we don't want any order status chagnes to fire off corrisponding invoice requests to the server.
		 *
		 * Sending these requests from staging sites can have unintended consequences for the live store. For example, updating an unpaid
		 * renewal order's status on a duplicate site, would lead to the corrisponding subscription being marked as paid in the live
		 * account at Stripe.
		 */
		if ( WC_Payments_Subscriptions::is_duplicate_site() ) {
			return;
		}

		if ( WC_Payments_Features::should_use_stripe_billing() ) {
			add_action( 'woocommerce_order_payment_status_changed', [ $this, 'maybe_record_invoice_payment' ], 10, 1 );
			add_action( 'woocommerce_renewal_order_payment_complete', [ $this, 'maybe_record_invoice_payment' ], 11, 1 );
			add_action( self::RECORD_INVOICE_PAYMENT_RETRY_HOOK, [ $this, 'retry_record_invoice_payment' ], 10, 1 );
		}
	}

	/**
	 * Gets the subscription last invoice ID from WC subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 *
	 * @return string Invoice ID.
	 */
	public static function get_pending_invoice_id( $subscription ): string {
		return $subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true );
	}

	/**
	 * Gets the invoice ID from a WC order.
	 *
	 * @param WC_Order $order The order.
	 * @return string Invoice ID.
	 */
	public static function get_order_invoice_id( WC_Order $order ): string {
		return $order->get_meta( self::ORDER_INVOICE_ID_KEY, true );
	}

	/**
	 * Gets the invoice ID from a WC subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 *
	 * @return string Invoice ID.
	 */
	public static function get_subscription_invoice_id( $subscription ) {
		return $subscription->get_meta( self::ORDER_INVOICE_ID_KEY, true );
	}

	/**
	 * Checks whether the amount an invoice collected matches the order total.
	 *
	 * The order can change after Stripe creates the invoice. WooCommerce also keeps fractional line amounts
	 * and rounds only the order total to the store's price decimals, while Stripe rounds each invoice line
	 * to a minor unit. So allow one store price unit of difference per order line.
	 *
	 * @param WC_Order $order            The order.
	 * @param int      $amount_paid      The invoice's amount_paid, in minor units.
	 * @param string   $invoice_currency The invoice's currency.
	 *
	 * @return bool
	 */
	public static function does_invoice_payment_match_order_total( WC_Order $order, $amount_paid, $invoice_currency ): bool {
		if ( ! is_numeric( $amount_paid ) ) {
			return false;
		}

		$order_currency       = $order->get_currency();
		$order_total_in_cents = WC_Payments_Utils::prepare_amount( $order->get_total(), $order_currency );
		$price_unit           = 10 ** max( 0, WC_Payments_Utils::get_stripe_minor_unit_for_currency( $order_currency ) - self::get_price_decimals( $order_currency ) );
		$rounding_allowance   = $price_unit * count( $order->get_items( [ 'line_item', 'fee', 'shipping', 'tax', 'coupon' ] ) );

		return strtolower( $order_currency ) === strtolower( $invoice_currency )
			&& abs( (int) $amount_paid - $order_total_in_cents ) <= $rounding_allowance;
	}

	/**
	 * Gets the number of decimals the store prices a currency in.
	 *
	 * Multi-Currency prices other currencies with their locale's decimals, but webhooks run without its
	 * frontend filters, so wc_get_price_decimals() only holds for the store currency.
	 *
	 * @param string $currency The currency code.
	 *
	 * @return int
	 */
	private static function get_price_decimals( string $currency ): int {
		if ( strtoupper( $currency ) === strtoupper( get_woocommerce_currency() ) ) {
			return wc_get_price_decimals();
		}

		return (int) WC_Payments::get_localization_service()->get_currency_format( strtoupper( $currency ) )['num_decimals'];
	}

	/**
	 * Gets the WC order ID from the invoice ID.
	 *
	 * @param string $invoice_id The invoice ID.
	 * @return int The order ID.
	 */
	public static function get_order_id_by_invoice_id( string $invoice_id ) {
		$query_args = [
			'status'     => 'any',
			'type'       => 'shop_order',
			'limit'      => 1,
			'return'     => 'ids',
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => self::ORDER_INVOICE_ID_KEY,
					'value' => $invoice_id,
				],
			],
		];

		// On HPOS environments we can pass meta_query directly to get_orders() as WC doesn't override it.
		if ( WC_Payments_Utils::is_hpos_tables_usage_enabled() ) {
			$order_ids = wc_get_orders( $query_args );
		} else {
			$meta_query = $query_args['meta_query'];
			unset( $query_args['meta_query'] );

			$add_meta_query = function ( $query ) use ( $meta_query ) {
				$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				return $query;
			};

			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add_meta_query, 10, 2 );
			$order_ids = wc_get_orders( $query_args );
			remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add_meta_query, 10 );
		}

		return (int) array_shift( $order_ids );
	}

	/**
	 * Sets a pending invoice ID meta for a subscription.
	 *
	 * @param WC_Subscription $subscription The subscription to set the invoice on.
	 * @param string          $invoice_id   The invoice ID.
	 */
	public function mark_pending_invoice_for_subscription( WC_Subscription $subscription, string $invoice_id ) {
		$this->set_pending_invoice_id( $subscription, $invoice_id );
	}

	/**
	 * Removes pending invoice id meta from subscription.
	 *
	 * @param WC_Subscription $subscription The Subscription.
	 */
	public function mark_pending_invoice_paid_for_subscription( WC_Subscription $subscription ) {
		$this->set_pending_invoice_id( $subscription, '' );
	}

	/**
	 * Marks a subscription invoice as paid after a parent or renewal order is completed.
	 *
	 * When a subscription's parent order goes from a pending payment status to a payment completed status,
	 * or when a subscription with no corresponding Stripe subscription is manually renewed,
	 * make sure the invoice is marked as paid (without charging the customer since it was charged on checkout).
	 *
	 * Note: this function has no impact on staging sites to prevent corrupting the live subscriptions on the WCPay account.
	 *
	 * If the server refuses to mark the invoice paid (anything other than "invoice is already paid"), the failure is
	 * handed to a scheduled retry rather than rethrown. Rethrowing used to bubble out of the
	 * `woocommerce_order_payment_status_changed` hook, where WC_Order::status_transition() turned it into an order
	 * note. This usually runs in the shopper's checkout request, so it makes no further server calls on failure:
	 * checking whether the WooPayments subscription can still be billed is left to the retry.
	 *
	 * @param int $order_id The WC order ID.
	 */
	public function maybe_record_invoice_payment( int $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || self::get_order_invoice_id( $order ) ) {
			return;
		}

		foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] ) as $subscription ) {
			$invoice_id = self::get_subscription_invoice_id( $subscription );

			if ( ! $invoice_id || ! WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
				continue;
			}

			if ( $this->is_server_unavailable ) {
				$this->defer_invoice_payment( $subscription, $invoice_id, $order, 'not attempted, the WooPayments server failed earlier in this request' );
				continue;
			}

			try {
				// Set the invoice status to paid but don't charge the customer by using paid_out_of_band parameter.
				$this->payments_api_client->charge_invoice( $invoice_id, [ 'paid_out_of_band' => 'true' ] );
			} catch ( API_Exception $e ) {
				// If the invoice was already paid, silently handle that error and carry on; any other failure is
				// handed to the scheduled retry (never rethrown, see the method docblock).
				if ( WP_Http::BAD_REQUEST === $e->get_http_code() && false !== stripos( $e->getMessage(), 'invoice is already paid' ) ) {
					Logger::info( sprintf( 'Invoice for subscription #%s has already been paid.', $subscription->get_id() ) );
				} else {
					// Connection failures (Connection_Exception) report 500 too.
					if ( WP_Http::INTERNAL_SERVER_ERROR <= $e->get_http_code() ) {
						$this->is_server_unavailable = true;
					}
					$this->defer_invoice_payment( $subscription, $invoice_id, $order, $e->getMessage() );
					continue;
				}
			}

			$this->maybe_convert_manual_subscription( $subscription, $order );
		}
	}

	/**
	 * Converts a manually-renewing subscription to automatic renewal once its invoice has been recorded as paid.
	 *
	 * When a subscription with no (or an again-linked) WooPayments subscription is renewed manually, the first recorded
	 * payment flips it back to automatic renewal: manual renewal is turned off, the payment method is set to WooPayments,
	 * and the token used to pay the order is copied to the subscription so future renewals can be charged. No-op for
	 * subscriptions that already renew automatically.
	 *
	 * Called from both the synchronous {@see maybe_record_invoice_payment()} path and the asynchronous
	 * {@see retry_record_invoice_payment()} path so a payment recorded on retry ends in the same state as one recorded
	 * immediately.
	 *
	 * @param WC_Subscription $subscription The subscription whose invoice was recorded as paid.
	 * @param WC_Order        $order        The order the payment token should be copied from.
	 */
	private function maybe_convert_manual_subscription( WC_Subscription $subscription, WC_Order $order ) {
		if ( ! $subscription->is_manual() ) {
			return;
		}

		$subscription->set_requires_manual_renewal( false );
		$subscription->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );

		// Copy the payment token used to pay for the order to the subscription.
		WC_Payments::get_gateway()->update_failing_payment_method( $subscription, $order );
		$subscription->save();
	}

	/**
	 * Hands an out-of-band invoice payment that could not be recorded in the order status hook to the scheduled retry.
	 *
	 * Runs in the shopper's request, so it makes no server calls and never throws. The link is kept untouched until
	 * retry_record_invoice_payment() can check whether the WooPayments subscription can still be billed.
	 *
	 * @param WC_Subscription $subscription The subscription whose invoice could not be recorded as paid.
	 * @param string          $invoice_id   The WooPayments invoice ID.
	 * @param WC_Order        $order        The order being processed.
	 * @param string          $reason       Why the payment was not recorded, for the log.
	 */
	private function defer_invoice_payment( WC_Subscription $subscription, string $invoice_id, WC_Order $order, string $reason ) {
		Logger::error(
			sprintf(
				'Failed to record out-of-band payment for invoice %1$s (subscription #%2$d, order #%3$d), scheduling a retry: %4$s',
				$invoice_id,
				$subscription->get_id(),
				$order->get_id(),
				$reason
			)
		);

		try {
			$this->schedule_record_invoice_payment_retry( $order, $subscription, $invoice_id, 1 );

			$subscription->add_order_note(
				sprintf(
					// translators: %s: WooPayments invoice ID.
					__( 'WooPayments could not record the payment for invoice %s yet and will retry automatically.', 'woocommerce-payments' ),
					$invoice_id
				)
			);
		} catch ( Throwable $t ) {
			Logger::error(
				sprintf(
					'Failed to schedule a retry for invoice %1$s (subscription #%2$d): %3$s',
					$invoice_id,
					$subscription->get_id(),
					$t->getMessage()
				)
			);
		}
	}

	/**
	 * Handles a failed scheduled retry of a "mark invoice paid out of band" request.
	 *
	 * Every failure is logged. On a test/live mode mismatch the link is kept untouched, since the subscription only
	 * looks missing because the store switched modes. Otherwise, if the WooPayments subscription can no longer be
	 * billed (incomplete_expired, canceled, or gone while its mode is known) it is unlinked, so
	 * `is_wcpay_subscription()` returns false and WooCommerce Subscriptions handles renewals itself; if it may still be
	 * live, the link is kept (unlinking could lead to double billing) and a bounded, idempotent retry of the
	 * out-of-band payment is scheduled. Once the retries are exhausted the subscription is left active and linked for
	 * manual review rather than being suspended, and a durable order note is added so the state is visible to the
	 * merchant even when logging is off.
	 *
	 * Runs from retry_record_invoice_payment() under Action Scheduler, never from the order status hook, so the server
	 * lookup below does not hold up checkout. The whole recovery is wrapped so it never throws.
	 *
	 * @param WC_Subscription $subscription The subscription whose invoice could not be recorded as paid.
	 * @param string          $invoice_id   The WooPayments invoice ID that failed.
	 * @param WC_Order        $order        The order being processed.
	 * @param API_Exception   $exception    The exception thrown by charge_invoice().
	 * @param int             $attempt      The retry attempt that just failed (1 is the first scheduled retry).
	 */
	private function handle_failed_invoice_payment( WC_Subscription $subscription, string $invoice_id, WC_Order $order, API_Exception $exception, int $attempt ) {
		$wcpay_subscription_id = WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription );

		Logger::error(
			sprintf(
				'Failed to record out-of-band payment for invoice %1$s (subscription #%2$d, WooPayments subscription %3$s, order #%4$d, attempt %5$d): %6$s',
				$invoice_id,
				$subscription->get_id(),
				$wcpay_subscription_id,
				$order->get_id(),
				$attempt,
				$exception->getMessage()
			)
		);

		// Recovery mutates subscription state and may reach the server; never let it throw.
		try {
			// A test/live mode mismatch (the store switched modes) makes a live subscription look missing on the
			// server (404), so neither unlinking (which could double bill) nor retrying would help. Keep the link
			// untouched and leave the subscription for the store's own mode handling.
			$subscription_mode = $this->get_subscription_mode( $subscription );

			if ( $this->is_subscription_mode_mismatch( $subscription_mode ) ) {
				Logger::error(
					sprintf(
						'Not reconciling invoice %1$s for subscription #%2$d: its WooPayments mode does not match the current store mode. Leaving the subscription active and linked.',
						$invoice_id,
						$subscription->get_id()
					)
				);

				// Logger output is disabled on a default store, so leave a durable, merchant-visible signal too. This is
				// informational (usually a test-to-live switch), not a payment failure, so it points at the mode rather
				// than asking for a payment review.
				$subscription->add_order_note(
					sprintf(
						// translators: %s: WooPayments invoice ID.
						__( 'WooPayments skipped recording the payment for invoice %s because the subscription was created in a different WooPayments mode (test or live) than the store uses now. The subscription remains active and linked; check the WooPayments mode configured for the store.', 'woocommerce-payments' ),
						$invoice_id
					)
				);
				return;
			}

			// Past the mismatch check, a non-empty mode matches the store's.
			if ( $this->is_wcpay_subscription_unbillable( $wcpay_subscription_id, '' !== $subscription_mode ) ) {
				$this->unlink_wcpay_subscription( $subscription, $wcpay_subscription_id );
				return;
			}

			// The subscription may still be live, so keep the link (unlinking could double bill) and retry the
			// idempotent out-of-band payment with backoff. Most failures here are transient (timeout, 5xx).
			if ( $attempt < self::MAX_RECORD_INVOICE_PAYMENT_RETRIES ) {
				$this->schedule_record_invoice_payment_retry( $order, $subscription, $invoice_id, $attempt + 1 );
				return;
			}

			Logger::error(
				sprintf(
					'Exhausted retries recording out-of-band payment for invoice %1$s (subscription #%2$d, WooPayments subscription %3$s). The subscription is left active and linked for manual review.',
					$invoice_id,
					$subscription->get_id(),
					$wcpay_subscription_id
				)
			);

			// Logger output is disabled on a default store, so leave a durable, merchant-visible signal too.
			$subscription->add_order_note(
				sprintf(
					// translators: %1$s: WooPayments invoice ID, %2$s: WooPayments subscription ID.
					__( 'WooPayments could not record the payment for invoice %1$s after several attempts. The subscription (WooPayments subscription %2$s) remains active and needs manual review.', 'woocommerce-payments' ),
					$invoice_id,
					$wcpay_subscription_id
				)
			);
		} catch ( Throwable $t ) {
			Logger::error(
				sprintf(
					'Failed to recover subscription #%1$d after a failed invoice payment: %2$s',
					$subscription->get_id(),
					$t->getMessage()
				)
			);

			// Leave a durable signal as well, but never let note-writing throw.
			try {
				$subscription->add_order_note(
					__( 'WooPayments could not record a payment for this subscription and the automatic recovery failed. The subscription remains active and needs manual review.', 'woocommerce-payments' )
				);
			} catch ( Throwable $note_failure ) {
				Logger::error(
					sprintf(
						'Failed to add a recovery-failure note to subscription #%1$d: %2$s',
						$subscription->get_id(),
						$note_failure->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Retries recording an out-of-band invoice payment that failed earlier. Scheduled via Action Scheduler.
	 *
	 * Idempotent: a successful or already-paid invoice ends the retries; a subscription that has since been reconciled,
	 * unlinked, cancelled or expired is skipped; any remaining failure is routed back through
	 * handle_failed_invoice_payment(), which either unlinks a now-dead subscription, schedules the next attempt, or
	 * exhausts the retries and alerts.
	 *
	 * @param array $payload Scheduled payload with order_id, subscription_id, invoice_id and attempt.
	 */
	public function retry_record_invoice_payment( $payload ) {
		if ( ! is_array( $payload ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}

		$order        = wc_get_order( $payload['order_id'] ?? 0 );
		$subscription = wcs_get_subscription( $payload['subscription_id'] ?? 0 );
		$invoice_id   = (string) ( $payload['invoice_id'] ?? '' );
		$attempt      = (int) ( $payload['attempt'] ?? 0 );

		if ( ! $order || ! $subscription || '' === $invoice_id ) {
			return;
		}

		// Nothing to do if the order has since been reconciled, the subscription was unlinked, or it moved to another invoice.
		if ( self::get_order_invoice_id( $order ) || ! WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
			return;
		}

		if ( self::get_subscription_invoice_id( $subscription ) !== $invoice_id ) {
			return;
		}

		// A cancelled or expired subscription keeps its WooPayments subscription ID, which the store already cancelled
		// on the server. Recording the payment or unlinking it would only add a misleading note. Pending-cancel still
		// has a paid period, so its payment is recorded.
		if ( ! $subscription->has_status( [ 'active', 'on-hold', 'pending-cancel' ] ) ) {
			Logger::info(
				sprintf(
					'Skipping retry for invoice %1$s: subscription #%2$d is %3$s.',
					$invoice_id,
					$subscription->get_id(),
					$subscription->get_status()
				)
			);
			return;
		}

		try {
			$this->payments_api_client->charge_invoice( $invoice_id, [ 'paid_out_of_band' => 'true' ] );
			Logger::info( sprintf( 'Recorded out-of-band payment for invoice %1$s on retry attempt %2$d.', $invoice_id, $attempt ) );
		} catch ( API_Exception $e ) {
			// Any failure other than "already paid" is routed back to the failure handler; do not convert below.
			if ( WP_Http::BAD_REQUEST !== $e->get_http_code() || false === stripos( $e->getMessage(), 'invoice is already paid' ) ) {
				$this->handle_failed_invoice_payment( $subscription, $invoice_id, $order, $e, $attempt );
				return;
			}

			Logger::info( sprintf( 'Invoice %1$s for subscription #%2$d has already been paid.', $invoice_id, $subscription->get_id() ) );
		}

		// The payment has been recorded (now or earlier); mirror the synchronous path's manual-to-automatic conversion.
		$this->maybe_convert_manual_subscription( $subscription, $order );
	}

	/**
	 * Schedules a single retry of recording an out-of-band invoice payment, de-duplicated and with backoff.
	 *
	 * @param WC_Order        $order        The order being processed.
	 * @param WC_Subscription $subscription The subscription whose invoice could not be recorded as paid.
	 * @param string          $invoice_id   The WooPayments invoice ID to retry.
	 * @param int             $attempt      The attempt number to schedule (1-based).
	 */
	private function schedule_record_invoice_payment_retry( WC_Order $order, WC_Subscription $subscription, string $invoice_id, int $attempt ) {
		$action_scheduler_service = WC_Payments::get_action_scheduler_service();

		if ( ! $action_scheduler_service ) {
			Logger::error( sprintf( 'Action Scheduler service unavailable; cannot retry recording out-of-band payment for invoice %s.', $invoice_id ) );
			return;
		}

		$payload = [
			'order_id'        => $order->get_id(),
			'subscription_id' => $subscription->get_id(),
			'invoice_id'      => $invoice_id,
			'attempt'         => $attempt,
		];

		// schedule_job() defaults the group to GROUP_ID and de-duplicates pending actions with the same payload.
		$action_scheduler_service->schedule_job(
			time() + $this->get_record_invoice_payment_retry_delay( $attempt ),
			self::RECORD_INVOICE_PAYMENT_RETRY_HOOK,
			[ $payload ]
		);

		Logger::info( sprintf( 'Scheduled retry attempt %1$d to record out-of-band payment for invoice %2$s (subscription #%3$d).', $attempt, $invoice_id, $subscription->get_id() ) );
	}

	/**
	 * Returns the delay in seconds before a given retry attempt, using a bounded backoff.
	 *
	 * @param int $attempt The 1-based attempt number.
	 * @return int Delay in seconds.
	 */
	private function get_record_invoice_payment_retry_delay( int $attempt ): int {
		$delays = [ 10 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS, DAY_IN_SECONDS ];
		$index  = max( 0, min( $attempt - 1, count( $delays ) - 1 ) );
		return $delays[ $index ];
	}

	/**
	 * Determines whether the current WooPayments mode differs from the mode the subscription was created in.
	 *
	 * The API client scopes every request to the store's current mode, so a subscription created in the other mode
	 * (test vs live) is reported as missing (404) even though it is still live. Treating that as unbillable would
	 * unlink a live subscription and let WooCommerce Subscriptions bill it while Stripe Billing keeps billing too, so
	 * the caller keeps the link untouched when the modes do not match. Mirrors the mode check in
	 * WC_Payments_Subscription_Service::check_wcpay_mode_for_subscription(): an unknown mode is not a mismatch.
	 *
	 * @param string $subscription_mode The subscription's mode from get_subscription_mode(), empty when unknown.
	 * @return bool True when the subscription's mode is known and does not match the current WooPayments mode.
	 */
	private function is_subscription_mode_mismatch( string $subscription_mode ): bool {
		if ( '' === $subscription_mode ) {
			return false;
		}

		$current_mode = WC_Payments::mode()->is_test() ? Order_Mode::TEST : Order_Mode::PRODUCTION;

		return $subscription_mode !== $current_mode;
	}

	/**
	 * Gets the WooPayments mode (test or live) the subscription was created in, from its parent order.
	 *
	 * @param WC_Subscription $subscription The subscription to check.
	 * @return string The saved mode, or an empty string when there is no parent order or it has no saved mode.
	 */
	private function get_subscription_mode( WC_Subscription $subscription ): string {
		$parent_order = $subscription->get_parent();

		if ( ! $parent_order ) {
			return '';
		}

		$subscription_mode = $parent_order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY );

		return is_string( $subscription_mode ) ? $subscription_mode : '';
	}

	/**
	 * Determines whether a WooPayments (Stripe Billing) subscription can no longer be billed, so the store should take
	 * over renewals.
	 *
	 * A subscription whose status is `incomplete_expired` or `canceled` is unbillable; finding it at all shows it
	 * belongs to the current mode. One the server reports as missing (a 404 with a `resource_missing` error) is
	 * unbillable only when its mode is known, since a live subscription in the other mode is reported the same way.
	 * On any other lookup failure (timeout, 5xx, or a bare 404 from infrastructure such as a proxy or gateway) the
	 * status is unknown, so it returns false to keep the link rather than risk unlinking a subscription that may still
	 * be live.
	 *
	 * @param string $wcpay_subscription_id The WooPayments subscription ID.
	 * @param bool   $is_mode_known         Whether the subscription's test/live mode is known and matches the store's.
	 * @return bool True when the subscription can no longer be billed on the server.
	 */
	private function is_wcpay_subscription_unbillable( string $wcpay_subscription_id, bool $is_mode_known ): bool {
		if ( '' === $wcpay_subscription_id ) {
			return false;
		}

		try {
			$wcpay_subscription = $this->payments_api_client->get_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			// Only a 404 the server attributes to the missing resource itself (resource_missing) means the subscription
			// is truly gone. A bare 404 from infrastructure (a proxy or gateway) leaves the status unknown, so the
			// caller keeps the link and retries rather than unlinking a subscription that may still be live and double
			// billing it.
			if ( WP_Http::NOT_FOUND === $e->get_http_code() && 'resource_missing' === $e->get_error_code() ) {
				if ( $is_mode_known ) {
					return true;
				}

				Logger::error(
					sprintf(
						'WooPayments subscription %1$s was not found, but its test/live mode is unknown, so it may still be live in the other mode. Keeping the link.',
						$wcpay_subscription_id
					)
				);
				return false;
			}

			Logger::error(
				sprintf(
					'Could not determine WooPayments subscription %1$s status after a failed invoice payment: %2$s',
					$wcpay_subscription_id,
					$e->getMessage()
				)
			);
			return false;
		}

		return in_array( $wcpay_subscription['status'] ?? '', [ 'incomplete_expired', 'canceled' ], true );
	}

	/**
	 * Unlinks a WooPayments (Stripe Billing) subscription from its WC subscription so WooCommerce Subscriptions takes
	 * over renewals.
	 *
	 * Only called once the server reported the subscription missing, canceled or incomplete_expired, so there is
	 * nothing left to cancel on the server. Like WC_Payments_Subscription_Service::maybe_cancel_subscription(), it keeps
	 * a record of the ID under the "_cancelled_wcpay_subscription_id" meta, removes the live link and the now-stale
	 * invoice pointers, and records a note. After this `is_wcpay_subscription()` returns false.
	 *
	 * @param WC_Subscription $subscription          The subscription to unlink.
	 * @param string          $wcpay_subscription_id The WooPayments subscription ID being unlinked.
	 */
	private function unlink_wcpay_subscription( WC_Subscription $subscription, string $wcpay_subscription_id ) {
		$subscription->update_meta_data( '_cancelled' . WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, $wcpay_subscription_id );
		$subscription->delete_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY );
		$subscription->delete_meta_data( self::ORDER_INVOICE_ID_KEY );
		$subscription->delete_meta_data( self::PENDING_INVOICE_ID_KEY );
		$subscription->save();

		$subscription->add_order_note(
			__( 'The WooPayments subscription linked to this subscription could no longer be billed, so it has been unlinked and WooCommerce Subscriptions will handle future renewals.', 'woocommerce-payments' )
		);
	}

	/**
	 * Validates a WCPay invoice.
	 *
	 * @param array           $wcpay_item_data     The WCPay invoice items.
	 * @param array           $wcpay_discount_data The WCPay invoice discounts.
	 * @param WC_Subscription $subscription        The WC Subscription object.
	 *
	 * @throws API_Exception          If updating the WCPay subscription or items fails.
	 * @throws Rest_Request_Exception If WCPay invoice items do not match WC subscription items.
	 */
	public function validate_invoice( array $wcpay_item_data, array $wcpay_discount_data, WC_Subscription $subscription ) {
		$item_data = $this->get_repair_data_for_wcpay_items( $wcpay_item_data, $subscription );

		if ( ! empty( $item_data ) ) {
			foreach ( $item_data as $id => $data ) {
				$this->payments_api_client->update_subscription_item( $id, $data );
			}
		}

		$discount_data = $this->get_repair_data_for_wcpay_discounts( $wcpay_discount_data, $subscription );

		if ( isset( $discount_data ) ) {
			$response = $this->payments_api_client->update_subscription(
				WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription ),
				[ 'discounts' => $discount_data ]
			);

			WC_Payments_Subscription_Service::set_wcpay_discount_ids( $subscription, $response['discounts'] );
		}
	}

	/**
	 * Sets the subscription's last invoice ID meta for WC subscription.
	 *
	 * @param WC_Order $order      The order.
	 * @param string   $invoice_id The invoice ID.
	 */
	public function set_order_invoice_id( WC_Order $order, string $invoice_id ) {
		$order->update_meta_data( self::ORDER_INVOICE_ID_KEY, $invoice_id );
		$order->save();
	}

	/**
	 * Sets the subscription's last invoice ID meta for WC subscription.
	 *
	 * @param WC_Subscription $subscription      The subscription.
	 * @param string          $parent_invoice_id The parent order invoice ID.
	 */
	public function set_subscription_invoice_id( WC_Subscription $subscription, string $parent_invoice_id ) {
		$subscription->update_meta_data( self::ORDER_INVOICE_ID_KEY, $parent_invoice_id );
		$subscription->save();
	}

	/**
	 * Retrieves the intent object and adds its data to the order.
	 *
	 * @param WC_Order $order The order to update.
	 * @param string   $intent_id The intent ID.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function get_and_attach_intent_info_to_order( $order, $intent_id ) {
		try {
			$request = Get_Intention::create( $intent_id );
			$request->set_hook_args( $order );
			$intent_object = $request->send();

		} catch ( API_Exception $e ) {
			$order->add_order_note( __( 'The payment info couldn\'t be added to the order.', 'woocommerce-payments' ) );
			return;
		}

		$this->order_service->attach_intent_info_to_order( $order, $intent_object );
	}

	/**
	 * Sends a request to server to record the store's context for an invoice payment.
	 *
	 * @param string $invoice_id The subscription invoice ID.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function record_subscription_payment_context( string $invoice_id ) {
		return $this->payments_api_client->update_invoice(
			$invoice_id,
			[
				'subscription_context' => class_exists( 'WC_Subscriptions' ) && WC_Payments_Features::is_stripe_billing_enabled() ? 'stripe_billing' : 'legacy_wcpay_subscription',
			]
		);
	}

	/**
	 * Sends a request to server to update transaction details.
	 *
	 * @param array    $invoice Invoice details.
	 * @param WC_Order $order Order details.
	 *
	 * @return void
	 * @throws API_Exception
	 */
	public function update_transaction_details( array $invoice, WC_Order $order ) {
		if ( ! isset( $invoice['charge'] ) ) {
			return;
		}

		$charge = $this->payments_api_client->get_charge( $invoice['charge'] );
		if ( ! isset( $charge['balance_transaction'] ) || ! isset( $charge['balance_transaction']['id'] ) ) {
			return;
		}

		$this->payments_api_client->update_transaction(
			$charge['balance_transaction']['id'],
			[
				'customer_first_name' => $order->get_billing_first_name(),
				'customer_last_name'  => $order->get_billing_last_name(),
				'customer_email'      => $order->get_billing_email(),
				'customer_country'    => $order->get_billing_country(),
			]
		);
	}

	/**
	 * Update a charge with the order id from invoice.
	 *
	 * @param array $invoice Invoice details.
	 * @param int   $order_id Order ID.
	 *
	 * @return void
	 * @throws API_Exception
	 */
	public function update_charge_details( array $invoice, int $order_id ) {
		if ( ! isset( $invoice['charge'] ) ) {
			return;
		}
		$this->payments_api_client->update_charge(
			$invoice['charge'],
			[
				'metadata' => [ 'order_id' => $order_id ],
			]
		);
	}

	/**
	 * Get recurring items of passed item (subscription).
	 *
	 * @param WC_Subscription $item Subscription to get recurring items for.
	 *
	 * @return array
	 */
	public function get_recurring_items( $item ) {
		// Subscription service has this service as a dependency, so we can't inject it via constructor.
		// With this we can mock this function in tests to return whatever we want.
		return WC_Payments_Subscriptions::get_subscription_service()->get_recurring_item_data_for_subscription( $item );
	}

	/**
	 * Get the WCPay subscription item ID for a WC subscription item.
	 *
	 * @param WC_Order_Item $item The WC subscription item.
	 *
	 * @return string The WCPay subscription item ID.
	 */
	public function get_wcpay_item_id( $item ) {
		return WC_Payments_Subscription_Service::get_wcpay_subscription_item_id( $item );
	}

	/**
	 * Sets the subscription last invoice ID meta for WC subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 * @param string          $invoice_id   The invoice ID.
	 */
	private function set_pending_invoice_id( $subscription, string $invoice_id ) {
		$subscription->update_meta_data( self::PENDING_INVOICE_ID_KEY, $invoice_id );
		$subscription->save();
	}

	/**
	 * Gets repair data for WCPay invoice items.
	 *
	 * @param array           $wcpay_item_data The WCPay invoice items.
	 * @param WC_Subscription $subscription    The WC Subscription object.
	 *
	 * @return array Repair data.
	 *
	 * @throws Rest_Request_Exception WCPay invoice items do not match WC subscription items.
	 */
	private function get_repair_data_for_wcpay_items( array $wcpay_item_data, WC_Subscription $subscription ): array {
		$repair_data        = [];
		$wcpay_items        = [];
		$subscription_items = $subscription->get_items( [ 'line_item', 'fee', 'shipping', 'tax' ] );

		foreach ( $wcpay_item_data as $item ) {
			$wcpay_subscription_item_id = $item['subscription_item'];

			$wcpay_items[ $wcpay_subscription_item_id ] = [
				'unit_amount'      => $item['price']['unit_amount_decimal'],
				'billing_period'   => $item['price']['recurring']['interval'],
				'billing_interval' => $item['price']['recurring']['interval_count'],
				'currency'         => $item['price']['currency'],
				'quantity'         => $item['quantity'],
			];
		}

		// Generate any repair data necessary to update the WCPay Subscription so it matches the WC subscription.
		$recurring_items = $this->get_recurring_items( $subscription );
		foreach ( $recurring_items  as $recurring_item_data ) {
			$item_id       = $recurring_item_data['metadata']['wc_item_id'];
			$item          = $subscription_items[ $item_id ];
			$wcpay_item_id = $this->get_wcpay_item_id( $item );

			if ( ! isset( $wcpay_items[ $wcpay_item_id ] ) ) {
				$message = __( 'The WooPayments invoice items do not match WC subscription items.', 'woocommerce-payments' );
				Logger::error( $message );
				throw new Rest_Request_Exception( $message );
			}

			// Check the quantity matches between WC and WCPay.
			if ( $item->is_type( 'line_item' ) && $wcpay_items[ $wcpay_item_id ]['quantity'] !== $recurring_item_data['quantity'] ) {
				$repair_data[ $wcpay_item_id ]['quantity'] = $recurring_item_data['quantity'];
			}

			// Confirm the line item amount matches between WC and WCPay.
			$unit_amounts_match = (string) $wcpay_items[ $wcpay_item_id ]['unit_amount'] === (string) $recurring_item_data['price_data']['unit_amount_decimal'];

			if ( ! $unit_amounts_match ) {
				$price_data = $recurring_item_data['price_data'];

				// We need to maintain the WCPay subscription's billing terms and currency. ie We cannot update the recurring period, interval or currency mid term.
				$price_data['currency']  = $wcpay_items[ $wcpay_item_id ]['currency'];
				$price_data['recurring'] = [
					'interval'       => $wcpay_items[ $wcpay_item_id ]['billing_period'],
					'interval_count' => $wcpay_items[ $wcpay_item_id ]['billing_interval'],
				];

				$repair_data[ $wcpay_item_id ]['price_data'] = $price_data;
			}
		}

		return $repair_data;
	}

	/**
	 * Gets repair data for WCPay invoice discounts.
	 *
	 * @param array           $wcpay_discount_data The WCPay discounts.
	 * @param WC_Subscription $subscription        The WC Subscription object.
	 *
	 * @return mixed
	 */
	private function get_repair_data_for_wcpay_discounts( array $wcpay_discount_data, WC_Subscription $subscription ) {
		$repair_data               = null;
		$subscription_discount_ids = WC_Payments_Subscription_Service::get_wcpay_discount_ids( $subscription );

		if ( ! empty( $subscription_discount_ids ) || ! empty( $wcpay_discount_data ) ) {
			if ( count( $subscription_discount_ids ) !== count( $wcpay_discount_data ) ) {
				$repair_data = WC_Payments_Subscription_Service::get_discount_item_data_for_subscription( $subscription );
			} else {
				foreach ( $subscription_discount_ids as $discount_id ) {
					if ( ! in_array( $discount_id, $wcpay_discount_data, true ) ) {
						$repair_data = WC_Payments_Subscription_Service::get_discount_item_data_for_subscription( $subscription );
						break;
					}
				}
			}
		}

		return $repair_data;
	}
}
