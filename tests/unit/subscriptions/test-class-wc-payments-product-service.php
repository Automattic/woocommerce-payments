<?php
/**
 * Class WC_Payments_Product_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Product_Service unit tests.
 */
class WC_Payments_Product_Service_Test extends WCPAY_UnitTestCase {

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
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account_service;

	/**
	 * Mock product.
	 *
	 * @var WC_Product|MockObject
	 */
	private $mock_product;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_product         = $this->get_mock_product();
		$this->mock_api_client      = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_account_service = $this->createMock( WC_Payments_Account::class );
		$this->product_service      = new WC_Payments_Product_Service( $this->mock_api_client, $this->mock_account_service );

		WC_Payments::mode()->live();
	}

	public function tear_down() {
		WC_Payments::mode()->live();
	}

	/**
	 * Test create product.
	 */
	public function test_create_product() {
		$account_id = 'acct_test123';
		$product_id = 'prod_test123';
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn( [ 'wcpay_product_id' => $product_id ] );

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_get_period( 'month' );
		$this->mock_get_interval( 3 );
		$this->product_service->create_product( $this->mock_product );

		$this->assertSame( $product_id, $this->mock_product->get_meta( self::LIVE_PRODUCT_ID_KEY ) );
		$this->assertSame( $account_id, $this->mock_product->get_meta( self::LIVE_PRODUCT_ID_KEY . '_linked_to' ) );
	}

	/**
	 * Test update product.
	 */
	public function test_update_products_live_only() {
		$mock_product_id = 'prod_1234';
		$account_id      = 'acct_test123';

		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, $mock_product_id );
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_product' )
			->with(
				$mock_product_id,
				$this->get_mock_product_data( [ 'test_mode' => false ] )
			)
			->willReturn(
				[
					'wcpay_product_id' => $mock_product_id,
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
		$mock_prod_product_id = 'prod_1234';
		$mock_test_product_id = 'prod_5678';
		$account_id           = 'acct_test123';

		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, $mock_prod_product_id );
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY, $mock_test_product_id );
		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->exactly( 4 ) )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_api_client->expects( $this->exactly( 2 ) )
			->method( 'update_product' )
			->withConsecutive(
				[
					$mock_prod_product_id,
					$this->get_mock_product_data( [ 'test_mode' => false ] ),
				],
				[
					$mock_test_product_id,
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
		$account_id = 'acct_1234';
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, 'prod_test123' );
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->update_meta_data( self::LIVE_PRICE_ID_KEY, 'price_test123' );
		$this->mock_product->update_meta_data( self::TEST_PRICE_ID_KEY, 'price_test456' );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->exactly( 2 ) )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

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

	public function test_get_or_create_wcpay_product_id_for_test() {
		$mock_product_id = 'prod_123';
		$account_id      = 'acct_test123';

		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY, $mock_product_id );
		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_product' );

		$result = $this->product_service->get_or_create_wcpay_product_id( $this->mock_product, true );

		$this->assertEquals( $mock_product_id, $result );
	}

	public function test_get_or_create_wcpay_product_id_for_live() {
		$mock_product_id = 'prod_123';
		$account_id      = 'acct_test123';

		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY, $mock_product_id );
		$this->mock_product->update_meta_data( self::LIVE_PRODUCT_ID_KEY . '_linked_to', $account_id );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_product' );

		$result = $this->product_service->get_or_create_wcpay_product_id( $this->mock_product, false );

		$this->assertEquals( $mock_product_id, $result );
	}
	public function test_get_or_create_wcpay_product_id_will_create_product_if_not_exist() {
		$new_product_id = 'prod_test123';
		$account_id     = 'acct_test123';

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn( [ 'wcpay_product_id' => $new_product_id ] );

		$result = $this->product_service->get_or_create_wcpay_product_id( $this->mock_product );

		$this->assertEquals( $new_product_id, $result );
	}

	public function test_create_new_product_for_different_account() {
		$mock_product_id = 'prod_123';
		$new_product_id  = 'prod_456';

		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY, $mock_product_id );
		$this->mock_product->update_meta_data( self::TEST_PRODUCT_ID_KEY . '_linked_to', 'acct_test123' );
		$this->mock_product->save();

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( 'acct_test456' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_product' )
			->with( $this->get_mock_product_data() )
			->willReturn( [ 'wcpay_product_id' => $new_product_id ] );

		$result = $this->product_service->get_or_create_wcpay_product_id( $this->mock_product );

		$this->assertEquals( $new_product_id, $result );
	}
	/**
	 * Tests for WC_Payments_Product_Service::get_wcpay_product_id_option()
	 */
	public function test_get_wcpay_product_id_option() {
		$this->assertSame( '_wcpay_product_id_live', WC_Payments_Product_Service::get_wcpay_product_id_option() );

		// set to testmode.
		WC_Payments::mode()->test();
		$this->assertSame( '_wcpay_product_id_test', WC_Payments_Product_Service::get_wcpay_product_id_option() );
	}

	/**
	 * Tests for WC_Payments_Product_Service::get_wcpay_product_id_for_item()
	 */
	public function test_get_wcpay_product_id_for_item() {
		$product_id = 'product_id_test123';
		$account_id = 'acct_test123';
		$this->mock_api_client->expects( $this->once() )
			->method( 'create_product' )
			->willReturn(
				[
					'wcpay_product_id' => $product_id,
					'wcpay_price_id'   => 'price_test123',
				]
			);

		$this->mock_account_service
			->expects( $this->once() )
			->method( 'get_stripe_account_id' )
			->willReturn( $account_id );

		// If type is 'Test Tax *&^ name', the result should be _wcpay_product_id_live_test_tax__name.
		$test_type = 'Test Tax *&^ name';
		$this->product_service->get_wcpay_product_id_for_item( $test_type );

		$this->assertFalse( get_option( '_wcpay_product_id_live_Test Tax *&^ name' ) );
		$this->assertSame( $product_id, get_option( '_wcpay_product_id_live_test_tax__name' ) );
	}
}
