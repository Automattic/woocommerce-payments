<?php
/**
 * Class OrderService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Payments_Features;
use WC_Payments_Order_Service;
use WCPay\Constants\Payment_Type;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for managing orders.
 *
 * This service's public methods should only require order IDs rather than objects,
 * avoiding direct access to the `$order` object witnin `src` (except for this class).
 */
class OrderService {
	/**
	 * Legacy order service.
	 *
	 * TEMPORARY: The legacy order service should be migrated here
	 * once `WC_Order` parameters have been converted to order IDS.

	 * @todo: Add a GH issue link here.
	 * @var WC_Payments_Order_Service
	 */
	private $legacy_service;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_Order_Service $legacy_service The legacy order service.
	 * @param LegacyProxy               $legacy_proxy   Proxy for accessing non-src functionality.
	 */
	public function __construct(
		WC_Payments_Order_Service $legacy_service,
		LegacyProxy $legacy_proxy
	) {
		$this->legacy_service = $legacy_service;
		$this->legacy_proxy   = $legacy_proxy;
	}

	/**
	 * Retrieves the order object.
	 *
	 * Unlike the legacy service, this one only accepts integer IDs,
	 * and returns only the `WC_Order` object, no refunds.
	 *
	 * @param int $order_id ID of the order.
	 * @return WC_Order Order object.
	 * @throws Order_Not_Found_Exception If the order could not be found.
	 */
	public function get_order( int $order_id ): WC_Order {
		$order = $this->legacy_proxy->call_function( 'wc_get_order', $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new Order_Not_Found_Exception(
				__( 'The requested order was not found.', 'woocommerce-payments' ),
				'order_not_found'
			);
		}
		return $order;
	}

	/**
	 * Set the payment metadata for payment method id.
	 *
	 * @param int    $order_id          ID of the order.
	 * @param string $payment_method_id The value to be set.
	 *
	 * @throws Order_Not_Found_Exception
	 */
	public function set_payment_method_id( int $order_id, string $payment_method_id ) {
		$this->legacy_service->set_payment_method_id_for_order( $order_id, $payment_method_id );
	}

	/**
	 * Generates payment metadata from order details.
	 *
	 * @param int          $order_id     ID of the order.
	 * @param Payment_Type $payment_type Type of the payment (recurring or not).
	 * @return array                     The metadat athat will be sent to the server.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_payment_metadata( int $order_id, Payment_Type $payment_type = null ) {
		$order = $this->get_order( $order_id );

		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'        => $name,
			'customer_email'       => $email,
			'site_url'             => esc_url( get_site_url() ),
			'order_id'             => $order->get_id(),
			'order_number'         => $order->get_order_number(),
			'order_key'            => $order->get_order_key(),
			'payment_type'         => $payment_type,
			'checkout_type'        => $order->get_created_via(),
			'client_version'       => WCPAY_VERSION_NUMBER,
			'subscription_payment' => 'no',
		];

		if (
			'recurring' === (string) $payment_type
			&& $this->legacy_proxy->function_exists( 'wcs_order_contains_subscription' )
			&& $this->legacy_proxy->call_function( 'wcs_order_contains_subscription', $order, 'any' )
		) {
			$use_stripe_billing = $this->legacy_proxy->call_static( WC_Payments_Features::class, 'should_use_stripe_billing' );
			$is_renewal         = $this->legacy_proxy->call_function( 'wcs_order_contains_renewal', $order );

			$metadata['subscription_payment'] = $is_renewal ? 'renewal' : 'initial';
			$metadata['payment_context']      = $use_stripe_billing ? 'wcpay_subscription' : 'regular_subscription';
		}

		return apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment_type );
	}
}
