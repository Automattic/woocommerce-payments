<?php
/**
 * These tests make assertions against class WC_Payments_Payment_Request_Button_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Payment_Request_Button_Handler_Test class.
 */
class WC_Payments_Payment_Request_Button_Handler_Test extends WP_UnitTestCase {
	const SHIPPING_ADDRESS = [
		'country'   => 'US',
		'state'     => 'CA',
		'postcode'  => '94110',
		'city'      => 'San Francisco',
		'address_1' => '60 29th Street',
		'address_2' => '#343',
	];

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Payment request instance.
	 *
	 * @var WC_Payments_Payment_Request_Button_Handler
	 */
	private $pr;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Test product to add to the cart
	 * @var WC_Product_Simple
	 */
	private $simple_product;

	/**
	 * Test shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	private $zone;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $flat_rate_id;

	/**
	 * Flat rate shipping method instance id
	 *
	 * @var int
	 */
	private $local_pickup_id;

	/**
	 * Used to get the settings.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_wcpay_gateway;

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
									->disableOriginalConstructor()
									->setMethods(
										[
											'get_account_data',
											'is_server_connected',
											'capture_intention',
											'cancel_intention',
											'get_intent',
											'create_and_confirm_setup_intent',
											'get_setup_intent',
											'get_payment_method',
											'refund_charge',
										]
									)
									->getMock();
		$this->mock_api_client->expects( $this->any() )->method( 'is_server_connected' )->willReturn( true );
		$this->mock_wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();

		$this->pr = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway );

		$this->simple_product = WC_Helper_Product::create_simple_product();

		WC_Helper_Shipping::delete_simple_flat_rate();
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$this->flat_rate_id = $zone->add_shipping_method( 'flat_rate' );
		self::set_shipping_method_cost( $this->flat_rate_id, '5' );

		$this->local_pickup_id = $zone->add_shipping_method( 'local_pickup' );
		self::set_shipping_method_cost( $this->local_pickup_id, '1' );

		$this->zone = $zone;

		WC()->session->init();
		WC()->cart->add_to_cart( $this->simple_product->get_id(), 1 );
		$this->pr->update_shipping_method( [ self::get_shipping_option_rate_id( $this->flat_rate_id ) ] );
		WC()->cart->calculate_totals();
	}

	public function tearDown() {
		parent::tearDown();
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();
		$this->zone->delete();
		delete_option( 'woocommerce_woocommerce_payments_settings' );
	}

	/**
	 * @return WC_Payment_Gateway_WCPay
	 */
	private function make_wcpay_gateway() {
		$mock_customer_service         = $this->createMock( WC_Payments_Customer_Service::class );
		$mock_token_service            = $this->createMock( WC_Payments_Token_Service::class );
		$mock_action_scheduler_service = $this->createMock( WC_Payments_Action_Scheduler_Service::class );
		$mock_rate_limiter             = $this->createMock( Session_Rate_Limiter::class );
		$mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );

		return new WC_Payment_Gateway_WCPay(
			$this->mock_api_client,
			$this->mock_wcpay_account,
			$mock_customer_service,
			$mock_token_service,
			$mock_action_scheduler_service,
			$mock_rate_limiter,
			$mock_order_service
		);
	}

	/**
	 * Sets shipping method cost
	 *
	 * @param string $instance_id Shipping method instance id
	 * @param string $cost Shipping method cost in USD
	 */
	private static function set_shipping_method_cost( $instance_id, $cost ) {
		$method          = WC_Shipping_Zones::get_shipping_method( $instance_id );
		$option_key      = $method->get_instance_option_key();
		$options         = get_option( $option_key );
		$options['cost'] = $cost;
		update_option( $option_key, $options );
	}

	/**
	 * Composes shipping option object by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return array Shipping option.
	 */
	private static function get_shipping_option( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		return [
			'id'     => $method->get_rate_id(),
			'label'  => $method->title,
			'detail' => '',
			'amount' => WC_Payments_Utils::prepare_amount( $method->get_instance_option( 'cost' ) ),
		];
	}

	/**
	 * Retrieves rate id by shipping method instance id.
	 *
	 * @param string $instance_id Shipping method instance id.
	 *
	 * @return string Shipping option instance rate id.
	 */
	private static function get_shipping_option_rate_id( $instance_id ) {
		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		return $method->get_rate_id();
	}


	public function test_get_shipping_options_returns_shipping_options() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->flat_rate_id, $this->local_pickup_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_get_shipping_options_returns_chosen_option() {
		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$flat_rate              = $this->get_shipping_option( $this->flat_rate_id );
		$expected_display_items = [
			[
				'label'  => 'Shipping',
				'amount' => $flat_rate['amount'],
			],
		];

		$this->assertEquals( 1500, $data['total']['amount'], 'Total amount mismatch' );
		$this->assertEquals( $expected_display_items, $data['displayItems'], 'Display items mismatch' );
	}

	public function test_get_shipping_options_keeps_chosen_option() {
		$method_id = self::get_shipping_option_rate_id( $this->local_pickup_id );
		$this->pr->update_shipping_method( [ $method_id ] );

		$data = $this->pr->get_shipping_options( self::SHIPPING_ADDRESS );

		$expected_shipping_options = array_map(
			'self::get_shipping_option',
			[ $this->local_pickup_id, $this->flat_rate_id ]
		);

		$this->assertEquals( 'success', $data['result'] );
		$this->assertEquals( $expected_shipping_options, $data['shipping_options'], 'Shipping options mismatch' );
	}

	public function test_get_button_settings() {
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();
		$this->pr                 = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway );

		$this->assertEquals(
			[
				'type'         => 'buy',
				'theme'        => 'dark',
				'height'       => '40',
				'locale'       => 'en',
				'branded_type' => 'long',
			],
			$this->pr->get_button_settings()
		);
	}

	public function test_multiple_packages_in_cart_not_allowed() {
		// Add fake packages to the cart.
		add_filter(
			'woocommerce_cart_shipping_packages',
			function() {
				return [
					'fake_package_1',
					'fake_package_2',
				];
			}
		);
		$this->mock_wcpay_gateway = $this->make_wcpay_gateway();
		$this->pr                 = new WC_Payments_Payment_Request_Button_Handler( $this->mock_wcpay_account, $this->mock_wcpay_gateway );

		$this->assertFalse( $this->pr->has_allowed_items_in_cart() );
	}
}
