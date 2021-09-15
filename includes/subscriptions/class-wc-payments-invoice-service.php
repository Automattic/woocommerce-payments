<?php
/**
 * Class WC_Payments_Invoice_Service
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;
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
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client WooCommerce Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;

		add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_record_first_invoice_payment' ], 10, 3 );
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
	 * Marks the initial subscription invoice as paid after a parent order is completed.
	 *
	 * When a subscription's parent order goes from a pending payment status to a payment completed status,
	 * make sure the invoice is marked as paid (without charging the customer since it was charged on checkout).
	 *
	 * @param int    $order_id   The order which is updating status.
	 * @param string $old_status The order's old status.
	 * @param string $new_status The order's new status.
	 */
	public function maybe_record_first_invoice_payment( int $order_id, string $old_status, string $new_status ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$needed_payment  = in_array( $old_status, apply_filters( 'woocommerce_valid_order_statuses_for_payment', [ 'pending', 'on-hold', 'failed' ], $order ), true );
		$order_completed = in_array( $new_status, [ apply_filters( 'woocommerce_payment_complete_order_status', 'processing', $order_id, $order ), 'processing', 'completed' ], true );

		if ( $needed_payment && $order_completed ) {
			foreach ( wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'parent' ] ) as $subscription ) {
				$invoice_id = self::get_subscription_invoice_id( $subscription );

				if ( ! $invoice_id ) {
					continue;
				}

				// Update the status of the invoice to paid but don't charge the customer by using paid_out_of_band parameter.
				$this->payments_api_client->charge_invoice( $invoice_id, [ 'paid_out_of_band' => 'true' ] );
			}
		}
	}

	/**
	 * Validates a Stripe invoice.
	 *
	 * @param array           $wcpay_items     The Stripe invoice items.
	 * @param array           $wcpay_discounts The Stripe invoice discounts.
	 * @param WC_Subscription $subscription    The WC Subscription object.
	 */
	public function validate_invoice_items( array $wcpay_items, array $wcpay_discounts, WC_Subscription $subscription ) {
		$wcpay_item_data = [];

		foreach ( $wcpay_items as $item ) {
			$wcpay_subscription_item_id = $item['subscription_item'];

			$wcpay_item_data[ $wcpay_subscription_item_id ] = [
				'amount'    => $item['amount'],
				'quantity'  => $item['quantity'],
				'tax_rates' => array_column( $item['tax_rates'], 'percentage' ),
			];
		}

		foreach ( $subscription->get_items( 'line_item', 'fee', 'shipping' ) as $item ) {
			$subscription_item_id = WC_Payments_Subscription_Service::get_wcpay_subscription_item_id( $item );

			if ( $wcpay_subscription_item_id !== $subscription_item_id ) {
				// Log and fix.
				return;
			}

			if ( (int) $item->get_total() * 100 !== $wcpay_item_data['amount'] ) {
				// Fix price.
				return;
			}

			if ( $item->get_quantity() !== $wcpay_item_data['quantity'] ) {
				// Fix quantity.
				return;
			}

			if ( $item->get_taxes() ) {
				foreach ( $item->get_taxes() as $tax ) {
					if ( ! in_array( (int) $tax->get_rate_percent(), $wcpay_item_data['tax_rates'], true ) ) {
						// Fix tax rates.
						return;
					}
				}
			}
		}
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
	 * Gets the invoice ID from a WC order.
	 *
	 * @param WC_Order $order The order.
	 * @return string Invoice ID.
	 */
	public static function get_order_invoice_id( WC_Order $order ) : string {
		return $order->get_meta( self::ORDER_INVOICE_ID_KEY, true );
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
}
