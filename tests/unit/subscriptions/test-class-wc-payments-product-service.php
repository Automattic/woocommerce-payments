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

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->product_service = new WC_Payments_Product_Service( $this->mock_api_client );
	}

	/**
	 * Test create product.
	 *
	 * @throws API_Exception
	 */
	public function test_create_product() {
		$mock_product      = $this->get_mock_product();
		$mock_product_data = $this->get_mock_product_data();

		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->with( $mock_product_data )
			->willReturn(
				[
					'stripe_product_id' => 'prod_test123',
					'stripe_price_id'   => 'price_test123',
				]
			);

		$this->product_service->create_product( $mock_product );
		$this->assertEquals( 'prod_test123', $mock_product->get_meta( self::PRODUCT_ID_KEY, true ) );
		$this->assertEquals( 'price_test123', $mock_product->get_meta( self::PRICE_ID_KEY, true ) );
	}

	/**
	 * Test update product.
	 *
	 * @throws API_Exception
	 */
	public function test_update_product() {
		$user = new WP_User( 0 );

		$mock_product      = $this->get_mock_product();
		$mock_product_data = $this->get_mock_product_data();

		$mock_product->update_meta_data( self::PRODUCT_ID_KEY, 'prod_test123' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				'prod_test123',
				$mock_product_data
			)
			->willReturn(
				[
					'stripe_product_id' => 'prod_test123',
					'stripe_price_id'   => 'price_test123',
				]
			);

		$this->product_service->update_product( $mock_product );
		$this->assertEquals( 'price_test123', $mock_product->get_meta( self::PRICE_ID_KEY, true ) );
	}

	private function get_mock_product() {
		$product = new WC_Product();

		$product->set_name( 'Test product' );
		$product->set_description( 'Test product description' );
		$product->set_price( 100 );
		$product->update_meta_data( 'subscription_period', 'month' );
		$product->update_meta_data( 'subscription_period_interval', 3 );
		$product->save();

		return $product;
	}

	private function get_mock_product_data( $overrides = [] ) {
		return array_merge(
			[
				'currency'       => 'usd',
				'description'    => 'Test product description',
				'name'           => 'Test product',
				'interval'       => 'month',
				'interval_count' => 3,
				'unit_amount'    => 10000,
			],
			$overrides
		);
	}
}
