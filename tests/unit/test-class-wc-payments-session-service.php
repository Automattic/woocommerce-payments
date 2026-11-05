<?php
/**
 * Class WC_Payments_Session_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Order_Status;
use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Internal\Service\DuplicatePaymentPreventionService;

/**
 * WC_Payments_Session_Service unit tests.
 */
class WC_Payments_Session_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * The mocked session store ID.
	 */
	const SESSION_STORE_ID = 'st_session_store_id';

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Session_Service
	 */
	private $session_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Session_Handler.
	 *
	 * @var WC_Session_Handler|MockObject
	 */
	private $mock_session_handler;

	/**
	 * Session state restored after migration tests.
	 *
	 * @var array
	 */
	private $original_session_state;

	/**
	 * Session rows created by migration tests.
	 *
	 * @var string[]
	 */
	private $migration_session_ids = [];

	/**
	 * Pre-test setup
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_session_state = [ WC()->session, $_COOKIE, get_current_user_id() ];

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->session_service = new WC_Payments_Session_Service( $this->mock_api_client );

		$this->mock_session_handler = $this->createMock( WC_Session_Handler::class );

		add_filter( 'pre_option_' . WC_Payments_Session_Service::SESSION_STORE_ID_OPTION, [ $this, 'mock_session_store_id' ] );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->migration_session_ids as $session_id ) {
			( new WC_Session_Handler() )->delete_session( $session_id );
		}
		$this->migration_session_ids          = [];
		[ WC()->session, $_COOKIE, $user_id ] = $this->original_session_state;
		wp_set_current_user( $user_id );
		remove_filter( 'woocommerce_set_cookie_enabled', [ $this, 'capture_session_cookie' ], 10 );

		remove_filter( 'pre_option_' . WC_Payments_Session_Service::SESSION_STORE_ID_OPTION, [ $this, 'mock_session_store_id' ] );
		parent::tear_down();
	}

	/**
	 * @dataProvider provider_session_ownership_migration
	 */
	public function test_guest_login_preserves_only_owned_checkout_data( $owners, $usable ) {
		$guest_id                      = $this->prepare_guest_session();
		$user_id                       = self::factory()->user->create();
		$this->migration_session_ids[] = (string) $user_id;

		$paid_order = WC_Helper_Order::create_order();
		$paid_order->set_status( Order_Status::COMPLETED );
		$paid_order->set_payment_method( 'woocommerce_payments' );
		$paid_order->set_cart_hash( 'same_cart' );
		$paid_order->update_meta_data( '_intent_id', 'pi_test' );
		$paid_order->save();
		WC()->session->set( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_KEY, 'cus_test' );
		WC()->session->set( WC_Payments_Order_Service::PAID_INTENT_ID_SESSION_KEY, 'pi_test' );
		WC()->session->set( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER, $paid_order->get_id() );
		WC()->session->set( 'unrelated', 'keep' );
		foreach ( $owners as $key => $owner ) {
			WC()->session->set( $key, 'own' === $owner ? $guest_id : $owner );
		}
		WC()->session->save_data();

		wp_set_current_user( $user_id );
		WC()->session->init_session_cookie();
		WC()->session->save_data();

		$this->assertSame( (string) $user_id, WC()->session->get_customer_id() );
		$migration_hook_available = version_compare( WC_VERSION, '8.8.0', '>=' );
		$expected_owned_id        = $migration_hook_available ? (string) $user_id : $guest_id;
		if ( ! $migration_hook_available ) {
			$usable = [ false, false, false ];
		}
		foreach ( $owners as $key => $owner ) {
			$this->assertSame( 'own' === $owner ? $expected_owned_id : $owner, WC()->session->get( $key ) );
		}
		$this->assertSame( 'keep', WC()->session->get( 'unrelated' ) );
		$this->assertSame( 'cus_test', WC()->session->get( 'wcpay_customer_id' ) );
		$this->assertSame( 'pi_test', WC()->session->get( 'wcpay_paid_intent_id' ) );
		$this->assertSame( $paid_order->get_id(), WC()->session->get( 'wcpay_processing_order' ) );
		$this->assertSame( $usable[0] ? 'cus_test' : null, WC_Payments::get_customer_service()->get_customer_id_by_user_id( 0 ) );
		$this->assertSame(
			! $usable[1],
			( new WC_Payments_Order_Success_Page() )->maybe_skip_email_verification_after_payment( true, $paid_order, 'order-received' )
		);
		$this->assertSame(
			$usable[2] ? $paid_order->get_id() : null,
			wcpay_get_container()->get( DuplicatePaymentPreventionService::class )->get_session_processing_order()
		);

		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway->method( 'get_return_url' )->willReturn( 'https://example.com/order-received/' );
		$duplicate_service = new Duplicate_Payment_Prevention_Service();
		$duplicate_service->init( $gateway, $this->createMock( WC_Payments_Order_Service::class ) );
		$new_order = WC_Helper_Order::create_order();
		$new_order->set_cart_hash( 'same_cart' );
		$new_order->save();
		$new_order_id = $new_order->get_id();
		$result       = $duplicate_service->check_against_session_processing_order( $new_order );
		if ( $usable[2] ) {
			$this->assertSame( 'success', $result['result'] );
			$this->assertSame( 'https://example.com/order-received/?wcpay_paid_for_previous_order=yes', $result['redirect'] );
			$this->assertSame( 'trash', wc_get_order( $new_order_id )->get_status() );
		} else {
			$this->assertNull( $result );
			$this->assertSame( 'pending', wc_get_order( $new_order_id )->get_status() );
		}
	}

	public function provider_session_ownership_migration() {
		return [
			'own values'     => [
				[
					'wcpay_customer_id_session'      => 'own',
					'wcpay_paid_intent_id_session'   => 'own',
					'wcpay_processing_order_session' => 'own',
				],
				[ true, true, true ],
			],
			'other session'  => [
				[
					'wcpay_customer_id_session'      => 't_other',
					'wcpay_paid_intent_id_session'   => 't_other',
					'wcpay_processing_order_session' => 't_other',
				],
				[ false, false, false ],
			],
			'missing owners' => [
				[
					'wcpay_customer_id_session'      => null,
					'wcpay_paid_intent_id_session'   => null,
					'wcpay_processing_order_session' => null,
				],
				[ false, false, false ],
			],
			'mixed owners'   => [
				[
					'wcpay_customer_id_session'      => 'own',
					'wcpay_paid_intent_id_session'   => 't_other',
					'wcpay_processing_order_session' => 'own',
				],
				[ true, false, true ],
			],
		];
	}

	public function test_checkout_account_keeps_customer_after_session_migration() {
		$guest_id = $this->prepare_guest_session();
		WC_Subscriptions::set_wcs_is_checkout_blocks_api_request( '__return_true' );
		WC()->session->set( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_KEY, 'cus_checkout' );
		WC()->session->set( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_OWNER_KEY, $guest_id );
		WC()->session->save_data();

		$user_id = wc_create_new_customer( 'checkout-migration@example.com', 'checkout-migration', 'test-password' );
		$this->assertIsInt( $user_id );
		$this->migration_session_ids[] = (string) $user_id;
		wp_set_current_user( $user_id );
		WC()->session->init_session_cookie();

		$this->assertSame( 'cus_checkout', WC_Payments::get_customer_service()->get_customer_id_by_user_id( $user_id ) );
		$expected_owner = version_compare( WC_VERSION, '8.8.0', '>=' ) ? (string) $user_id : $guest_id;
		$this->assertSame( $expected_owner, WC()->session->get( 'wcpay_customer_id_session' ) );
	}

	/**
	 * @dataProvider provider_invalid_migration_ids
	 */
	public function test_migration_rejects_invalid_session_ids( $old_id, $new_id ) {
		$current_id = $this->prepare_guest_session();
		WC()->session->set( WC_Payments_Customer_Service::CUSTOMER_ID_SESSION_OWNER_KEY, $old_id );

		$this->session_service->migrate_session_ownership( $old_id, 'current' === $new_id ? $current_id : $new_id );

		$this->assertSame( $old_id, WC()->session->get( 'wcpay_customer_id_session' ) );
	}

	public function provider_invalid_migration_ids() {
		return [
			'missing old ID'            => [ null, 'current' ],
			'empty old ID'              => [ '', 'current' ],
			'non-string old ID'         => [ 123, 'current' ],
			'missing new ID'            => [ 't_guest', null ],
			'empty new ID'              => [ 't_guest', '' ],
			'non-string new ID'         => [ 't_guest', 123 ],
			'different current session' => [ 't_guest', 't_other' ],
		];
	}

	/**
	 * Starts a real guest session with a cookie that core can validate during migration.
	 *
	 * @return string Guest session ID.
	 */
	private function prepare_guest_session(): string {
		wp_set_current_user( 0 );
		$_COOKIE      = [];
		WC()->session = new WC_Session_Handler();
		WC()->session->init_session_cookie();
		add_filter( 'woocommerce_set_cookie_enabled', [ $this, 'capture_session_cookie' ], 10, 3 );
		WC()->session->set_customer_session_cookie( true );
		$session_id                    = WC()->session->get_customer_id();
		$this->migration_session_ids[] = $session_id;
		return $session_id;
	}

	/**
	 * Makes the emitted session cookie available to core without sending HTTP headers.
	 *
	 * @param bool   $enabled Whether to emit the cookie.
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @return bool
	 */
	public function capture_session_cookie( $enabled, $name, $value ) {
		if ( 0 === strpos( $name, 'wp_woocommerce_session_' ) ) {
			$_COOKIE[ $name ] = $value;
			return false;
		}
		return $enabled;
	}

	public function mock_session_store_id() {
		return self::SESSION_STORE_ID;
	}

	public function test_user_just_logged_in_returns_false_for_no_user_logged_in() {
		wp_set_current_user( 0 );

		$this->assertFalse( $this->session_service->user_just_logged_in() );
	}

	public function test_user_just_logged_in_returns_false_on_invalid_session() {
		$wpcom_blog_id = 'blog_id';
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( $wpcom_blog_id );

		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertFalse( $this->session_service->user_just_logged_in() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_user_just_logged_in_returns_false_for_missing_or_invalid_session_cookie() {
		wp_set_current_user( 1 );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn( false );

		$this->assertFalse( $this->session_service->user_just_logged_in() );

		WC()->session = $tmp_session;
	}

	public function test_user_just_logged_in() {
		$new_customer_id = 1;
		$old_customer_id = 2;

		wp_set_current_user( $new_customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$old_customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $new_customer_id );

		$this->assertTrue( $this->session_service->user_just_logged_in() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_returns_when_no_jetpack_connection() {
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( null );

		$this->assertNotEmpty( $this->session_service->get_sift_session_id() );
	}

	public function test_get_sift_session_id_from_cookie_when_user_just_logged_in() {
		$new_customer_id = 1;
		$old_customer_id = 222;

		wp_set_current_user( $new_customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$old_customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $new_customer_id );

		$this->assertEquals( self::SESSION_STORE_ID . '_' . $old_customer_id, $this->session_service->get_sift_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_from_session_when_didnt_user_just_logged_in() {
		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$customer_id, // The same customer ID is used for the cookie and the session.
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $customer_id );

		$this->assertEquals( self::SESSION_STORE_ID . '_' . $customer_id, $this->session_service->get_sift_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_sift_session_id_returns_null_on_invalid_session() {
		$customer_id = 1;

		wp_set_current_user( $customer_id );

		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertNull( $this->session_service->get_sift_session_id() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_no_jetpack_connection() {
		$this->mock_api_client->method( 'get_blog_id' )->willReturn( null );

		$this->assertNull( $this->session_service->get_cookie_session_id() );
	}

	public function test_get_cookie_session_id_returns_null_when_no_session_handler() {
		$tmp_session  = WC()->session;
		WC()->session = null;

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_invalid_session_handler() {
		$tmp_session  = WC()->session;
		WC()->session = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'set' ] )
			->getMock();

		add_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		remove_filter( 'woocommerce_session_handler', [ $this, 'return_stdclass' ] );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id_returns_null_when_no_cookie() {
		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn( false );

		$this->assertNull( $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_cookie_session_id() {
		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$customer_id = 'customer_id';
		$this->mock_session_handler
			->method( 'get_session_cookie' )
			->willReturn(
				[
					$customer_id,
					time() + ( 60 * 60 * 48 ),
					time() + ( 60 * 60 * 47 ),
					'hash',
				]
			);

		$this->assertEquals( self::SESSION_STORE_ID . '_' . $customer_id, $this->session_service->get_cookie_session_id() );

		WC()->session = $tmp_session;
	}

	public function test_get_store_id_returns_from_option() {
		$this->assertEquals( self::SESSION_STORE_ID, $this->session_service->get_store_id() );
	}

	public function test_get_store_id_stores_to_option() {
		remove_filter( 'pre_option_' . WC_Payments_Session_Service::SESSION_STORE_ID_OPTION, [ $this, 'mock_session_store_id' ] );
		update_option( WC_Payments_Session_Service::SESSION_STORE_ID_OPTION, false );

		if ( function_exists( 'did_filter' ) ) {
			$update_option_run_count = did_filter( 'pre_update_option' );
		}

		$store_id = $this->session_service->get_store_id();

		if ( function_exists( 'did_filter' ) ) {
			$this->assertEquals( $update_option_run_count + 1, did_filter( 'pre_update_option' ) );
		}

		// Assert that the option stored value is the same as the returned one.
		$this->assertEquals( $store_id, get_option( WC_Payments_Session_Service::SESSION_STORE_ID_OPTION ) );

		if ( function_exists( 'did_filter' ) ) {
			// Assert that there is no additional call to update_option on further fetches.
			$this->session_service->get_store_id();
			$this->assertEquals( $update_option_run_count + 1, did_filter( 'pre_update_option' ) );
		}
	}

	public function test_link_current_session_to_customer() {
		$tmp_session  = WC()->session;
		WC()->session = $this->mock_session_handler;

		$customer_id = 'customer_id';
		$this->mock_session_handler
			->method( 'get_customer_id' )
			->willReturn( $customer_id );

		$expected_response = [ 'success' => true ];
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'link_session_to_customer' )
			->with( self::SESSION_STORE_ID . '_' . $customer_id, $customer_id )
			->willReturn( $expected_response );

		$this->assertEquals( $expected_response, $this->session_service->link_current_session_to_customer( $customer_id ) );

		WC()->session = $tmp_session;
	}

	public function return_stdclass() {
		return stdClass::class;
	}
}
