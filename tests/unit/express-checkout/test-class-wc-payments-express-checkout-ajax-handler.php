<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Ajax_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Country_Code;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Session_Rate_Limiter;
use WCPay\WooPay\WooPay_Utilities;

/**
 * WC_Payments_Express_Checkout_Ajax_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Ajax_Handler_Test extends WCPAY_UnitTestCase {
	/**
	 * The test subject.
	 *
	 * @var WC_Payments_Express_Checkout_Ajax_Handler
	 */
	private $ajax_handler;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$account_mock = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$express_checkout_button_helper_mock = new WC_Payments_Express_Checkout_Button_Helper(
			$gateway_mock,
			$account_mock
		);

		$this->ajax_handler = new WC_Payments_Express_Checkout_Ajax_Handler(
			$express_checkout_button_helper_mock
		);

		$this->ajax_handler->init();
	}

	public function test_tokenized_cart_address_avoid_normalization_when_missing_header() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', null );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => 'US',
				'state'   => 'California',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );

		$this->assertSame( 'California', $shipping_address['state'] );
	}

	public function test_tokenized_cart_address_avoid_normalization_when_wrong_nonce() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', 'invalid-nonce' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => 'US',
				'state'   => 'California',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );

		$this->assertSame( 'California', $shipping_address['state'] );
	}

	public function test_tokenized_cart_address_state_normalization() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => 'US',
				'state'   => 'California',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country' => 'CA',
				'state'   => 'Colombie-Britannique',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'CA', $shipping_address['state'] );
		$this->assertSame( 'BC', $billing_address['state'] );
	}

	public function test_tokenized_cart_address_postcode_normalization() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'CA',
				'postcode' => 'H3B',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'US',
				'postcode' => '90210',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		// this should be modified.
		$this->assertSame( 'H3B000', $shipping_address['postcode'] );
		// this shouldn't be modified.
		$this->assertSame( '90210', $billing_address['postcode'] );
	}

	public function test_tokenized_cart_avoid_address_postcode_normalization_if_route_incorrect() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/checkout' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'CA',
				'postcode' => 'H3B',
				'state'    => 'Colombie-Britannique',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'CA',
				'postcode' => 'H3B',
				'state'    => 'Colombie-Britannique',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		// this should be modified.
		$this->assertSame( 'BC', $shipping_address['state'] );
		$this->assertSame( 'BC', $billing_address['state'] );
		// this shouldn't be modified.
		$this->assertSame( 'H3B', $shipping_address['postcode'] );
		$this->assertSame( 'H3B', $billing_address['postcode'] );
	}

	/**
	 * When Hong Kong has an invalid state, it should remain unchanged.
	 */
	public function test_tokenized_cart_hk_invalid_state() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => Country_Code::HONG_KONG,
				'state'   => 'invalid-state',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( Country_Code::HONG_KONG, $shipping_address['country'] );
		$this->assertEquals( 'invalid-state', $shipping_address['state'] );
	}

	/**
	 * When Hong Kong regions/districts are delivered in the postcode field due to an Apple Pay bug, they should be adjusted.
	 */
	public function test_tokenized_cart_hk_postcode_with_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => 'kowloon',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( Country_Code::HONG_KONG, $shipping_address['country'] );
		$this->assertEquals( 'KOWLOON', $shipping_address['state'] );
	}

	/**
	 * When the `九龍` Hong Kong region is delivered in the postcode field, it should be adjusted for WooCommerce to be able to handle it.
	 */
	public function test_tokenized_cart_hk_postcode_with_九龍_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => '九龍',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( Country_Code::HONG_KONG, $shipping_address['country'] );
		$this->assertEquals( 'KOWLOON', $shipping_address['state'] );
	}

	public function test_tokenized_cart_italy_state_venezia_normalization() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => 'IT',
				'state'   => 'Venezia',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country' => 'IT',
				'state'   => 'Milano',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'VE', $shipping_address['state'] );
		$this->assertSame( 'MI', $billing_address['state'] );
	}

	public function test_tokenized_cart_italy_already_normalized_state() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => 'IT',
				'state'   => 'VE',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country' => 'IT',
				'state'   => 'MI',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'VE', $shipping_address['state'] );
		$this->assertSame( 'MI', $billing_address['state'] );
	}
}
