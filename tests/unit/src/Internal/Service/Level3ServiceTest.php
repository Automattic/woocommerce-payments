<?php
/**
 * Class Level3ServiceTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Service;

use ReflectionClass;
use PHPUnit\Framework\MockObject\MockObject;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPAY_UnitTestCase;
use WC_Payments_Account;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Level3 data service unit tests.
 */
class Level3ServiceTest extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var Level3Service
	 */
	private $sut;

	/**
	 * @var OrderService|MockObject
	 */
	private $mock_order_service;

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * @var LegacyProxy|MockObject
	 */
	private $legacy_proxy;

	/**
	 * Order ID used for mocks.
	 *
	 * @var int
	 */
	private $order_id = 123;

	/**
	 * Set up the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_order_service = $this->createMock( OrderService::class );
		$this->mock_account       = $this->createMock( WC_Payments_Account::class );
		$this->legacy_proxy       = $this->createMock( LegacyProxy::class );

		// Main service under test: Level3Service.
		$this->sut = new Level3Service(
			$this->mock_order_service,
			$this->mock_account,
			$this->legacy_proxy
		);
	}

	protected function create_mock_item( $name, $quantity, $subtotal, $total_tax, $product_id, $variable = false ) {
		// Setup the item.
		$mock_item = $this
			->getMockBuilder( WC_Order_Item_Product::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_name',
					'get_quantity',
					'get_subtotal',
					'get_total_tax',
					'get_total',
					'get_variation_id',
					'get_product_id',
				]
			)
			->getMock();

		$mock_item
			->method( 'get_name' )
			->will( $this->returnValue( $name ) );

		$mock_item
			->method( 'get_quantity' )
			->will( $this->returnValue( $quantity ) );

		$mock_item
			->method( 'get_total' )
			->will( $this->returnValue( $subtotal ) );

		$mock_item
			->method( 'get_subtotal' )
			->will( $this->returnValue( $subtotal ) );

		$mock_item
			->method( 'get_total_tax' )
			->will( $this->returnValue( $total_tax ) );

		$mock_item
			->method( 'get_variation_id' )
			->will( $this->returnValue( $variable ? 789 : false ) );

		$mock_item
			->method( 'get_product_id' )
			->will( $this->returnValue( $product_id ) );

		return $mock_item;
	}

	protected function mock_level_3_order(
		$shipping_postcode,
		$with_fee = false,
		$with_negative_price_product = false,
		$quantity = 1,
		$basket_size = 1,
		$product_id = 30,
		$variable = false
	) {
		$mock_items   = [];
		$mock_items[] = $this->create_mock_item( 'Beanie with Logo', $quantity, 18, 2.7, $product_id, $variable );

		if ( $with_fee ) {
			// Setup the fee.
			$mock_fee = $this
				->getMockBuilder( WC_Order_Item_Fee::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'get_name', 'get_quantity', 'get_total_tax', 'get_total' ] )
				->getMock();

			$mock_fee
				->method( 'get_name' )
				->will( $this->returnValue( 'fee' ) );

			$mock_fee
				->method( 'get_quantity' )
				->will( $this->returnValue( 1 ) );

			$mock_fee
				->method( 'get_total' )
				->will( $this->returnValue( 10 ) );

			$mock_fee
				->method( 'get_total_tax' )
				->will( $this->returnValue( 1.5 ) );

			$mock_items[] = $mock_fee;
		}

		if ( $with_negative_price_product ) {
			$mock_items[] = $this->create_mock_item( 'Negative Product Price', $quantity, -18.99, 2.7, 42 );
			$mock_items[] = $this->create_mock_item( 'Negative Product Price+Tax', $quantity, -18.99, -2.7, 42 );
		}

		if ( $basket_size > 1 ) {
			// Keep the formely created item/fee and add duplicated items to the basket.
			$mock_items = array_merge( $mock_items, array_fill( 0, $basket_size - count( $mock_items ), $mock_items[0] ) );
		}

		$this->mock_order( $mock_items, $shipping_postcode );
	}

	/**
	 * @param array      $mock_items        Order items.
	 * @param string     $shipping_postcode Shipping postcode.
	 * @param float|null $total             Order total. Defaults to what WooCommerce would charge for the items and shipping.
	 */
	protected function mock_order( array $mock_items, string $shipping_postcode, ?float $total = null ) {
		if ( null === $total ) {
			$total = 30 + 8;
			foreach ( $mock_items as $mock_item ) {
				$total += $mock_item->get_total() + $mock_item->get_total_tax();
			}
		}

		// Setup the order.
		$mock_order = $this
			->getMockBuilder( WC_Order::class )
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'get_id',
					'get_items',
					'get_currency',
					'get_total',
					'get_shipping_total',
					'get_shipping_tax',
					'get_shipping_postcode',
				]
			)
			->getMock();

		$mock_order
			->method( 'get_total' )
			->willReturn( (string) $total );

		$mock_order
			->method( 'get_id' )
			->will( $this->returnValue( 210 ) );

		$mock_order
			->method( 'get_items' )
			->will( $this->returnValue( $mock_items ) );

		$mock_order
			->method( 'get_currency' )
			->will( $this->returnValue( Currency_Code::UNITED_STATES_DOLLAR ) );

		$mock_order
			->method( 'get_shipping_total' )
			->will( $this->returnValue( 30 ) );

		$mock_order
			->method( 'get_shipping_tax' )
			->will( $this->returnValue( 8 ) );

		$mock_order
			->method( 'get_shipping_postcode' )
			->will( $this->returnValue( $shipping_postcode ) );

		$this->mock_order_service->expects( $this->once() )
			->method( '_deprecated_get_order' )
			->with( $this->order_id )
			->willReturn( $mock_order );
	}

	public function test_full_level3_data() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 789,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1.0,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, false, 1, 1, 30, true );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_product_id_longer_than_12_characters() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 123456789123,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, false, 1, 1, 123456789123456 );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_fee() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
				(object) [
					'product_code'        => 'fee',
					'product_description' => 'fee',
					'unit_cost'           => 1000,
					'quantity'            => 1,
					'tax_amount'          => 150,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', true );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_negative_price_product() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
				(object) [
					'product_code'        => 42,
					'product_description' => 'Negative Product Price',
					'unit_cost'           => 0,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 1899,
				],
				(object) [
					'product_code'        => 42,
					'product_description' => 'Negative Product Price+Tax',
					'unit_cost'           => 0,
					'quantity'            => 1,
					'tax_amount'          => 0,
					'discount_amount'     => 1899 + 270,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, true, 1, 1 );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_us_store_level_3_data() {
		// Use a non-us customer postcode to ensure it's not included in the level3 data.
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '9000' );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertArrayNotHasKey( 'shipping_address_zip', $level_3_data );
	}

	public function test_us_customer_level_3_data() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
		];

		// Use a non-US postcode.
		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '9000' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012' );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_non_us_customer_level_3_data() {
		$expected_data = [];

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::CANADA );
		$this->mock_level_3_order( 'K0A' );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_full_level3_data_with_float_quantity() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 450,
					'quantity'            => 4,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, false, 3.7 );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_rounding_in_edge_cases() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		$mock_items   = [];
		$mock_items[] = $this->create_mock_item( 'Beanie with Addon', 3, 73, 0, 30 );
		$this->mock_order( $mock_items, '98012' );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertCount( 2, $level_3_data['line_items'] );
		$this->assertEquals( 2433, $level_3_data['line_items'][0]->unit_cost );
		$this->assertEquals( 'rounding-fix', $level_3_data['line_items'][1]->product_code );
		$this->assertEquals( 'Rounding fix', $level_3_data['line_items'][1]->product_description );
		$this->assertEquals( 1, $level_3_data['line_items'][1]->unit_cost );
		$this->assertEquals( 1, $level_3_data['line_items'][1]->quantity );
		$this->assertEquals( 0, $level_3_data['line_items'][1]->tax_amount );
		$this->assertEquals( 0, $level_3_data['line_items'][1]->discount_amount );
	}

	public function test_full_level3_data_with_float_quantity_zero() {
		$expected_data = [
			'merchant_reference'   => '210',
			'customer_reference'   => '210',
			'shipping_amount'      => 3800,
			'line_items'           => [
				(object) [
					'product_code'        => 30,
					'product_description' => 'Beanie with Logo',
					'unit_cost'           => 1800,
					'quantity'            => 1,
					'tax_amount'          => 270,
					'discount_amount'     => 0,
				],
			],
			'shipping_address_zip' => '98012',
			'shipping_from_zip'    => '94110',
		];

		$this->legacy_proxy->expects( $this->once() )
			->method( 'call_function' )
			->with( 'get_option', 'woocommerce_store_postcode' )
			->willReturn( '94110' );

		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, false, 0.4 );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertEquals( $expected_data, $level_3_data );
	}

	public function test_level3_data_bundle() {
		$items = (array) [
			(object) [
				'product_code'        => 'abcd',
				'product_description' => 'product description',
				'unit_cost'           => 1000,
				'quantity'            => 4,
				'tax_amount'          => 200,
				'discount_amount'     => 500,
			],
			(object) [
				'product_code'        => 'abcd',
				'product_description' => 'product description',
				'unit_cost'           => 5000,
				'quantity'            => 3,
				'tax_amount'          => 1000,
				'discount_amount'     => 200,
			],
		];

		// Use reflection to test the otherwise method.
		$reflection = new ReflectionClass( Level3Service::class );
		$method     = $reflection->getMethod( 'bundle_level3_data_from_items' );
		$method->setAccessible( true );
		$bundle_data = $method->invoke( $this->sut, $items );

		$this->assertSame( $bundle_data->product_description, '2 more items' );

		// total_unit_cost = sum( unit_cost * quantity ).
		$this->assertSame( $bundle_data->unit_cost, 19000 );

		// quantity of the bundle = 1.
		$this->assertSame( $bundle_data->quantity, 1 );

		// total_tax_amount = sum( tax_amount ).
		$this->assertSame( $bundle_data->tax_amount, 1200 );

		// total_discount_amount = sum( discount_amount ).
		$this->assertSame( $bundle_data->discount_amount, 700 );
	}

	public function test_level3_data_bundle_for_orders_with_more_than_200_items() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', true, false, 1, 500 );
		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( count( $level_3_data['line_items'] ), 200 );

		$bundled_data = end( $level_3_data['line_items'] );

		$this->assertSame( $bundled_data->product_description, '301 more items' );
		$this->assertLessThanOrEqual( 12, strlen( $bundled_data->product_code ) );
	}

	public function test_unit_cost_rounded_up_is_balanced_with_a_discount() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		// 59.83 / 2 rounds to 29.92, a cent over.
		$mock_items   = [];
		$mock_items[] = $this->create_mock_item( 'Beanie', 2, 59.83, 0, 30 );
		$this->mock_order( $mock_items, '98012' );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertCount( 2, $level_3_data['line_items'] );
		$this->assertEquals(
			(object) [
				'product_code'        => 'rounding-fix',
				'product_description' => 'Rounding fix',
				'unit_cost'           => 0,
				'quantity'            => 1,
				'tax_amount'          => 0,
				'discount_amount'     => 1,
			],
			$level_3_data['line_items'][1]
		);
	}

	public function test_lines_rounded_separately_are_balanced_against_the_order_total() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		// "Round tax at subtotal level": WooCommerce rounds 129.655 once, to 129.66.
		$mock_items = [];
		foreach ( [ 16.491, 31.991, 25.191, 26.391, 29.591 ] as $index => $price ) {
			$mock_items[] = $this->create_mock_item( 'Product ' . $index, 1, $price, 0, 30 + $index );
		}
		$this->mock_order( $mock_items, '98012', 129.66 + 38 );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertCount( 6, $level_3_data['line_items'] );
		$this->assertSame( 'rounding-fix', $level_3_data['line_items'][5]->product_code );
		$this->assertSame( 1, $level_3_data['line_items'][5]->unit_cost );
		$this->assertSame( 0, $level_3_data['line_items'][5]->discount_amount );
	}

	public function test_no_level3_data_when_the_order_total_differs_from_its_items() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		// A 30% deposit.
		$mock_items   = [];
		$mock_items[] = $this->create_mock_item( 'Catering', 1, 600, 0, 30 );
		$this->mock_order( $mock_items, '98012', 180 );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( [], $level_3_data );
	}

	public function test_no_level3_data_when_an_order_without_items_charges_more_than_shipping() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_order( [], '98012', 5 + 38 );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( [], $level_3_data );
	}

	public function test_level3_data_for_an_order_with_only_shipping() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_order( [], '98012' );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( 3800, $level_3_data['shipping_amount'] );
		$this->assertSame( [], $level_3_data['line_items'] );
	}

	public function test_filtered_level3_data_that_balances_is_kept() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		// A gift card paid 10.00 outside the line items.
		$mock_items   = [];
		$mock_items[] = $this->create_mock_item( 'Beanie', 1, 18, 2.7, 30 );
		$this->mock_order( $mock_items, '98012', 18 + 2.7 + 38 - 10 );

		$add_gift_card = function ( $level3_data ) {
			$level3_data['line_items'][] = (object) [
				'product_code'        => 'gift-card',
				'product_description' => 'Gift card',
				'unit_cost'           => 0,
				'quantity'            => 1,
				'tax_amount'          => 0,
				'discount_amount'     => 1000,
			];
			return $level3_data;
		};
		add_filter( 'wcpay_payment_request_level3_data', $add_gift_card );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		remove_filter( 'wcpay_payment_request_level3_data', $add_gift_card );

		$this->assertCount( 2, $level_3_data['line_items'] );
		$this->assertSame( 'gift-card', $level_3_data['line_items'][1]->product_code );
	}

	public function test_no_level3_data_when_a_filter_returns_values_stripe_rejects() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012' );

		$break_description = function ( $level3_data ) {
			$level3_data['line_items'][0]->product_description = '';
			return $level3_data;
		};
		add_filter( 'wcpay_payment_request_level3_data', $break_description );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		remove_filter( 'wcpay_payment_request_level3_data', $break_description );

		$this->assertSame( [], $level_3_data );
	}

	public function test_long_filtered_descriptions_are_truncated() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012' );

		$lengthen_description = function ( $level3_data ) {
			$level3_data['line_items'][0]->product_description = 'Écharpe en laine mérinos extra douce';
			return $level3_data;
		};
		add_filter( 'wcpay_payment_request_level3_data', $lengthen_description );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		remove_filter( 'wcpay_payment_request_level3_data', $lengthen_description );

		$this->assertSame( 'Écharpe en laine mérinos e', $level_3_data['line_items'][0]->product_description );
		$this->assertSame( 1800, $level_3_data['line_items'][0]->unit_cost );
	}

	public function test_negative_price_product_is_discounted_for_the_whole_line() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );
		$this->mock_level_3_order( '98012', false, true, 2 );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( 0, $level_3_data['line_items'][1]->unit_cost );
		$this->assertSame( 1899, $level_3_data['line_items'][1]->discount_amount );
		$this->assertCount( 3, $level_3_data['line_items'] );
	}

	public function test_items_without_a_name_get_a_description_and_product_code() {
		$this->mock_account->method( 'get_account_country' )->willReturn( Country_Code::UNITED_STATES );

		$mock_fee = $this
			->getMockBuilder( WC_Order_Item_Fee::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_name', 'get_quantity', 'get_total_tax', 'get_total' ] )
			->getMock();
		$mock_fee->method( 'get_name' )->willReturn( '' );
		$mock_fee->method( 'get_quantity' )->willReturn( 1 );
		$mock_fee->method( 'get_total' )->willReturn( 10 );
		$mock_fee->method( 'get_total_tax' )->willReturn( 0 );

		$this->mock_order( [ $mock_fee ], '98012' );

		$level_3_data = $this->sut->get_data_from_order( $this->order_id );

		$this->assertSame( 'fee', $level_3_data['line_items'][0]->product_code );
		$this->assertSame( 'Item', $level_3_data['line_items'][0]->product_description );
	}
}
