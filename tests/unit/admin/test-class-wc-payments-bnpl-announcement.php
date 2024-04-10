<?php
/**
 * Class WC_Payments_Bnpl_Announcement_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Bnpl_Announcement unit tests.
 */
class WC_Payments_Bnpl_Announcement_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $account_service_mock;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $gateway_mock;

	/**
	 * @var WC_Payments_Bnpl_Announcement
	 */
	private $bnpl_announcement;

	public function set_up() {
		$this->gateway_mock = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->setMethods( [ 'get_upe_enabled_payment_method_ids' ] )
			->getMock();

		$this->account_service_mock = $this->getMockBuilder( WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'get_account_country' ] )->getMock();

		$this->bnpl_announcement = new WC_Payments_Bnpl_Announcement( $this->gateway_mock, $this->account_service_mock );
	}

	public function test_it_enqueues_scripts_for_eligible_users() {
		define( 'WP_ADMIN', true );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		WC_Payments::mode()->live();
		$this->set_current_user_can( true );
		$this->set_mocked_time( strtotime( '2024-06-06' ) );
		$this->account_service_mock->method( 'get_account_country' )->willReturn( 'US' );
		$this->account_service_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [ 'card' ] );
		delete_user_meta( get_current_user_id(), '_wcpay_bnpl_april15_viewed' );
		set_transient( 'wcpay_bnpl_april15_successful_purchases_count', 5 );

		$this->bnpl_announcement->maybe_enqueue_scripts();
	}

	private function set_current_user_can( bool $can ) {
		global $current_user_can;

		// phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$current_user_can = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'current_user_can' ] )
			->getMock();

		$current_user_can->method( 'current_user_can' )->willReturn( $can );
	}

	private function set_mocked_time( int $timestamp ) {
		$time_mock = $this->getMockBuilder( 'DateTime' )
			->setMethods( [ 'getTimestamp' ] )
			->getMock();
		$time_mock->expects( $this->any() )
			->method( 'getTimestamp' )
			->willReturn( $timestamp );

		$this->setGlobalMock( 'time', $time_mock );
	}
}
