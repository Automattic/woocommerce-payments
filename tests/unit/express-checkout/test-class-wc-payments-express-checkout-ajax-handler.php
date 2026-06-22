<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Ajax_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Country_Code;

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

	public function test_tokenized_cart_gb_address_outward_code_2_postcode_normalization() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'GB',
				'postcode' => 'B1',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'GB',
				'postcode' => 'B2',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'B1000', $shipping_address['postcode'] );
		$this->assertSame( 'B2000', $billing_address['postcode'] );
	}

	public function test_tokenized_cart_gb_address_outward_code_3_postcode_normalization() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'GB',
				'postcode' => 'B10',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'GB',
				'postcode' => 'B24',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'B10000', $shipping_address['postcode'] );
		$this->assertSame( 'B24000', $billing_address['postcode'] );
	}

	public function test_tokenized_cart_gb_address_outward_code_4_postcode_normalization() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'GB',
				'postcode' => 'BB10',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'GB',
				'postcode' => 'GU52',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'BB10000', $shipping_address['postcode'] );
		$this->assertSame( 'GU52000', $billing_address['postcode'] );
	}

	public function test_tokenized_cart_gb_address_unknown_outward_code_postcode_normalization() {
		$request = new WP_REST_Request();
		$request->set_route( '/wc/store/v1/cart/update-customer' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => 'GB',
				'postcode' => 'Z Z Z Y-',
			]
		);
		$request->set_param(
			'billing_address',
			[
				'country'  => 'GB',
				'postcode' => 'ZQ QZZZZZZZA',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$shipping_address = $request->get_param( 'shipping_address' );
		$billing_address  = $request->get_param( 'billing_address' );

		$this->assertSame( 'ZZZY000', $shipping_address['postcode'] );
		$this->assertSame( 'ZQQZZZZ', $billing_address['postcode'] );
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

	/**
	 * Apple Pay may deliver the bare island name "Hong Kong" as the region instead of the
	 * WooCommerce region name "Hong Kong Island". It must still normalize to the WC `HONG KONG`
	 * state key, on the shipping address.
	 */
	public function test_tokenized_cart_hk_shipping_state_hong_kong_alias() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country' => Country_Code::HONG_KONG,
				'state'   => 'Hong Kong',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( 'HONG KONG', $shipping_address['state'] );
	}

	/**
	 * The "Hong Kong" alias must also normalize on the billing address (the reported failure).
	 */
	public function test_tokenized_cart_hk_billing_state_hong_kong_alias() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country' => Country_Code::HONG_KONG,
				'state'   => 'Hong Kong',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'HONG KONG', $billing_address['state'] );
	}

	/**
	 * Multi-word HK regions delivered in the postcode field (the JS shipping path) must normalize
	 * to the WC state key. Regression coverage for the previous `.replace( ' ', '' )` space strip.
	 */
	public function test_tokenized_cart_hk_shipping_postcode_with_new_territories_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => 'New Territories',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( 'NEW TERRITORIES', $shipping_address['state'] );
	}

	/**
	 * The Chinese (中文) multi-word region `新界` (New Territories) in the postcode field must normalize.
	 */
	public function test_tokenized_cart_hk_shipping_postcode_with_新界_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => '新界',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( 'NEW TERRITORIES', $shipping_address['state'] );
	}

	/**
	 * The same multi-word region recovery must work on the billing address.
	 */
	public function test_tokenized_cart_hk_billing_postcode_with_new_territories_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => 'New Territories',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'NEW TERRITORIES', $billing_address['state'] );
	}

	/**
	 * Multi-word "Hong Kong Island" delivered in the postcode field must normalize to `HONG KONG`.
	 */
	public function test_tokenized_cart_hk_shipping_postcode_with_hong_kong_island_region() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => 'invalid-state',
				'postcode' => 'Hong Kong Island',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( 'HONG KONG', $shipping_address['state'] );
	}

	/**
	 * Apple Pay can drop the Hong Kong region entirely, leaving `state` and `postcode` empty and
	 * only the district in the `city` field. The region must be derived from the district.
	 * This is the real-world failure reported in WOOPMNT-6188 (e.g. `1 Tai Po Road, Tai Po`).
	 */
	public function test_tokenized_cart_hk_shipping_region_derived_from_city_district() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'shipping_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => '',
				'city'     => 'Tai Po',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$shipping_address = $request->get_param( 'shipping_address' );
		$this->assertEquals( 'NEW TERRITORIES', $shipping_address['state'] );
	}

	/**
	 * The district-from-city derivation must also work for the billing address (the reported error).
	 */
	public function test_tokenized_cart_hk_billing_region_derived_from_city_district() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => '',
				'city'     => 'Tai Po',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'NEW TERRITORIES', $billing_address['state'] );
	}

	/**
	 * A Kowloon district in the city field must derive the `KOWLOON` region.
	 */
	public function test_tokenized_cart_hk_region_derived_from_kowloon_district() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => '',
				'city'     => 'Kowloon City',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'KOWLOON', $billing_address['state'] );
	}

	/**
	 * A Hong Kong Island district in the city field must derive the `HONG KONG` region.
	 */
	public function test_tokenized_cart_hk_region_derived_from_hk_island_district() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => '',
				'city'     => 'Central',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'HONG KONG', $billing_address['state'] );
	}

	/**
	 * The district-from-city derivation must work for Chinese (中文) district names too (`大埔` = Tai Po).
	 */
	public function test_tokenized_cart_hk_region_derived_from_中文_district() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => '',
				'city'     => '大埔',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'NEW TERRITORIES', $billing_address['state'] );
	}

	/**
	 * Some clients drop the space from "Hong Kong"; it must still resolve to the `HONG KONG` region.
	 */
	public function test_tokenized_cart_hk_region_derived_from_spaceless_hong_kong() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'  => Country_Code::HONG_KONG,
				'state'    => '',
				'postcode' => 'HongKong',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );
		$billing_address = $request->get_param( 'billing_address' );
		$this->assertEquals( 'HONG KONG', $billing_address['state'] );
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

	public function test_tokenized_cart_address_lines_shifted_when_address_1_empty() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'   => 'DE',
				'address_1' => '',
				'address_2' => 'Meininger Strasse 58',
			]
		);
		$request->set_param(
			'shipping_address',
			[
				'country'   => 'DE',
				'address_1' => '   ',
				'address_2' => 'Meininger Strasse 58',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$billing_address  = $request->get_param( 'billing_address' );
		$shipping_address = $request->get_param( 'shipping_address' );

		$this->assertSame( 'Meininger Strasse 58', $billing_address['address_1'] );
		$this->assertSame( '', $billing_address['address_2'] );
		$this->assertSame( 'Meininger Strasse 58', $shipping_address['address_1'] );
		$this->assertSame( '', $shipping_address['address_2'] );
	}

	public function test_tokenized_cart_address_lines_preserved_when_address_1_populated() {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_param(
			'billing_address',
			[
				'country'   => 'DE',
				'address_1' => 'Meininger Strasse 58',
				'address_2' => 'Apt 4B',
			]
		);

		$this->ajax_handler->tokenized_cart_store_api_address_normalization( null, null, $request );

		$billing_address = $request->get_param( 'billing_address' );

		$this->assertSame( 'Meininger Strasse 58', $billing_address['address_1'] );
		$this->assertSame( 'Apt 4B', $billing_address['address_2'] );
	}
}
