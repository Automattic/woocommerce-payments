<?php
/**
 * Class WC_Payments_Invoice_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Rest_Request_Exception;
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
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client      $payments_api_client  WooCommerce Payments API client.
	 * @param WC_Payments_Product_Service $product_service      Product Service.
	 * @param WC_Payment_Gateway_WCPay    $gateway              WC payments Payment Gateway.
	 */
	public function __construct(
		WC_Payments_API_Client $payments_api_client,
		WC_Payments_Product_Service $product_service,
		WC_Payment_Gateway_WCPay $gateway
	) {
		$this->payments_api_client = $payments_api_client;
		$this->product_service     = $product_service;
		$this->gateway             = $gateway;

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
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT pm.post_id
				FROM {$wpdb->prefix}postmeta AS pm
				INNER JOIN {$wpdb->prefix}posts AS p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s AND pm.meta_value = %s
				",
				self::ORDER_INVOICE_ID_KEY,
				$invoice_id
			)
		);
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
	 * @param int $order_id The WC order ID.
	 */
	public function maybe_record_invoice_payment( int $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || self::get_order_invoice_id( $order ) ) {
			return;
		}

		foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] ) as $subscription ) {
			$invoice_id = self::get_subscription_invoice_id( $subscription );

			if ( ! $invoice_id ) {
				continue;
			}

			// Update the status of the invoice to paid but don't charge the customer by using paid_out_of_band parameter.
			$this->payments_api_client->charge_invoice( $invoice_id, [ 'paid_out_of_band' => 'true' ] );

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
	 */
	public function get_and_attach_intent_info_to_order( $order, $intent_id ) {
		try {
			$intent_object = $this->payments_api_client->get_intent( $intent_id );
		} catch ( API_Exception $e ) {
			$order->add_order_note( __( 'The payment info couldn\'t be added to the order.', 'woocommerce-payments' ) );
			return;
		}

		$this->gateway->attach_intent_info_to_order(
			$order,
			$intent_id,
			$intent_object->get_status(),
			$intent_object->get_payment_method_id(),
			$intent_object->get_customer_id(),
			$intent_object->get_charge_id(),
			$intent_object->get_currency()
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
		$repair_data = [];
		$wcpay_items = [];

		foreach ( $wcpay_item_data as $item ) {
			$wcpay_subscription_item_id = $item['subscription_item'];

			$wcpay_items[ $wcpay_subscription_item_id ] = [
				'amount'    => $item['amount'],
				'quantity'  => $item['quantity'],
				'tax_rates' => array_column( $item['tax_rates'], 'percentage' ),
			];
		}

		foreach ( $subscription->get_items( [ 'line_item', 'fee', 'shipping' ] ) as $item ) {
			$subscription_item_id = WC_Payments_Subscription_Service::get_wcpay_subscription_item_id( $item );

			if ( ! $subscription_item_id ) {
				continue;
			}

			if ( ! in_array( $subscription_item_id, array_keys( $wcpay_items ), true ) ) {
				$message = __( 'The WCPay invoice items do not match WC subscription items', 'woocommerce-payments' );
				Logger::error( $message );
				throw new Rest_Request_Exception( $message );
			}

			$item_data = $wcpay_items[ $subscription_item_id ];

			if ( (int) $item->get_total() * 100 !== $item_data['amount'] ) {
				if ( $item->is_type( 'line_item' ) ) {
					$product                                       = $item->get_product();
					$repair_data[ $subscription_item_id ]['price'] = $this->product_service->get_wcpay_price_id( $product );
				} else {
					$repair_data[ $subscription_item_id ]['price_data'] = WC_Payments_Subscription_Service::format_item_price_data(
						$subscription->get_currency(),
						$this->product_service->get_wcpay_product_id_for_item( $item->get_type() ),
						$item->get_total(),
						$subscription->get_billing_period(),
						$subscription->get_billing_interval()
					);
				}
			}

			if ( $item->get_quantity() !== $item_data['quantity'] ) {
				$repair_data[ $subscription_item_id ]['quantity'] = $item->get_quantity();
			}

			if ( ! empty( $item->get_taxes() ) ) {
				$tax_rate_ids = array_keys( $item->get_taxes()['total'] );

				if ( count( $tax_rate_ids ) !== count( $item_data['tax_rates'] ) ) {
					$repair_data[ $subscription_item_id ]['tax_rates'] = WC_Payments_Subscription_Service::get_tax_rates_for_item( $item, $subscription );
				} else {
					foreach ( $subscription->get_taxes() as $tax ) {
						if ( in_array( $tax->get_rate_id(), $tax_rate_ids, true ) && ! in_array( (int) $tax->get_rate_percent(), $item_data['tax_rates'], true ) ) {
							$repair_data[ $subscription_item_id ]['tax_rates'] = WC_Payments_Subscription_Service::get_tax_rates_for_item( $item, $subscription );
							break;
						}
					}
				}
			}
		}

		return $repair_data;
	}

	/**
	 * Gets repair data for WCPay invoice discounts.
	 *
	 * @param array           $wcpay_discount_data The WCPay disounts.
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
