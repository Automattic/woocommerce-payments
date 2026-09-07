<?php
/**
 * Isolated, local-demo-only WooCommerce cart quotes and real order construction.
 */

defined( 'ABSPATH' ) || exit;

/** A cart that never loads or persists a shopper session. */
class WCPay_Agent_Demo_Cart extends WC_Cart {
	public function __construct() {
		$this->fees_api = new WC_Cart_Fees();
	}

	public function get_cart() {
		return array_filter( $this->get_cart_contents() );
	}
}

/** An in-memory session with no persistence hooks. */
class WCPay_Agent_Demo_Session extends WC_Session {}

/** Build authoritative quotes for explicitly marked demo products. */
class WCPay_Agent_Demo_Quote {
	public static function calculate( int $product_id, int $quantity, array $address ): array {
		return self::with_cart( $product_id, $quantity, $address, static fn( array $quote ): array => $quote );
	}

	/** Requote immediately before creating an unpaid order. */
	public static function make_order( array $quote, int $shopper_id ): WC_Order {
		return self::with_cart(
			(int) $quote['product_id'],
			(int) $quote['quantity'],
			$quote['shipping_address'],
			static function ( array $fresh ) use ( $quote, $shopper_id ): WC_Order {
				if ( ! hash_equals( $fresh['fingerprint'], (string) ( $quote['fingerprint'] ?? '' ) ) ) {
					throw new RuntimeException( 'The order has changed. Request a new quote and approval.' );
				}
				$order = new WC_Order();
				try {
					$order->set_created_via( 'wcpay-agent-demo' );
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
					$order->update_meta_data( '_wcpay_agent_demo', 'yes' );
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

	/** Calculate with guest state, restoring the real storefront state on all paths. */
	private static function with_cart( int $product_id, int $quantity, array $address, callable $callback ) {
		$original = array( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages );
		$enable_taxes = static fn() => 'yes';
		$session_class = static fn() => WCPay_Agent_Demo_Session::class;
		add_filter( 'pre_option_woocommerce_calc_taxes', $enable_taxes );
		add_filter( 'woocommerce_session_handler', $session_class );
		try {
			WC()->session = new WCPay_Agent_Demo_Session();
			$product = wc_get_product( $product_id );
			if ( ! $product || 'yes' !== $product->get_meta( '_wcpay_agent_demo_product' ) || 'publish' !== $product->get_status() || ! $product->is_type( 'simple' ) || $product->is_virtual() || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->has_enough_stock( $quantity ) || $product->backorders_allowed() || $quantity < 1 || $quantity > 5 || ( $product->is_sold_individually() && $quantity > 1 ) ) {
				throw new RuntimeException( 'Choose an available demo product and a quantity from 1 to 5.' );
			}
			$clean = array();
			foreach ( array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ) as $field ) {
				if ( isset( $address[ $field ] ) && ! is_string( $address[ $field ] ) ) {
					throw new RuntimeException( 'Invalid shipping address.' );
				}
				$clean[ $field ] = sanitize_text_field( $address[ $field ] ?? '' );
				if ( 'address_2' !== $field && '' === $clean[ $field ] ) {
					throw new RuntimeException( 'Complete the shipping address before requesting a quote.' );
				}
			}
			if ( 'US' !== $clean['country'] || ! isset( WC()->countries->get_states( 'US' )[ $clean['state'] ] ) || ! WC_Validation::is_postcode( $clean['postcode'], 'US' ) ) {
				throw new RuntimeException( 'This local demo requires a valid US shipping address.' );
			}
			WC()->customer = new WC_Customer( 0 );
			foreach ( $clean as $field => $value ) {
				WC()->customer->{ 'set_shipping_' . $field }( $value );
				WC()->customer->{ 'set_billing_' . $field }( $value );
			}
			WC()->customer->set_calculated_shipping( true );
			WC()->cart = new WCPay_Agent_Demo_Cart();
			$key = WC()->cart->generate_cart_id( $product_id );
			WC()->cart->set_cart_contents( array( $key => array( 'key' => $key, 'product_id' => $product_id, 'variation_id' => 0, 'variation' => array(), 'quantity' => $quantity, 'data' => $product, 'data_hash' => wc_get_cart_item_data_hash( $product ) ) ) );
			// Run WooCommerce's totals engine without storefront session callbacks.
			new WC_Cart_Totals( WC()->cart );
			$packages = WC()->shipping()->get_packages();
			$chosen = WC()->session->get( 'chosen_shipping_methods', array() );
			if ( 1 !== count( $packages ) || ! isset( $packages[0]['rates'][ $chosen[0] ?? '' ] ) ) {
				throw new RuntimeException( 'No shipping method is available for this demo address.' );
			}
			if ( WC()->cart->get_fees() || (float) WC()->cart->get_discount_total() > 0 ) {
				throw new RuntimeException( 'This demo does not support additional fees or discounts.' );
			}
			$rate = $packages[0]['rates'][ $chosen[0] ];
			$item = WC()->cart->get_cart()[ $key ];
			$quote = array(
				'product_id' => $product_id,
				'quantity' => $quantity,
				'currency' => get_woocommerce_currency(),
				'product_name' => $product->get_name(),
				'unit_price' => wc_format_decimal( (float) $item['line_subtotal'] / $quantity, wc_get_price_decimals() ),
				'subtotal' => wc_format_decimal( $item['line_subtotal'], wc_get_price_decimals() ),
				'shipping_total' => wc_format_decimal( WC()->cart->get_shipping_total(), wc_get_price_decimals() ),
				'tax_total' => wc_format_decimal( WC()->cart->get_total_tax(), wc_get_price_decimals() ),
				'total' => wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() ),
				'shipping_method' => array( 'id' => $rate->get_id(), 'label' => $rate->get_label(), 'cost' => $rate->get_cost(), 'taxes' => $rate->get_taxes() ),
				'item_taxes' => $item['line_tax_data'],
				'shipping_address' => $clean,
			);
			$quote['fingerprint'] = hash( 'sha256', wp_json_encode( $quote ) );
			return $callback( $quote );
		} finally {
			remove_filter( 'pre_option_woocommerce_calc_taxes', $enable_taxes );
			remove_filter( 'woocommerce_session_handler', $session_class );
			list( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->packages ) = $original;
		}
	}
}
