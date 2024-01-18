<?php
/**
 * Class WC_Payments_Invoice_Service
 *
 * @package WooCommerce\Payments
 */

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
	 * Client for making requests to the WooCommerce Payments API.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Product Service
	 *
	 * @var WC_Payments_Product_Service
	 */
	private $product_service;


	/**
	 * Order Service
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client      $payments_api_client  WooCommerce Payments API client.
	 * @param WC_Payments_Product_Service $product_service      Product Service.
	 * @param WC_Payments_Order_Service   $order_service              WC payments Order Service.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Product_Service $product_service,
		WC_Payments_Order_Service $order_service
	) {
		$this->payments_api_client = $payments_api_client;
		$this->product_service     = $product_service;
		$this->order_service       = $order_service;

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

		add_action( 'woocommerce_order_payment_status_changed', [ $this, 'maybe_record_invoice_payment' ], 10, 1 );
		add_action( 'woocommerce_renewal_order_payment_complete', [ $this, 'maybe_record_invoice_payment' ], 11, 1 );
	}

	/**
	 * Gets the subscription last invoice ID from WC subscription.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 *
	 * @return string Invoice ID.
	 */
	public static function get_pending_invoice_id( $subscription ) : string {
		return $subscription->get_meta( self::PENDING_INVOICE_ID_KEY, true );
	}

	/**
	 * Gets the invoice ID from a WC order.
	 *
	 * @param WC_Order $order The order.
	 * @return string Invoice ID.
	 */
	public static function get_order_invoice_id( WC_Order $order ) : string {
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
	 * @param int $order_id The WC order ID.
	 * @throws API_Exception If the request to mark the invoice as paid fails.
	 */
	public function maybe_record_invoice_payment( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || self::get_order_invoice_id( $order ) ) {
			return;
		}

		foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] ) as $subscription ) {
			$invoice_id = self::get_subscription_invoice_id( $subscription );

			if ( ! $invoice_id || ! WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
				continue;
			}

			try {
				// Set the invoice status to paid but don't charge the customer by using paid_out_of_band parameter.
				$this->payments_api_client->charge_invoice( $invoice_id, [ 'paid_out_of_band' => 'true' ] );
			} catch ( API_Exception $e ) {
				// If the invoice was already paid, silently handle that error. Throw all other exceptions.
				if ( WP_Http::BAD_REQUEST === $e->get_http_code() && false !== stripos( $e->getMessage(), 'invoice is already paid' ) ) {
					Logger::info( sprintf( 'Invoice for subscription #%s has already been paid.', $subscription->get_id() ) );
				} else {
					throw $e;
				}
			}

			if ( $subscription->is_manual() ) {
				$subscription->set_requires_manual_renewal( false );
				$subscription->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );

				// Copy the payment token used to pay for the order to the subscription.
				WC_Payments::get_gateway()->update_failing_payment_method( $subscription, $order );
				$subscription->save();
			}
		}
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

		$charge = $intent_object->get_charge();

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
		if ( !isset( $charge['balance_transaction'] ) || !isset( $charge['balance_transaction']['id'] ) ) {
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
	 * @param array $invoice
	 * @param int   $order_id
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
				'metadata' => ['order_id' => $order_id ],
			]
		);
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
	private function get_repair_data_for_wcpay_items( array $wcpay_item_data, WC_Subscription $subscription ) : array {
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
		foreach ( WC_Payments_Subscriptions::get_subscription_service()->get_recurring_item_data_for_subscription( $subscription ) as $recurring_item_data ) {
			$item_id       = $recurring_item_data['metadata']['wc_item_id'];
			$item          = $subscription_items[ $item_id ];
			$wcpay_item_id = WC_Payments_Subscription_Service::get_wcpay_subscription_item_id( $item );

			if ( ! isset( $wcpay_items[ $wcpay_item_id ] ) ) {
				$message = __( 'The WCPay invoice items do not match WC subscription items.', 'woocommerce-payments' );
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
