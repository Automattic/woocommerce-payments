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

	const PRODUCT_HASH_KEY = '_wcpay_product_hash';
	const PRODUCT_ID_KEY   = '_wcpay_product_id';
	const PRICE_ID_KEY     = '_wcpay_product_price_id';

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

	/**
	 * Test create product.
	 */
	public function test_create_product() {
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn(
				[
					'stripe_product_id' => 'prod_test123',
					'stripe_price_id'   => 'price_test123',
				]
			);

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->create_product( $this->mock_product );
		$this->assertEquals( 'prod_test123', $this->mock_product->get_meta( self::PRODUCT_ID_KEY, true ) );
		$this->assertEquals( 'price_test123', $this->mock_product->get_meta( self::PRICE_ID_KEY, true ) );
	}

	/**
	 * Test update product.
	 */
	public function test_update_product() {
		$this->mock_product->update_meta_data( self::PRODUCT_ID_KEY, 'prod_test123' );
		$this->mock_product->save();

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				'prod_test123',
				$this->get_mock_product_data()
			)
			->willReturn(
				[
					'stripe_product_id' => 'prod_test123',
					'stripe_price_id'   => 'price_test123',
				]
			);

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->update_product( $this->mock_product );
	}

	/**
	 * Test archive product.
	 *
	 * Note: This also tests unarchive_product
	 */
	public function test_archive_product() {
		$this->mock_product->update_meta_data( self::PRODUCT_ID_KEY, 'prod_test123' );
		$this->mock_product->update_meta_data( self::PRICE_ID_KEY, 'price_test123' );
		$this->mock_product->save();

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_price' )
			->with(
				'price_test123',
				[ 'active' => 'false' ]
			)
			->willReturn(
				[
					'stripe_price_id' => 'price_test123',
					'object'          => 'price',
				]
			);

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				'prod_test123',
				[ 'active' => 'false' ]
			)
			->willReturn(
				[
					'stripe_product_id' => 'prod_test123',
					'object'            => 'product',
				]
			);

		$this->product_service->archive_product( $this->mock_product );
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
					'stripe_price_id' => 'price_test123',
					'object'          => 'price',
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
				'currency'       => 'USD',
				'description'    => 'Test product description',
				'name'           => 'Test product',
				'interval'       => 'month',
				'interval_count' => 3,
				'unit_amount'    => 10000,
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
}
