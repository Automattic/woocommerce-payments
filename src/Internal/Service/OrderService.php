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
use WCPay\Internal\Proxy\HooksProxy;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for managing orders.
 *
 * This service's public methods should only require order IDs rather than objects,
 * avoiding direct access to the `$order` object witnin `src` (except for this class).
 */
class OrderService {
	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Hooks proxy.
	 *
	 * @var HooksProxy
	 */
	private $hooks_proxy;

	/**
	 * Class constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Proxy for accessing non-src functionality.
	 * @param HooksProxy  $hooks_proxy  Proxy for triggering hooks.
	 */
	public function __construct(
		LegacyProxy $legacy_proxy,
		HooksProxy $hooks_proxy
	) {
		$this->legacy_proxy = $legacy_proxy;
		$this->hooks_proxy  = $hooks_proxy;
	}

	/**
	 * Retrieves the order object.
	 *
	 * Please restrain from using this method!
	 * It can only be used to (temporarily) provide the order object
	 * to legacy (`includes`) services, which are not adapted to work
	 * with order IDs yet.
	 *
	 * @see https://github.com/Automattic/woocommerce-payments/issues/7367
	 * @param int $order_id ID of the order.
	 * @return WC_Order Order object.
	 * @throws Order_Not_Found_Exception If the order could not be found.
	 */
	public function _deprecated_get_order( int $order_id ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return $this->get_order( $order_id );
	}

	/**
	 * Generates payment metadata from order details.
	 *
	 * @param int          $order_id     ID of the order.
	 * @param Payment_Type $payment_type Type of the payment (recurring or not).
	 * @return array                     The metadat athat will be sent to the server.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_payment_metadata( int $order_id, ?Payment_Type $payment_type = null ) {
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
			&& $this->legacy_proxy->call_function( 'function_exists', 'wcs_order_contains_subscription' )
			&& $this->legacy_proxy->call_function( 'wcs_order_contains_subscription', $order, 'any' )
		) {
			$use_stripe_billing = $this->legacy_proxy->call_static( WC_Payments_Features::class, 'should_use_stripe_billing' );
			$is_renewal         = $this->legacy_proxy->call_function( 'wcs_order_contains_renewal', $order );

			$metadata['subscription_payment'] = $is_renewal ? 'renewal' : 'initial';
			$metadata['payment_context']      = $use_stripe_billing ? 'wcpay_subscription' : 'regular_subscription';
		}

		return $this->hooks_proxy->apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment_type );
	}


	/**
	 * Sets the '_wcpay_mode' meta data on an order.
	 *
	 * @param string $order_id The order id.
	 * @param string $mode  Mode from the context.
	 * @throws Order_Not_Found_Exception
	 */
	public function set_mode( string $order_id, string $mode ): void {
		$order = $this->get_order( $order_id );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, $mode );
		$order->save_meta_data();
	}

	/**
	 * Gets the '_wcpay_mode' meta data on an order.
	 *
	 * @param string $order_id The order id.
	 *
	 * @return string The mode.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_mode( string $order_id ): string {
		$order = $this->get_order( $order_id );
		return $order->get_meta( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, true );
	}

	/**
	 * Gets currently attached intent ID of the order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string|null Intent ID for the order. Null if no intent ID attached to order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_intent_id( int $order_id ): ?string {
		$order     = $this->get_order( $order_id );
		$intent_id = (string) $order->get_meta( '_intent_id', true );
		if ( empty( $intent_id ) ) {
			return null;
		}
		return $intent_id;
	}

	/**
	 * Gets cart hash for the given order ID.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return string Cart hash for the order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_cart_hash( int $order_id ): string {
		$order = $this->get_order( $order_id );
		return $order->get_cart_hash();
	}

	/**
	 * Gets customer ID for the given order ID.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return int Customer ID for the order.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_customer_id( int $order_id ): int {
		return $this->get_order( $order_id )->get_customer_id();
	}

	/**
	 * Checks if the order has one of paid statuses.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return bool True if the order has one of paid statuses, false otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function is_paid( int $order_id ): bool {
		return $this->get_order( $order_id )
			->has_status(
				$this->legacy_proxy->call_function( 'wc_get_is_paid_statuses' )
			);
	}

	/**
	 * Checks if the order has one of pending statuses.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return bool True if the order has one of pending statuses, false otherwise.
	 * @throws Order_Not_Found_Exception
	 */
	public function is_pending( int $order_id ) {
		return $this->get_order( $order_id )
			->has_status(
				$this->legacy_proxy->call_function( 'wc_get_is_pending_statuses' )
			);
	}

	/**
	 * Validate phone number provided in the order.
	 *
	 * @param  int $order_id ID of the order.
	 *
	 * @return bool
	 * @throws Order_Not_Found_Exception
	 */
	public function is_valid_phone_number( int $order_id ): bool {
		$order = $this->get_order( $order_id );
		return strlen( $order->get_billing_phone() ) < 20;
	}

	/**
	 * Adds note to order.
	 *
	 * @param int    $order_id  ID of the order.
	 * @param string $note      Note content.
	 *
	 * @return int Note ID.
	 * @throws Order_Not_Found_Exception
	 */
	public function add_note( int $order_id, string $note ): int {
		return $this->get_order( $order_id )->add_order_note( $note );
	}

	/**
	 * Deletes order.
	 *
	 * @param int  $order_id     ID of the order.
	 * @param bool $force_delete Should the order be deleted permanently.
	 *
	 * @return bool Result of the deletion.
	 * @throws Order_Not_Found_Exception
	 */
	public function delete( int $order_id, bool $force_delete = false ): bool {
		return $this->get_order( $order_id )->delete( $force_delete );
	}

	/**
	 * Retrieves the order object.
	 *
	 * This method should be only used internally within this service.
	 * Other `src` methods and services should not access and manipulate
	 * order data directly, utilizing this service instead.
	 *
	 * Unlike the legacy service, this one only accepts integer IDs,
	 * and returns only the `WC_Order` object, no refunds.
	 *
	 * @param int $order_id ID of the order.
	 * @return WC_Order Order object.
	 * @throws Order_Not_Found_Exception If the order could not be found.
	 */
	protected function get_order( int $order_id ): WC_Order {
		$order = $this->legacy_proxy->call_function( 'wc_get_order', $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new Order_Not_Found_Exception(
				esc_html(
					sprintf(
					// Translators: %d is the ID of an order.
						__( 'The requested order (ID %d) was not found.', 'woocommerce-payments' ),
						$order_id
					)
				),
				'order_not_found'
			);
		}
		return $order;
	}
}
