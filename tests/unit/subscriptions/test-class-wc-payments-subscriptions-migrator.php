<?php
/**
 * Class WC_Payments_Subscriptions_Migrator Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Subscriptions_Migrator unit tests.
 */
class WC_Payments_Subscriptions_Migrator_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
	}

	public function test_payments_subscriptions_migrate_simple() {
		$mock_subscription          = new WC_Subscription();
		$mock_wcpay_subscription_id = 'wcpay_subscription_id_12345';

		$mock_subscription->update_meta_data( '_wcpay_subscription_id', $mock_wcpay_subscription_id );
		$mock_subscription->save();

		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		$this->mock_api_client->expects( $this->once() )
			->method( 'get_subscription' )
			->with( $mock_wcpay_subscription_id )
			->willReturn(
				[
					'id'     => $mock_wcpay_subscription_id,
					'status' => 'active',
				]
			);

		$this->assertEmpty( $mock_subscription->get_meta( '_migrated_wcpay_subscription_id' ) );

		$migrator = new WC_Payments_Subscriptions_Migrator( $this->mock_api_client );
		$migrator->migrate_wcpay_subscription( $mock_subscription->get_id() );

		// set WCPay Subscription ID.
		$this->assertEquals( $mock_wcpay_subscription_id, $mock_subscription->get_meta( '_migrated_wcpay_subscription_id' ) );
	}
}
