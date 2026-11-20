<?php
/**
 * Agent purchase quote service.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service\AgentPurchases;

use RuntimeException;
use Throwable;
use WC_Order;
use WC_Customer;
use WC_Validation;
use WC_Cart_Totals;

/** Build authoritative quotes for products enabled for agent purchases. */
class QuoteService {
	/**
	 * Calculate a quote without modifying the storefront session.
	 *
	 * @param int   $product_id Product identifier.
	 * @param int   $quantity Quantity to purchase.
	 * @param array $address Shipping address.
	 * @return array Quote and fingerprint.
	 */
	public function calculate( int $product_id, int $quantity, array $address ): array {
		return $this->with_cart( $product_id, $quantity, $address, static fn( array $quote ): array => $quote );
	}

	/**
	 * Requote immediately before creating an unpaid order.
	 *
	 * @param array $quote Approved quote.
	 * @param int   $shopper_id Shopper identifier.
	 * @return WC_Order Unpaid order.
	 * @throws RuntimeException When the quote has changed.
	 * @throws Throwable When order creation fails.
	 */
	public function make_order( array $quote, int $shopper_id ): WC_Order {
		return $this->with_cart(
			(int) $quote['product_id'],
			(int) $quote['quantity'],
			$quote['shipping_address'],
			static function ( array $fresh ) use ( $quote, $shopper_id ): WC_Order {
				if ( ! hash_equals( $fresh['fingerprint'], (string) ( $quote['fingerprint'] ?? '' ) ) ) {
					throw new RuntimeException( __( 'The order has changed. Request a new quote and approval.', 'woocommerce-payments' ) );
				}
				$order = new WC_Order();
				try {
					$order->set_created_via( 'wcpay-agent-purchase' );
					$order->set_customer_id( $shopper_id );
					$order->set_currency( $fresh['currency'] );
					$order->set_prices_include_tax( wc_prices_include_tax() );
					$order->set_address( $fresh['shipping_address'], 'shipping' );
					$order->set_address( $fresh['shipping_address'], 'billing' );
					$shopper = get_userdata( $shopper_id );
					if ( $shopper ) {
						$order->set_billing_email( $shopper->user_email );
					}
					WC()->checkout()->set_data_from_cart( $order );
					$order->update_meta_data( '_wcpay_agent_purchase', 'yes' );
					$order->save();
					return $order;
				} catch ( Throwable $error ) {
					if ( $order->get_id() ) {
						$order->delete( true );
					}
					throw $error;
				}
			}
		);
	}

	/**
	 * Calculate with guest state, restoring storefront state on all paths.
	 *
	 * @param int      $product_id Product identifier.
	 * @param int      $quantity Quantity to purchase.
	 * @param array    $address Shipping address.
	 * @param callable $callback Consumer of the calculated quote.
	 * @return mixed Callback result.
	 * @throws RuntimeException When product, address or totals are unsupported.
	 */
	private function with_cart( int $product_id, int $quantity, array $address, callable $callback ) {
		$original      = [ WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages ];
		$session_class = static fn() => InMemorySession::class;
		add_filter( 'woocommerce_session_handler', $session_class );
		try {
			/**
			 * Prepare scoped integrations before calculating an agent quote.
			 *
			 * @since 11.2.0
			 */
			do_action( 'wcpay_agent_purchase_before_quote' );
			WC()->session = new InMemorySession();
			$product      = wc_get_product( $product_id );
			if ( ! $product || ! in_array( $product_id, array_map( 'absint', (array) get_option( 'wcpay_agent_purchase_product_ids', [] ) ), true ) || 'publish' !== $product->get_status() || ! $product->is_type( 'simple' ) || $product->is_virtual() || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->has_enough_stock( $quantity ) || $product->backorders_allowed() || $quantity < 1 || $quantity > 5 || ( $product->is_sold_individually() && $quantity > 1 ) ) {
				throw new RuntimeException( __( 'Choose an available agent purchase product and a quantity from 1 to 5.', 'woocommerce-payments' ) );
			}
			$clean = [];
			foreach ( [ 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ] as $field ) {
				if ( isset( $address[ $field ] ) && ! is_string( $address[ $field ] ) ) {
					throw new RuntimeException( __( 'Invalid shipping address.', 'woocommerce-payments' ) );
				}
				$clean[ $field ] = sanitize_text_field( $address[ $field ] ?? '' );
				if ( 'address_2' !== $field && '' === $clean[ $field ] ) {
					throw new RuntimeException( __( 'Complete the shipping address before requesting a quote.', 'woocommerce-payments' ) );
				}
			}
			if ( 'US' !== $clean['country'] || ! isset( WC()->countries->get_states( 'US' )[ $clean['state'] ] ) || ! WC_Validation::is_postcode( $clean['postcode'], 'US' ) ) {
				throw new RuntimeException( __( 'Agent purchases require a valid US shipping address.', 'woocommerce-payments' ) );
			}
			WC()->customer = new WC_Customer( 0 );
			foreach ( $clean as $field => $value ) {
				WC()->customer->{ 'set_shipping_' . $field }( $value );
				WC()->customer->{ 'set_billing_' . $field }( $value );
			}
			WC()->customer->set_calculated_shipping( true );
			WC()->cart = new IsolatedCart();
			$key       = WC()->cart->generate_cart_id( $product_id );
			WC()->cart->set_cart_contents(
				[
					$key => [
						'key'          => $key,
						'product_id'   => $product_id,
						'variation_id' => 0,
						'variation'    => [],
						'quantity'     => $quantity,
						'data'         => $product,
						'data_hash'    => wc_get_cart_item_data_hash( $product ),
					],
				]
			);
			// Run WooCommerce's totals engine without storefront session callbacks.
			new WC_Cart_Totals( WC()->cart );
			$packages = WC()->shipping()->get_packages();
			$chosen   = WC()->session->get( 'chosen_shipping_methods', [] );
			if ( 1 !== count( $packages ) || ! isset( $packages[0]['rates'][ $chosen[0] ?? '' ] ) ) {
				throw new RuntimeException( __( 'No shipping method is available for this address.', 'woocommerce-payments' ) );
			}
			if ( WC()->cart->get_fees() || (float) WC()->cart->get_discount_total() > 0 ) {
				throw new RuntimeException( __( 'Agent purchases do not support additional fees or discounts.', 'woocommerce-payments' ) );
			}
			$rate                 = $packages[0]['rates'][ $chosen[0] ];
			$item                 = WC()->cart->get_cart()[ $key ];
			$quote                = [
				'product_id'       => $product_id,
				'quantity'         => $quantity,
				'currency'         => get_woocommerce_currency(),
				'product_name'     => $product->get_name(),
				'unit_price'       => wc_format_decimal( (float) $item['line_subtotal'] / $quantity, wc_get_price_decimals() ),
				'subtotal'         => wc_format_decimal( $item['line_subtotal'], wc_get_price_decimals() ),
				'shipping_total'   => wc_format_decimal( WC()->cart->get_shipping_total(), wc_get_price_decimals() ),
				'tax_total'        => wc_format_decimal( WC()->cart->get_total_tax(), wc_get_price_decimals() ),
				'total'            => wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() ),
				'shipping_method'  => [
					'id'    => $rate->get_id(),
					'label' => $rate->get_label(),
					'cost'  => $rate->get_cost(),
					'taxes' => $rate->get_taxes(),
				],
				'item_taxes'       => $item['line_tax_data'],
				'shipping_address' => $clean,
			];
			$quote['fingerprint'] = hash( 'sha256', wp_json_encode( $quote ) );
			return $callback( $quote );
		} finally {
			remove_filter( 'woocommerce_session_handler', $session_class );
			list( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages ) = $original;
			/**
			 * Restore scoped integrations after calculation, including failures.
			 *
			 * @since 11.2.0
			 */
			do_action( 'wcpay_agent_purchase_after_quote' );
		}
	}
}
