<?php
/**
 * Class WC_Payments_Product_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Product_Service unit tests.
 */
class WC_Payments_Product_Service_Test extends WP_UnitTestCase {

	const LIVE_PRODUCT_ID_KEY = '_wcpay_product_id_live';
	const TEST_PRODUCT_ID_KEY = '_wcpay_product_id_test';
	const LIVE_PRICE_ID_KEY   = '_wcpay_product_price_id_live';
	const TEST_PRICE_ID_KEY   = '_wcpay_product_price_id_test';
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Product_Service
	 */
	private $product_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_product    = $this->get_mock_product();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->product_service = new WC_Payments_Product_Service( $this->mock_api_client );
	}

	public function tearDown() {
		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );
	}

	/**
	 * Test create product.
	 */
	public function test_create_product() {
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn( [ 'wcpay_product_id' => 'prod_test123' ] );

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->create_product( $this->mock_product );
		$this->assertEquals( 'prod_test123', $this->mock_product->get_meta( self::LIVE_PRODUCT_ID_KEY, true ) );
	}

	/**
	 * Test update product.
	 */
	public function test_update_products_live_only() {
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, 'prod_test123' );
		$this->mock_product->save();

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				'prod_test123',
				$this->get_mock_product_data( [ 'test_mode' => false ] )
			)
			->willReturn(
				[
					'wcpay_product_id' => 'prod_test123',
					'wcpay_price_id'   => 'price_test123',
				]
			);

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->update_products( $this->mock_product );
	}

	/**
	 * Test update product.
	 */
	public function test_update_products_live_and_test() {
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, 'prod_test123_live' );
		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY, 'prod_test123_test' );
		$this->mock_product->save();

		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'update_product' )
			->withConsecutive(
				[
					'prod_test123_live',
					$this->get_mock_product_data( [ 'test_mode' => false ] ),
				],
				[
					'prod_test123_test',
					$this->get_mock_product_data( [ 'test_mode' => true ] ),
				]
			)
			->willReturn(
				[
					'wcpay_product_id' => 'dummy',
					'wcpay_price_id'   => 'dummy',
				]
			);

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->update_products( $this->mock_product );
	}

	/**
	 * Test archive product.
	 *
	 * Note: This also tests unarchive_product
	 */
	public function test_archive_product() {
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, 'prod_test123' );
		$this->mock_product->update_meta_data( self::LIVE_PRICE_ID_KEY, 'price_test123' );
		$this->mock_product->update_meta_data( self::TEST_PRICE_ID_KEY, 'price_test456' );
		$this->mock_product->save();

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				'prod_test123',
				[
					'active'    => 'false',
					'test_mode' => false,
				]
			)
			->willReturn(
				[
					'wcpay_product_id' => 'prod_test123',
					'object'           => 'product',
				]
			);

		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'update_price' )
			->withConsecutive(
				[
					'price_test456',
					[
						'active'    => 'false',
						'test_mode' => true,
					],
				],
				[
					'price_test123',
					[
						'active'    => 'false',
						'test_mode' => false,
					],
				]
			);

		$this->product_service->archive_product( $this->mock_product );

		// Confirm that the product price IDs have been deleted.
		$this->assertFalse( $this->mock_product->meta_exists( self::LIVE_PRICE_ID_KEY ) );
		$this->assertFalse( $this->mock_product->meta_exists( self::LIVE_PRICE_ID_KEY ) );
	}

	/**
	 * Test archive product price.
	 *
	 * Note: This also tests unarchive_price
	 */
	public function test_archive_price() {
		$this->mock_api_client->expects( $this->once() )
			->method( 'update_price' )
			->with(
				'price_test123',
				[ 'active' => 'false' ]
			)
			->willReturn(
				[
					'wcpay_price_id' => 'price_test123',
					'object'         => 'price',
				]
			);

		$this->product_service->archive_price( 'price_test123' );
	}

	/**
	 * Get a mock product.
	 */
	private function get_mock_product() {
		$product = new WC_Product();

		$product->set_name( 'Test product' );
		$product->set_description( 'Test product description' );
		$product->set_price( 100 );
		$product->save();

		return $product;
	}

	/**
	 * Get mock product data.
	 *
	 * @param array $overrides Product data to include.
	 */
	private function get_mock_product_data( $overrides = [] ) {
		return array_merge(
			[
				'description' => 'Test product description',
				'name'        => 'Test product',
			],
			$overrides
		);
	}

	/**
	 * Mock get_period static method.
	 *
	 * @param string $period Subscription period.
	 */
	private function mock_get_period( $period ) {
		WC_Subscriptions_Product::set_period( $period );
	}

	/**
	 * Mock get_interval static method.
	 *
	 * @param int $interval Subscription interval.
	 */
	private function mock_get_interval( $interval ) {
		WC_Subscriptions_Product::set_interval( $interval );
	}

	/**
	 * Tests for WC_Payments_Product_Service::get_wcpay_product_id()
	 */
	public function test_get_wcpay_product_id() {
		WC_Subscriptions_Product::$is_subscription = true;

		// Make sure the WC_Payments_Subscriptions::get_product_service() returns our mock product service object.
		$ref = new ReflectionProperty( 'WC_Payments_Subscriptions', 'product_service' );
		$ref->setAccessible( true );
		$ref->setValue( null, $this->product_service );

		$mock_product_id = 'prod_123_wcpay_test_product_id';
		$this->mock_product->update_meta_data( WC_Payments_Product_Service::LIVE_PRODUCT_ID_KEY, $mock_product_id );

		$this->assertSame( $mock_product_id, $this->product_service->get_wcpay_product_id( $this->mock_product ) );

		// Test that deleting the price will cause the product to be created.
		$this->mock_product->delete_meta_data( WC_Payments_Product_Service::LIVE_PRODUCT_ID_KEY );
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn(
				[
					'wcpay_product_id' => $mock_product_id,
					'wcpay_price_id'   => 'price_test123',
				]
			);

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );

		$this->assertSame( $mock_product_id, $this->product_service->get_wcpay_product_id( $this->mock_product ) );
	}

	/**
	 * Tests for WC_Payments_Product_Service::get_wcpay_product_id_option()
	 */
	public function test_get_wcpay_product_id_option() {
		$this->assertSame( '_wcpay_product_id_live', WC_Payments_Product_Service::get_wcpay_product_id_option() );

		// set to testmode.
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		$this->assertSame( '_wcpay_product_id_test', WC_Payments_Product_Service::get_wcpay_product_id_option() );
	}

	/**
	 * Tests for WC_Payments_Product_Service::get_wcpay_price_id_option()
	 */
	public function test_get_wcpay_price_id_option() {
		$this->assertSame( '_wcpay_product_price_id_live', WC_Payments_Product_Service::get_wcpay_price_id_option() );

		// set to testmode.
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );
		$this->assertSame( '_wcpay_product_price_id_test', WC_Payments_Product_Service::get_wcpay_price_id_option() );
	}
}
