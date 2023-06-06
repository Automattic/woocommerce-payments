<?php
/**
 * Class WC_Payments_Subscription_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Logger;

/**
 * Subscriptions logic for WCPay Subscriptions
 */
class WC_Payments_Subscription_Service {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * WCPay subscriptions endpoint on server.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_API_PATH = '/subscriptions';

	/**
	 * Subscription meta key used to store WCPay subscription's ID.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_ID_META_KEY = '_wcpay_subscription_id';

	/**
	 * Subscription item meta key used to store WCPay subscription item's ID.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_ITEM_ID_META_KEY = '_wcpay_subscription_item_id';

	/**
	 * Subscription discounts meta key used to store WCPay subscription discount IDs.
	 *
	 * @const string
	 */
	const SUBSCRIPTION_DISCOUNT_IDS_META_KEY = '_wcpay_subscription_discount_ids';

	/**
	 * WC Payments API Client
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Customer Service
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * Product Service
	 *
	 * @var WC_Payments_Product_Service
	 */
	private $product_service;

	/**
	 * Invoice Service
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private $invoice_service;

	/**
	 * The features WCPay Subscriptions Support.
	 *
	 * @var array
	 */
	private $supports = [
		'gateway_scheduled_payments',
		'multiple_subscriptions',
		'subscription_cancellation',
		'subscription_payment_method_change_admin',
		'subscription_payment_method_change_customer',
		'subscription_payment_method_change',
		'subscription_reactivation',
		'subscription_suspension',
		'subscriptions',
	];

	/**
	 * A set of temporary exceptions to the limited feature support.
	 *
	 * @var array
	 */
	private $feature_support_exceptions = [];

	/**
	 * Whether the current request is creating a WCPay subscription when
	 * updating the subscription payment method from the "My account" page.
	 *
	 * @var bool
	 */
	private $is_creating_subscription_from_update_payment_method = false;

	/**
	 * WC Payments Subscriptions Constructor.
	 *
	 * Attaches callbacks for managing WC Subscriptions.
	 *
	 * @param WC_Payments_API_Client       $api_client       WC payments API Client.
	 * @param WC_Payments_Customer_Service $customer_service WC payments customer service.
	 * @param WC_Payments_Product_Service  $product_service  WC payments Products service.
	 * @param WC_Payments_Invoice_Service  $invoice_service  WC payments Invoice service.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Customer_Service $customer_service,
		WC_Payments_Product_Service $product_service,
		WC_Payments_Invoice_Service $invoice_service
	) {

		$this->payments_api_client = $api_client;
		$this->customer_service    = $customer_service;
		$this->product_service     = $product_service;
		$this->invoice_service     = $invoice_service;

		/**
		 * When a store is in staging mode, we don't want any subscription updates or purchases to be sent to the server.
		 *
		 * Sending these requests from staging sites can have unintended consequences for the live store. For example,
		 * Subscriptions which renew on the staging site will lead to pausing the shared subscription record at Stripe
		 * and that will result in inexplicable paused subscriptions and missed renewal payments for the live site.
		 */
		if ( WC_Payments_Subscriptions::is_duplicate_site() ) {
			return;
		}

		if ( ! $this->is_subscriptions_plugin_active() ) {
			add_action( 'woocommerce_checkout_subscription_created', [ $this, 'create_subscription' ] );
			add_action( 'woocommerce_renewal_order_payment_complete', [ $this, 'create_subscription_for_manual_renewal' ] );
			add_action( 'woocommerce_subscription_payment_method_updated', [ $this, 'maybe_create_subscription_from_update_payment_method' ], 10, 2 );
		}

		add_action( 'woocommerce_subscription_status_cancelled', [ $this, 'cancel_subscription' ] );
		add_action( 'woocommerce_subscription_status_expired', [ $this, 'cancel_subscription' ] );
		add_action( 'woocommerce_subscription_status_on-hold', [ $this, 'handle_subscription_status_on_hold' ] );
		add_action( 'woocommerce_subscription_status_pending-cancel', [ $this, 'set_pending_cancel_for_subscription' ] );
		add_action( 'woocommerce_subscription_status_pending-cancel_to_active', [ $this, 'reactivate_subscription' ] );
		add_action( 'woocommerce_subscription_status_on-hold_to_active', [ $this, 'reactivate_subscription' ] );

		// Save the new token on the WCPay subscription when it's added to a WC subscription.
		add_action( 'woocommerce_payment_token_added_to_order', [ $this, 'update_wcpay_subscription_payment_method' ], 10, 3 );
		add_filter( 'woocommerce_subscription_payment_gateway_supports', [ $this, 'prevent_wcpay_subscription_changes' ], 10, 3 );
		add_filter( 'woocommerce_order_actions', [ $this, 'prevent_wcpay_manual_renewal' ], 11, 1 );

		add_action( 'woocommerce_payments_changed_subscription_payment_method', [ $this, 'maybe_attempt_payment_for_subscription' ], 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'show_wcpay_subscription_id' ] );
	}

	/**
	 * Checks if the WC subscription has a first payment date that is in the future.
	 *
	 * @param WC_Subscription $subscription WC subscription to check if first payment is now or delayed.
	 *
	 * @return bool Whether the first payment is delayed.
	 */
	public static function has_delayed_payment( WC_Subscription $subscription ) {
		$trial_end = $subscription->get_time( 'trial_end' );
		$has_sync  = false;

		if ( WC_Subscriptions_Synchroniser::is_syncing_enabled() && WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription ) ) {
			$has_sync = true;

			foreach ( $subscription->get_items() as $item ) {
				$synced_payment_date = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $item->get_product(), 'timestamp' );

				// Check if the subscription starts from today because in those cases we don't need a dynamic trial period to align to the payment date.
				if ( WC_Subscriptions_Synchroniser::is_today( $synced_payment_date ) ) {
					$has_sync = false;
					break;
				}
			}
		}

		return $has_sync || $trial_end > time();
	}

	/**
	 * Gets the WC subscription associated with a WCPay subscription ID.
	 *
	 * @param string $wcpay_subscription_id WCPay subscription ID.
	 *
	 * @return WC_Subscription|bool The WC subscription or false if it can't be found.
	 */
	public static function get_subscription_from_wcpay_subscription_id( string $wcpay_subscription_id ) {
		$subscriptions = wcs_get_subscriptions(
			[
				'subscriptions_per_page' => 1,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => self::SUBSCRIPTION_ID_META_KEY,
						'value' => $wcpay_subscription_id,
					],
				],
			]
		);

		return empty( $subscriptions ) ? false : reset( $subscriptions );
	}

	/**
	 * Gets the WCPay subscription ID from a WC subscription.
	 *
	 * @param WC_Subscription $subscription WC Subscription.
	 *
	 * @return string
	 */
	public static function get_wcpay_subscription_id( WC_Subscription $subscription ) {
		return $subscription->get_meta( self::SUBSCRIPTION_ID_META_KEY, true );
	}

	/**
	 * Gets the WCPay subscription item ID from a WC subscription item.
	 *
	 * @param WC_Order_Item $item WC Item.
	 *
	 * @return string
	 */
	public static function get_wcpay_subscription_item_id( WC_Order_Item $item ) {
		return $item->get_meta( self::SUBSCRIPTION_ITEM_ID_META_KEY, true );
	}

	/**
	 * Gets the WCPay subscription discount IDs from a WC subscription.
	 *
	 * @param WC_Subscription $subscription WC Subscription.
	 *
	 * @return array
	 */
	public static function get_wcpay_discount_ids( WC_Subscription $subscription ) {
		return $subscription->get_meta( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, true );
	}

	/**
	 * Sets Stripe discount ids on WC subscription.
	 *
	 * @param WC_Subscription $subscription The WC Subscription object.
	 * @param array           $discounts    The WCPay discount data.
	 *
	 * @return void
	 */
	public static function set_wcpay_discount_ids( WC_Subscription $subscription, array $discounts ) {
		$subscription->update_meta_data( self::SUBSCRIPTION_DISCOUNT_IDS_META_KEY, $discounts );
		$subscription->save();
	}

	/**
	 * Determines if a given WC subscription is a WCPay subscription.
	 *
	 * On duplicate sites (staging or dev environments) all WCPay Subscrptions are disabled and so return false.
	 * This is to avoid dev environments interacting with WCPay Subscriptions and communicating on behalf of the live store.
	 *
	 * @param WC_Subscription $subscription WC Subscription object.
	 *
	 * @return bool
	 */
	public static function is_wcpay_subscription( WC_Subscription $subscription ) : bool {
		return ! WC_Payments_Subscriptions::is_duplicate_site() && WC_Payment_Gateway_WCPay::GATEWAY_ID === $subscription->get_payment_method() && (bool) self::get_wcpay_subscription_id( $subscription );
	}

	/**
	 * Formats item data.
	 *
	 * @param string $currency          The item's currency.
	 * @param string $wcpay_product_id  The item's Stripe product id.
	 * @param float  $unit_amount       The item's unit amount.
	 * @param string $interval          The item's interval. Optional.
	 * @param int    $interval_count    The item's interval count. Optional.
	 *
	 * @return array Structured invoice item array.
	 */
	public static function format_item_price_data( string $currency, string $wcpay_product_id, float $unit_amount, string $interval = '', int $interval_count = 0 ) : array {
		$data = [
			'currency'            => $currency,
			'product'             => $wcpay_product_id,
			// We cannot use WC_Payments_Utils::prepare_amount() here because it returns an int but 'unit_amount_decimal' supports multiple decimal places even though it is cents (fractions of a cent).
			'unit_amount_decimal' => round( $unit_amount, wc_get_rounding_precision() ),
		];

		// Convert the amount to cents if it's not in a zero based currency.
		if ( ! WC_Payments_Utils::is_zero_decimal_currency( strtolower( $currency ) ) ) {
			$data['unit_amount_decimal'] *= 100;
		}

		if ( $interval && $interval_count ) {
			$data['recurring'] = [
				'interval'       => $interval,
				'interval_count' => $interval_count,
			];
		}

		return $data;
	}

	/**
	 * Prepares discount data used to create a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription used to create the subscription on server.
	 *
	 * @return array WCPay discount item data.
	 */
	public static function get_discount_item_data_for_subscription( WC_Subscription $subscription ) : array {
		$data = [];

		foreach ( $subscription->get_items( 'coupon' ) as $item ) {
			$code     = $item->get_code();
			$coupon   = new WC_Coupon( $code );
			$duration = in_array( $coupon->get_discount_type(), [ 'recurring_fee', 'recurring_percent' ], true ) ? 'forever' : 'once';
			$discount = $item->get_discount();

			if ( $discount ) {
				$data[] = [
					'amount_off' => WC_Payments_Utils::prepare_amount( $discount, $subscription->get_currency() ),
					'currency'   => $subscription->get_currency(),
					'duration'   => $duration,
					// Translators: %s Coupon code.
					'name'       => sprintf( __( 'Coupon - %s', 'woocommerce-payments' ), $code ),
				];
			}
		}

		return $data;
	}

	/**
	 * Gets a WCPay subscription from a WC subscription object.
	 *
	 * @param WC_Subscription $subscription The WC subscription to get from server.
	 *
	 * @return array|bool WCPay subscription data, otherwise false.
	 */
	public function get_wcpay_subscription( WC_Subscription $subscription ) {
		$wcpay_subscription_id = self::get_wcpay_subscription_id( $subscription );

		if ( ! $wcpay_subscription_id ) {
			return false;
		}

		try {
			return $this->payments_api_client->get_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			return false;
		}
	}

	/**
	 * Creates a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC order used to create a wcpay subscription on server.
	 *
	 * @return void
	 *
	 * @throws Exception Throws an exception to stop checkout processing and display message to customer.
	 */
	public function create_subscription( WC_Subscription $subscription ) {
		/*
		 * Bail early if the subscription payment method is not WooCommerce Payments.
		 * WCPay Subscriptions are not created in the following scenarios:
		 *
		 * - A different payment gateway was used to purchase the subscription (e.g. PayPal).
		 * - The subscription is free (i.e. $0) and payment details were not captured during checkout.
		 */
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return;
		}

		$checkout_error_message = __( 'There was a problem creating your subscription. Please try again or contact us for assistance.', 'woocommerce-payments' );
		$wcpay_customer_id      = $this->customer_service->get_customer_id_for_order( $subscription );

		if ( ! $wcpay_customer_id ) {
			Logger::error( 'There was a problem creating the WCPay subscription. WCPay customer ID missing.' );
			throw new Exception( $checkout_error_message );
		}

		try {
			$subscription_data = $this->prepare_wcpay_subscription_data( $wcpay_customer_id, $subscription );
			$this->validate_subscription_data( $subscription_data );

			$response = $this->payments_api_client->create_subscription( $subscription_data );

			$this->set_wcpay_subscription_id( $subscription, $response['id'] );
			$this->set_wcpay_subscription_item_ids( $subscription, $response['items']['data'] );

			if ( isset( $response['discounts'] ) ) {
				$this->set_wcpay_discount_ids( $subscription, $response['discounts'] );
			}

			if ( ! empty( $response['latest_invoice'] ) ) {
				$this->invoice_service->set_subscription_invoice_id( $subscription, $response['latest_invoice'] );
			}
		} catch ( \Exception $e ) {
			Logger::log( sprintf( 'There was a problem creating the WCPay subscription. %s', $e->getMessage() ) );

			if ( $e instanceof Amount_Too_Small_Exception ) {
				throw new Exception(
					sprintf(
						// Translators: The %1 placeholder is a currency formatted price string ($0.50). The %2 and %3 placeholders are opening and closing strong HTML tags.
						__( 'There was a problem creating your subscription. %1$s doesn\'t meet the %2$sminimum recurring amount%3$s this payment method can process.', 'woocommerce-payments' ),
						wc_price( $subscription->get_total() ),
						'<strong>',
						'</strong>'
					)
				);
			}

			throw new Exception( $checkout_error_message );
		}
	}

	/**
	 * Conditionally creates a WCPay subscription when a subscriber
	 * updates the subscription payment method from their account page.
	 *
	 * @param WC_Subscription $subscription       An instance of a WC_Subscription object.
	 * @param string          $new_payment_method The ID of the new payment method.
	 *
	 * @return void
	 */
	public function maybe_create_subscription_from_update_payment_method( WC_Subscription $subscription, string $new_payment_method ) {
		// Not changing the subscription payment method to WooCommerce Payments, bail.
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $new_payment_method ) {
			return;
		}

		// We already have a WCPay subscription ID, bail.
		if ( (bool) self::get_wcpay_subscription_id( $subscription ) ) {
			return;
		}

		$this->is_creating_subscription_from_update_payment_method = true;

		$this->create_subscription( $subscription );
	}

	/**
	 * Cancels the WCPay subscription when it's cancelled in WC.
	 *
	 * @param WC_Subscription $subscription The WC subscription that was canceled.
	 *
	 * @return void
	 */
	public function cancel_subscription( WC_Subscription $subscription ) {
		$wcpay_subscription_id = self::get_wcpay_subscription_id( $subscription );

		if ( ! $wcpay_subscription_id ) {
			return;
		}

		try {
			$this->payments_api_client->cancel_subscription( $wcpay_subscription_id );
		} catch ( API_Exception $e ) {
			Logger::log( sprintf( 'There was a problem canceling the subscription on WCPay server: %s.', $e->getMessage() ) );
		}
	}

	/**
	 * Handle subscription status change to on-hold.
	 *
	 * @param WC_Subscription $subscription The WC subscription.
	 *
	 * @return void
	 */
	public function handle_subscription_status_on_hold( WC_Subscription $subscription ) {
		// Check if the subscription is a WCPay subscription before proceeding.
		// In stores that have WC Subscriptions active, or previously had WC S,
		// this method may be called with regular tokenised subscriptions.
		if ( ! $this->is_wcpay_subscription( $subscription ) ) {
			return;
		}

		$this->suspend_subscription( $subscription );

		// Add an order note as a visible record of suspend.
		$subscription->add_order_note( __( 'Suspended WCPay Subscription because subscription status changed to on-hold.', 'woocommerce-payments' ) );

		// Log that the subscription was suspended.
		// Include a brief stack trace to help determine where status change originated.
		// For example, admin user action, or a code interaction with customizations.
		$e     = new Exception();
		$trace = $e->getTraceAsString();
		Logger::log(
			sprintf(
				'Suspended WCPay Subscription because subscription status changed to on-hold. WC ID: %d; WCPay ID: %s; stack: %s',
				$subscription->get_id(),
				self::get_wcpay_subscription_id( $subscription ),
				$trace
			)
		);
	}

	/**
	 * Suspends a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription to suspend.
	 *
	 * @return void
	 */
	public function suspend_subscription( WC_Subscription $subscription ) {
		// Check if the subscription is a WCPay subscription before proceeding.
		if ( ! $this->is_wcpay_subscription( $subscription ) ) {
			Logger::log(
				sprintf(
					'Aborting WC_Payments_Subscription_Service::suspend_subscription; subscription is a tokenised (non WCPay) subscription. WC ID: %d.',
					$subscription->get_id()
				)
			);
			return;
		}

		$this->update_subscription( $subscription, [ 'pause_collection' => [ 'behavior' => 'void' ] ] );
	}

	/**
	 * Reactivates the WCPay subscription when the WC subscription is activated.
	 * This is done by making a request to server to unset the "cancellation at end of period" value for the WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription that was activated.
	 *
	 * @return void
	 */
	public function reactivate_subscription( WC_Subscription $subscription ) {
		$this->update_subscription(
			$subscription,
			[
				'cancel_at_period_end' => 'false',
				'pause_collection'     => '',
			]
		);
	}

	/**
	 * Marks the WCPay subscription as pending-cancel by setting the "cancellation at end of period" on the WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The subscription that was set as pending cancel.
	 *
	 * @return void
	 */
	public function set_pending_cancel_for_subscription( WC_Subscription $subscription ) {
		$this->update_subscription( $subscription, [ 'cancel_at_period_end' => 'true' ] );
	}

	/**
	 * When a WC Subscription's payment method has been updated make sure we attach
	 * the new payment method ID to the WCPay subscription.
	 *
	 * If the WCPay subscription's payment method was updated while there's a failed invoice, trigger a retry.
	 *
	 * @param int              $post_id  Post ID (WC subscription ID) that had its payment method updated.
	 * @param int              $token_id Payment Token post ID stored in DB.
	 * @param WC_Payment_Token $token    Payment Token object.
	 *
	 * @return void
	 */
	public function update_wcpay_subscription_payment_method( int $post_id, int $token_id, WC_Payment_Token $token ) {
		$subscription = wcs_get_subscription( $post_id );

		if ( $subscription ) {
			$wcpay_subscription_id   = $this->get_wcpay_subscription_id( $subscription );
			$wcpay_payment_method_id = $token->get_token();

			if ( $wcpay_subscription_id && $wcpay_payment_method_id ) {
				try {
					$this->update_subscription( $subscription, [ 'default_payment_method' => $wcpay_payment_method_id ] );
				} catch ( API_Exception $e ) {
					Logger::error( sprintf( 'There was a problem updating the WCPay subscription\'s default payment method on server: %s.', $e->getMessage() ) );
					return;
				}
			}
		}
	}

	/**
	 * Attempts payment for WCPay subscription if needed.
	 *
	 * @param WC_Subscription  $subscription WC subscription linked to the WCPay subscription that maybe needs to retry payment.
	 * @param WC_Payment_Token $token        The new subscription token to assign to the invoice order.
	 *
	 * @return void
	 */
	public function maybe_attempt_payment_for_subscription( $subscription, WC_Payment_Token $token ) {

		if ( ! wcs_is_subscription( $subscription ) ) {
			return;
		}

		$wcpay_invoice_id = WC_Payments_Invoice_Service::get_pending_invoice_id( $subscription );

		if ( ! $wcpay_invoice_id ) {
			return;
		}

		$response = $this->payments_api_client->charge_invoice( $wcpay_invoice_id );

		// Rather than wait for the Stripe webhook to be received, complete the order now if it was successfully paid.
		if ( $response && isset( $response['status'] ) && 'paid' === $response['status'] ) {
			// Remove the pending invoice ID now that we know it has been paid.
			$this->invoice_service->mark_pending_invoice_paid_for_subscription( $subscription );

			$order_id = WC_Payments_Invoice_Service::get_order_id_by_invoice_id( $wcpay_invoice_id );
			$order    = $order_id ? wc_get_order( $order_id ) : false;

			if ( $order && $order->needs_payment() ) {
				// We're about to record a successful payment, temporarily remove the "is request to change payment method" flag as it prevents us from activating the subscrption via WC_Subscription::payment_complete().
				$is_change_payment_request = WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
				WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;

				// We need to store the successful token on the order otherwise WC_Subscriptions_Change_Payment_Gateway::change_failing_payment_method() will override the successful token with the failing one.
				$order->add_payment_token( $token );
				$order->payment_complete();

				// Reinstate the "is request to change payment method" flag.
				WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = $is_change_payment_request;
				wc_add_notice( __( "We've successfully collected payment for your subscription using your new payment method.", 'woocommerce-payments' ) );
			}
		}
	}

	/**
	 * Whether the subscription supports a given feature.
	 *
	 * @param bool            $supported    Is feature supported.
	 * @param string          $feature      Feature flag.
	 * @param WC_Subscription $subscription WC Subscription to check if feature is supported against.
	 *
	 * @return bool
	 */
	public function prevent_wcpay_subscription_changes( bool $supported, string $feature, WC_Subscription $subscription ) {

		if ( ! self::is_wcpay_subscription( $subscription ) ) {
			return $supported;
		}

		return in_array( $feature, $this->supports, true ) || isset( $this->feature_support_exceptions[ $subscription->get_id() ][ $feature ] );
	}

	/**
	 * Remove pending parent and renewal order creation from admin edit subscriptions page.
	 *
	 * @param array $actions Array of available actions.
	 * @return array Array of updated actions.
	 */
	public function prevent_wcpay_manual_renewal( array $actions ) {
		global $theorder;

		if ( wcs_is_subscription( $theorder ) && self::is_wcpay_subscription( $theorder ) ) {
			unset(
				$actions['wcs_create_pending_parent'],
				$actions['wcs_create_pending_renewal'],
				$actions['wcs_process_renewal']
			);
		}
		return $actions;
	}

	/**
	 * Show WCPay Subscription ID on Edit Subscription page.
	 *
	 * @param WC_Order|WC_Subscription $order The order object.
	 */
	public function show_wcpay_subscription_id( WC_Order $order ) {
		if ( ! wcs_is_subscription( $order ) || ! self::is_wcpay_subscription( $order ) ) {
			return;
		}

		$wcpay_subscription_id = self::get_wcpay_subscription_id( $order );
		if ( ! $wcpay_subscription_id ) {
			return;
		}

		echo '<p><strong>' . esc_html__( 'WooCommerce Payments Subscription ID', 'woocommerce-payments' ) . ':</strong> ' . esc_html( $wcpay_subscription_id ) . '</p>';
	}

	/**
	 * Updates a subscription's next payment date to match the WCPay subscription's payment date.
	 *
	 * @param array           $wcpay_subscription The WCPay Subscription data.
	 * @param WC_Subscription $subscription       The WC Subscription object.
	 *
	 * @return void
	 */
	public function update_dates_to_match_wcpay_subscription( array $wcpay_subscription, WC_Subscription $subscription ) {
		// Temporarily allow date changes when we're updating dates to match the dates on the WCPay subscription.
		$this->set_feature_support_exception( $subscription, 'subscription_date_changes' );

		$next_payment_date = gmdate( 'Y-m-d H:i:s', $wcpay_subscription['current_period_end'] );
		$subscription->update_dates( [ 'next_payment' => $next_payment_date ] );

		$next_payment_time_difference = absint( $wcpay_subscription['current_period_end'] - $subscription->get_time( 'next_payment' ) );

		if ( $next_payment_time_difference > 0 && $next_payment_time_difference >= 12 * HOUR_IN_SECONDS ) {
			$subscription->add_order_note( __( 'The subscription\'s next payment date has been updated to match WCPay server.', 'woocommerce-payments' ) );
		}

		// Remove the 'subscription_date_changes' exception.
		$this->clear_feature_support_exception( $subscription, 'subscription_date_changes' );
	}

	/**
	 * Creates a WCPay subscription on successful renewal payment for manual WC subscription.
	 *
	 * @param int $order_id WC Order ID.
	 */
	public function create_subscription_for_manual_renewal( int $order_id ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

		foreach ( $subscriptions as $subscription_id => $subscription ) {
			if ( ! self::get_wcpay_subscription_id( $subscription ) && $subscription->is_manual() ) {
				$this->create_subscription( $subscription );
			}
		}
	}

	/**
	 * Prepares item data used to create a WCPay subscription.
	 *
	 * @param string          $wcpay_customer_id WCPay Customer ID to create the subscription for.
	 * @param WC_Subscription $subscription      The WC subscription used to create the subscription on server.
	 *
	 * @return array WCPay subscription data
	 */
	private function prepare_wcpay_subscription_data( string $wcpay_customer_id, WC_Subscription $subscription ) {
		$recurring_items = $this->get_recurring_item_data_for_subscription( $subscription );
		$one_time_items  = $this->get_one_time_item_data_for_subscription( $subscription );
		$discount_items  = self::get_discount_item_data_for_subscription( $subscription );
		$data            = [
			'customer' => $wcpay_customer_id,
			'items'    => $recurring_items,
		];

		if ( self::has_delayed_payment( $subscription ) ) {
			$data['trial_end'] = max( $subscription->get_time( 'trial_end' ), $subscription->get_time( 'next_payment' ) );
		}

		if ( ! empty( $one_time_items ) ) {
			$data['add_invoice_items'] = $one_time_items;
		}

		if ( ! empty( $discount_items ) ) {
			$data['discounts'] = $discount_items;
		}

		if ( $this->is_creating_subscription_from_update_payment_method ) {
			$data['backdate_start_date']  = max( $subscription->get_time( 'start' ), $subscription->get_time( 'last_order_date_created' ), $subscription->get_time( 'last_order_date_paid' ) );
			$data['billing_cycle_anchor'] = $subscription->get_time( 'next_payment' );
		}

		return apply_filters( 'wcpay_subscriptions_prepare_subscription_data', $data );
	}

	/**
	 * Gets recurring item data from a subscription needed to create a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription to fetch product data from.
	 *
	 * @return array WCPay recurring item data.
	 */
	public function get_recurring_item_data_for_subscription( WC_Subscription $subscription ) : array {
		$data = [];

		foreach ( $subscription->get_items() as $item ) {
			$data[] = [
				'metadata'   => $this->get_item_metadata( $item ),
				'quantity'   => $item->get_quantity(),
				'price_data' => $this->format_item_price_data(
					$subscription->get_currency(),
					$this->product_service->get_wcpay_product_id( $item->get_product() ),
					$item->get_subtotal() / $item->get_quantity(),
					$subscription->get_billing_period(),
					$subscription->get_billing_interval()
				),
			];
		}

		$additional_items = array_merge( $subscription->get_fees(), $subscription->get_shipping_methods(), $subscription->get_taxes() );

		foreach ( $additional_items as $item ) {
			if ( is_a( $item, 'WC_Order_Item_Tax' ) ) {
				$item_name   = $item->get_label();
				$unit_amount = $item->get_tax_total() + $item->get_shipping_tax_total();
			} else {
				$item_name   = $item->get_type();
				$unit_amount = $item->get_total();
			}

			if ( $unit_amount ) {
				$data[] = [
					'metadata'   => $this->get_item_metadata( $item ),
					'price_data' => self::format_item_price_data(
						$subscription->get_currency(),
						$this->product_service->get_wcpay_product_id_for_item( $item_name ),
						$unit_amount,
						$subscription->get_billing_period(),
						$subscription->get_billing_interval()
					),
				];
			}
		}

		return $data;
	}

	/**
	 * Gets one time item data from a subscription needed to create a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription to fetch item data from.
	 *
	 * @return array WCPay one time item data.
	 */
	private function get_one_time_item_data_for_subscription( WC_Subscription $subscription ) : array {
		$data     = [];
		$currency = $subscription->get_currency();

		foreach ( $subscription->get_items() as $item ) {
			$product           = $item->get_product();
			$sign_up_fee       = (float) WC_Subscriptions_Product::get_sign_up_fee( $product );
			$one_time_shipping = WC_Subscriptions_Product::needs_one_time_shipping( $product );

			if ( $sign_up_fee ) {
				$wcpay_item_id = $this->product_service->get_wcpay_product_id_for_item( 'sign_up_fee' );
				$data[]        = [
					'price_data' => self::format_item_price_data( $currency, $wcpay_item_id, $sign_up_fee ),
				];
			}

			if ( $one_time_shipping ) {
				$wcpay_item_id = $this->product_service->get_wcpay_product_id_for_item( 'shipping' );
				$shipping      = 0;

				foreach ( $subscription->get_parent()->get_shipping_methods() as $shipping_method ) {
					$shipping += $shipping_method->get_total();
				}

				$data[] = [
					'price_data' => self::format_item_price_data( $currency, $wcpay_item_id, $shipping ),
				];
			}
		}

		return $data;
	}

	/**
	 * Updates a WCPay subscription.
	 *
	 * @param WC_Subscription $subscription The WC subscription that relates to the WCPay subscription that needs updating.
	 * @param array           $data         Data to update.
	 *
	 * @return array|null Updated wcpay subscription or null if there was an error.
	 */
	private function update_subscription( WC_Subscription $subscription, array $data ) {
		$wcpay_subscription_id = $this->get_wcpay_subscription_id( $subscription );
		$response              = null;

		if ( ! $wcpay_subscription_id ) {
			Logger::log( 'There was a problem updating the WCPay subscription in: Subscription does not contain a valid subscription ID.' );
			return;
		}

		try {
			$response = $this->payments_api_client->update_subscription( $wcpay_subscription_id, $data );
		} catch ( API_Exception $e ) {
			Logger::log( sprintf( 'There was a problem updating the WCPay subscription on server: %s', $e->getMessage() ) );
		}

		return $response;
	}

	/**
	 * Set the trial end date for the WCPay subscription (this updates both trial end as well as next payment).
	 *
	 * @param WC_Subscription $subscription WC subscription linked to the WCPay subscription that needs updating.
	 * @param int             $timestamp    Next payment or trial end timestamp in UTC.
	 *
	 * @return void
	 */
	private function set_trial_end_for_subscription( WC_Subscription $subscription, int $timestamp ) {
		$trial_end = 0 === $timestamp ? 'now' : $timestamp;
		$this->update_subscription( $subscription, [ 'trial_end' => $trial_end ] );
	}

	/**
	 * Sets the WCPay subscription ID meta for WC subscription.
	 *
	 * @param WC_Subscription $subscription WC Subscription to store meta against.
	 * @param string          $value        WCPay subscription ID meta value.
	 *
	 * @return void
	 */
	private function set_wcpay_subscription_id( WC_Subscription $subscription, string $value ) {
		$subscription->update_meta_data( self::SUBSCRIPTION_ID_META_KEY, $value );
		$subscription->save();
	}

	/**
	 * Sets Stripe subscription item ids on WC order items.
	 *
	 * @param WC_Subscription $subscription       The WC Subscription object.
	 * @param array           $subscription_items The WCPay Subscription data.
	 *
	 * @return void
	 */
	private function set_wcpay_subscription_item_ids( WC_Subscription $subscription, array $subscription_items ) {
		foreach ( $subscription_items as $item ) {
			$wcpay_subscription_item_id = $item['id'];
			$subscription_item_id       = isset( $item['metadata']['wc_item_id'] ) ? $item['metadata']['wc_item_id'] : false;

			if ( $subscription_item_id ) {
				$subscription_item = $subscription->get_item( $subscription_item_id );
				$subscription_item->update_meta_data( self::SUBSCRIPTION_ITEM_ID_META_KEY, $wcpay_subscription_item_id );
				$subscription_item->save();
			} else {
				Logger::log(
					sprintf(
						// Translators: %s Stripe subscription item ID.
						__( 'Unable to set subscription item ID meta for WCPay subscription item %s.', 'woocommerce-payments' ),
						$wcpay_subscription_item_id
					)
				);
			}
		}
	}

	/**
	 * Temporarily allows a subscription to bypass a payment gateway feature support flag.
	 *
	 * Use @see WC_Payments_Subscription_Service::clear_feature_support_exception() to clear it.
	 *
	 * @param WC_Subscription $subscription The subscription to set the exception for.
	 * @param string          $feature      The feature to allow.
	 */
	private function set_feature_support_exception( WC_Subscription $subscription, string $feature ) {
		$this->feature_support_exceptions[ $subscription->get_id() ][ $feature ] = true;
	}

	/**
	 * Clears a gateway support flag exception.
	 *
	 * Use @see WC_Payments_Subscription_Service::set_feature_support_exception() to set one.
	 *
	 * @param WC_Subscription $subscription The subscription to remove the exception for.
	 * @param string          $feature      The feature.
	 */
	private function clear_feature_support_exception( WC_Subscription $subscription, string $feature ) {
		unset( $this->feature_support_exceptions[ $subscription->get_id() ][ $feature ] );
	}

	/**
	 * Generates the metadata for a given WC_Order_Item
	 *
	 * @param WC_Order_Item|WC_Order_Item_Tax $item The order item to generate the metadata for. Can be any order item type including tax, shipping and fees.
	 * @return array Item metadata.
	 */
	private function get_item_metadata( WC_Order_Item $item ) {
		$metadata = [ 'wc_item_id' => $item->get_id() ];

		switch ( $item->get_type() ) {
			case 'tax':
				$metadata['wc_rate_id']  = $item->get_rate_id();
				$metadata['code']        = $item->get_rate_code();
				$metadata['rate']        = $item->get_rate_percent();
				$metadata['is_compound'] = wc_bool_to_string( $item->is_compound() );
				break;
			case 'shipping':
				$metadata['method'] = $item->get_name();
				break;
			case 'fee':
				$metadata['type'] = $item->get_name();
				break;
		}

		return $metadata;
	}

	/**
	 * Validates that the data used to create the WCPay Subscription.
	 *
	 * @param array $subscription_data The data used to create a WCPay subscription.
	 * @throws Exception If the subscription data contains invalid or missing data.
	 */
	private function validate_subscription_data( $subscription_data ) {

		if ( empty( $subscription_data['customer'] ) ) {
			throw new Exception( 'The "customer" arg is required to create the subscription.' );
		}

		if ( ! isset( $subscription_data['items'] ) ) {
			throw new Exception( 'The "items" arg is required to create the subscription.' );
		}

		foreach ( $subscription_data['items'] as $item_data ) {
			$required_price_keys  = [ 'currency', 'product', 'recurring' ];
			$required_period_keys = [ 'interval', 'interval_count' ];
			$errors               = [];

			if ( ! isset( $item_data['price_data']['unit_amount_decimal'] ) ) {
				$errors[] = 'unit_amount_decimal';
			}

			foreach ( $required_price_keys as $required_key ) {
				if ( empty( $item_data['price_data'][ $required_key ] ) ) {
					$errors[] = $required_key;
				}
			}

			foreach ( $required_period_keys as $required_price_key ) {
				if ( empty( $item_data['price_data']['recurring'][ $required_price_key ] ) ) {
					$errors[] = $required_price_key;
				}
			}

			if ( ! empty( $errors ) ) {
				$error_message = count( $errors ) > 1 ? 'The "%s" line item properties are required to create the subscription.' : 'The "%s" line item property is required to create the subscription.';
				throw new Exception( sprintf( $error_message, implode( '", "', $errors ) ) );
			}

			$billing_period   = $item_data['price_data']['recurring']['interval'];
			$billing_interval = $item_data['price_data']['recurring']['interval_count'];

			// Confirm the billing period is valid (no greater than 1 year in length).
			if ( ! $this->product_service->is_valid_billing_cycle( $billing_period, $billing_interval ) ) {
				throw new Exception( sprintf( 'The subscription billing period cannot be any longer than one year. A billing period of "every %s %s(s)" was given.', $billing_interval, $billing_period ) );
			}
		}
	}

	/**
	 * Determines if the store has any active WCPay subscriptions.
	 *
	 * @return bool True if store has active WCPay subscriptions, otherwise false.
	 */
	public static function store_has_active_wcpay_subscriptions() {
		$results = wcs_get_subscriptions(
			[
				'subscriptions_per_page' => 1,
				'subscription_status'    => 'active',
				// Ignoring phpcs warning, we need to search meta.
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => self::SUBSCRIPTION_ID_META_KEY,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$store_has_active_wcpay_subscriptions = count( $results ) > 0;
		return $store_has_active_wcpay_subscriptions;
	}
}
