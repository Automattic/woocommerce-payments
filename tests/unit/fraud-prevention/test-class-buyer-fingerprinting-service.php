<?php
/**
 * Class Buyer_Fingerprinting_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Country_Code;
use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * Buyer_Fingerprinting_Service_Test unit tests.
 */
class Buyer_Fingerprinting_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * Fraud_Prevention_Service mock object.
	 *
	 * @var Fraud_Prevention_Service
	 */
	private $fraud_prevention_service_mock;

	/**
	 * The service under test.
	 *
	 * @var Buyer_Fingerprinting_Service
	 */
	private $buyer_fingerprinting_service;

	public function set_up() {
		parent::set_up();

		$this->fraud_prevention_service_mock = $this->createMock( Fraud_Prevention_Service::class );

		Buyer_Fingerprinting_Service::set_instance( null );
		$this->buyer_fingerprinting_service = Buyer_Fingerprinting_Service::get_instance( $this->fraud_prevention_service_mock );
	}

	public function test_it_hashes_using_sha512() {
		$result                 = $this->buyer_fingerprinting_service->hash_data_for_fraud_prevention( 'test_string' );
		$expected_hashed_string = hash( 'sha512', 'test_string' );

		$this->assertSame( $result, $expected_hashed_string );
	}

	public function test_it_hashes_order_info() {
		$fingerprint = 'abc123';
		$ip_country  = Country_Code::UNITED_KINGDOM;
		add_filter(
			'woocommerce_geolocate_ip',
			function () use ( $ip_country ) {
				return $ip_country;
			}
		);

		$order_hashes          = $this->buyer_fingerprinting_service->get_hashed_data_for_customer( $fingerprint );
		$expected_hashed_array = [
			'fraud_prevention_data_shopper_ip_hash' => hash( 'sha512', '127.0.0.1', false ),
			'fraud_prevention_data_shopper_ua_hash' => $fingerprint,
			'fraud_prevention_data_ip_country'      => $ip_country,
		];

		$this->assertSame( $order_hashes, $expected_hashed_array );

		remove_all_filters( 'woocommerce_geolocate_ip' );
	}

	/**
	 * @dataProvider order_context_provider
	 */
	public function test_request_order_identifiers_do_not_supply_item_count( $context, $cart_quantity ) {
		global $wp;
		$original_cart       = WC()->cart;
		$original_query_vars = $wp->query_vars;
		$original_post       = $_POST;
		$order               = WC_Helper_Order::create_order( 0 );
		try {
			$cart = $this->createMock( WC_Cart::class );
			$cart->method( 'get_cart_contents_count' )->willReturn( $cart_quantity );
			WC()->cart = $cart;
			unset( $wp->query_vars['order-pay'], $_POST['wcpay_order_id'] );
			if ( 'query' === $context ) {
				$wp->query_vars['order-pay'] = $order->get_id();
			} else {
				$_POST['wcpay_order_id'] = $order->get_id();
			}

			$data = $this->buyer_fingerprinting_service->get_hashed_data_for_customer( 'test-fingerprint' );
			$this->assertArrayNotHasKey( 'fraud_prevention_data_cart_contents', $data );
		} finally {
			WC()->cart      = $original_cart;
			$wp->query_vars = $original_query_vars;
			$_POST          = $original_post;
			$order->delete( true );
		}
	}

	public function order_context_provider() {
		return [
			'query ID with nonempty cart' => [ 'query', 1 ],
			'query ID with empty cart'    => [ 'query', 0 ],
			'POST ID with nonempty cart'  => [ 'post', 1 ],
			'POST ID with empty cart'     => [ 'post', 0 ],
		];
	}

	public function test_item_count_is_omitted_without_order_context() {
		global $wp;
		$original_cart       = WC()->cart;
		$original_query_vars = $wp->query_vars;
		$original_post       = $_POST;
		try {
			unset( $wp->query_vars['order-pay'], $_POST['wcpay_order_id'] );
			$cart = $this->createMock( WC_Cart::class );
			$cart->expects( $this->never() )->method( 'get_cart_contents_count' );
			WC()->cart = $cart;

			$data = $this->buyer_fingerprinting_service->get_hashed_data_for_customer( 'test-fingerprint' );
			$this->assertArrayNotHasKey( 'fraud_prevention_data_cart_contents', $data );
		} finally {
			WC()->cart      = $original_cart;
			$wp->query_vars = $original_query_vars;
			$_POST          = $original_post;
		}
	}

	public function test_explicit_order_quantity_does_not_read_cart() {
		$original_cart = WC()->cart;
		$order         = WC_Helper_Order::create_order( 0 );
		try {
			$cart = $this->createMock( WC_Cart::class );
			$cart->expects( $this->never() )->method( 'get_cart_contents_count' );
			WC()->cart = $cart;

			$data = $this->buyer_fingerprinting_service->get_hashed_data_for_customer( 'test-fingerprint', $order );
			$this->assertSame( 4, $data['fraud_prevention_data_cart_contents'] );
		} finally {
			WC()->cart = $original_cart;
			$order->delete( true );
		}
	}
}
