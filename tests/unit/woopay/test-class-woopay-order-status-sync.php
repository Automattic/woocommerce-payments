<?php
/**
 * Class WooPay_Order_Status_Sync_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Order_Status_Sync;

/**
 * WooPay_Utilities unit tests.
 */
class WooPay_Order_Status_Sync_Test extends WP_UnitTestCase {

	/**
	 * @var WP_User $admin_user
	 */
	protected static $admin_user;

	public function set_up() {
		parent::set_up();

		$this->api_client_mock   = $this->createMock( WC_Payments_API_Client::class );
		$this->webhook_sync_mock = new WCPay\WooPay\WooPay_Order_Status_Sync( $this->api_client_mock );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		// Enable woopay.
		$this->set_is_woopay_eligible( true );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get( [ 'role' => 'administrator' ] );
	}

	/**
	 * Tests that the webhook is created succesfuly if the logged in user has the capability manage_woocommerce.
	 */
	public function test_webhook_is_created() {
		wp_set_current_user( self::$admin_user->ID );

		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();

		$this->assertNotEmpty( WooPay_Order_Status_Sync::get_webhook() );
	}

	/**
	 * Tests that the webhook is deleted succesfuly.
	 */
	public function test_webhook_removal() {

		wp_set_current_user( self::$admin_user->ID );

		$this->webhook_sync_mock->maybe_create_woopay_order_webhook();
		$this->assertNotEmpty( WooPay_Order_Status_Sync::get_webhook() );

		$this->set_is_woopay_eligible( false );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );

		$this->webhook_sync_mock->remove_webhook();
		$this->assertEmpty( WooPay_Order_Status_Sync::get_webhook() );

	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function set_is_woopay_eligible( $is_woopay_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_woopay_eligible ] );
	}

}
