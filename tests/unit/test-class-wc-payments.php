<?php
/**
 * Class WC_Payments_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments unit tests.
 */
class WC_Payments_Test extends WP_UnitTestCase {

	const EXPECTED_PLATFORM_CHECKOUT_HOOKS = [
		'wc_ajax_wcpay_init_platform_checkout' => [ WC_Payments::class, 'ajax_init_platform_checkout' ],
		'determine_current_user'               => [
			WC_Payments::class,
			'determine_current_user_for_platform_checkout',
		],
	];

	public function set_up() {
		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );
		remove_all_filters( 'wcpay_dev_mode' );
		parent::tear_down();
	}

	public function test_it_runs_upgrade_routines_during_init_at_priority_10() {
		$install_actions_priority = has_action(
			'init',
			[ WC_Payments::class, 'install_actions' ]
		);

		$this->assertEquals( 10, $install_actions_priority );
	}

	public function test_it_calls_upgrade_hook_during_upgrade() {
		update_option( 'woocommerce_woocommerce_payments_version', '1.0.0' );

		$upgrade_run_count = did_action( 'woocommerce_woocommerce_payments_updated' );
		WC_Payments::install_actions();
		$this->assertEquals( $upgrade_run_count + 1, did_action( 'woocommerce_woocommerce_payments_updated' ) );
	}

	public function test_it_registers_platform_checkout_hooks_if_feature_flag_is_enabled() {
		// Enable dev mode so nonce check is disabled.
		add_filter(
			'wcpay_dev_mode',
			function () {
				return true;
			}
		);

		$this->set_platform_checkout_enabled( true );

		foreach ( self::EXPECTED_PLATFORM_CHECKOUT_HOOKS as $hook => $callback ) {
			$this->assertEquals( 10, has_filter( $hook, $callback ) );
		}
	}

	public function test_it_registers_platform_checkout_hooks_if_feature_flag_is_enabled_but_not_in_dev_mode() {
		$this->set_platform_checkout_enabled( true );

		foreach ( self::EXPECTED_PLATFORM_CHECKOUT_HOOKS as $hook => $callback ) {
			$this->assertEquals( 10, has_filter( $hook, $callback ) );
		}
	}

	public function test_it_does_not_register_platform_checkout_hooks_if_feature_flag_is_disabled() {
		$this->set_platform_checkout_enabled( false );

		foreach ( self::EXPECTED_PLATFORM_CHECKOUT_HOOKS as $hook => $callback ) {
			$this->assertEquals( false, has_filter( $hook, $callback ) );
		}
	}

	public function test_rest_endpoints_validate_nonce() {
		$this->set_platform_checkout_feature_flag_enabled( true );
		$request = new WP_REST_Request( 'GET', '/wc/store/checkout' );

		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'woocommerce_rest_missing_nonce', $response->get_data()['code'] );
	}

	public function test_ajax_init_platform_checkout_sends_correct_data() {
		// Necessary in order to prevent die from being called.
		define( 'DOING_AJAX', true );

		$customer_id = 'cus_123456789';

		$pre_http_request_cb = function ( $preempt, $parsed_args, $url ) use ( $customer_id ) {
			$body = json_decode( $parsed_args['body'] );
			$this->assertEquals( $customer_id, $body->customer_id );
			return [ 'body' => wp_json_encode( [] ) ];
		};

		$wp_die_ajax_handler_cb = function () {
			return function ( $message, $title, $args ) {};
		};

		add_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler_cb );

		$mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
									->disableOriginalConstructor()
									->getMock();
		$mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with( $this->anything(), $this->anything() )
			->will( $this->returnValue( $customer_id ) );

		WC_Payments::set_customer_service( $mock_customer_service );
		$this->set_platform_checkout_feature_flag_enabled( true );

		WC_Payments::ajax_init_platform_checkout();
	}

	/**
	 * @param bool $is_enabled
	 */
	private function set_platform_checkout_feature_flag_enabled( $is_enabled ) {
		// Make sure platform checkout hooks are not registered.
		foreach ( self::EXPECTED_PLATFORM_CHECKOUT_HOOKS as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}

		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_enabled ] );
		// Testing feature flag, so platform_checkout setting should always be on.
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );

		WC_Payments::maybe_register_platform_checkout_hooks();

		// Trigger the addition of the disable nonce filter when appropriate.
		apply_filters( 'rest_request_before_callbacks', [], [], null );
	}

	private function set_platform_checkout_enabled( $is_enabled ) {
		// Make sure platform checkout hooks are not registered.
		foreach ( self::EXPECTED_PLATFORM_CHECKOUT_HOOKS as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}

		// Testing platform_checkout, so feature flag should always be on.
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', $is_enabled ? 'yes' : 'no' );

		WC_Payments::maybe_register_platform_checkout_hooks();

		// Trigger the addition of the disable nonce filter when appropriate.
		apply_filters( 'rest_request_before_callbacks', [], [], null );
	}
}
