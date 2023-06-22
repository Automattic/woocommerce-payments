<?php
/**
 * Class WC_Payments_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

/**
 * WC_Payments unit tests.
 */
class WC_Payments_Test extends WCPAY_UnitTestCase {

	const EXPECTED_WOOPAY_HOOKS = [
		'wc_ajax_wcpay_init_woopay' => [ WC_Payments::class, 'ajax_init_woopay' ],
	];

	public function set_up() {
		// Mock the main class's cache service.
		$this->_cache        = WC_Payments::get_database_cache();
		$this->_card_gateway = WC_Payments::get_gateway();
		$this->mock_cache    = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );
		WC_Payments::set_gateway( $this->_card_gateway );
		WC_Payments::mode()->live();
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

	public function test_it_registers_woopay_hooks_if_feature_flag_is_enabled() {
		// Enable dev mode so nonce check is disabled.
		WC_Payments::mode()->is_dev();

		$this->set_woopay_enabled( true );

		foreach ( self::EXPECTED_WOOPAY_HOOKS as $hook => $callback ) {
			$this->assertEquals( 10, has_filter( $hook, $callback ) );
		}
	}

	public function test_it_registers_woopay_hooks_if_feature_flag_is_enabled_but_not_in_dev_mode() {
		$this->set_woopay_enabled( true );

		foreach ( self::EXPECTED_WOOPAY_HOOKS as $hook => $callback ) {
			$this->assertEquals( 10, has_filter( $hook, $callback ) );
		}
	}

	public function test_it_does_not_register_woopay_hooks_if_feature_flag_is_disabled() {
		$this->set_woopay_enabled( false );

		foreach ( self::EXPECTED_WOOPAY_HOOKS as $hook => $callback ) {
			$this->assertEquals( false, has_filter( $hook, $callback ) );
		}
	}

	public function test_it_skips_stripe_link_gateway_registration() {
		update_option( WC_Payments_Features::UPE_SPLIT_FLAG_NAME, '1' );

		$card_gateway_mock = $this->createMock( UPE_Split_Payment_Gateway::class );
		$card_gateway_mock
			->expects( $this->once() )
			->method( 'get_payment_method_ids_enabled_at_checkout' )
			->willReturn(
				[
					'link',
					'card',
				]
			);
		$card_gateway_mock
			->expects( $this->once() )
			->method( 'get_stripe_id' )
			->willReturn( 'card' );
		WC_Payments::set_gateway( $card_gateway_mock );

		$registered_gateways = WC_Payments::register_gateway( [] );

		$this->assertCount( 1, $registered_gateways );
		$this->assertInstanceOf( UPE_Split_Payment_Gateway::class, $registered_gateways[0] );
		$this->assertEquals( $registered_gateways[0]->get_stripe_id(), 'card' );
	}

	public function test_rest_endpoints_validate_nonce() {

		if ( $this->is_wpcom() ) {
			$this->markTestSkipped( 'must be revisited. "/wc/store/checkout" is returning 404' );
		}

		$this->set_woopay_feature_flag_enabled( true );
		$request = new WP_REST_Request( 'GET', '/wc/store/checkout' );

		$response = rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'woocommerce_rest_missing_nonce', $response->get_data()['code'] );
	}

	public function test_ajax_init_woopay_sends_correct_customer_id() {
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
		$this->set_woopay_feature_flag_enabled( true );

		ob_start();
		WC_Payments::ajax_init_woopay();
		ob_get_clean();
	}

	public function test_ajax_init_woopay_has_not_verified_user_token_if_email_does_not_exists() {
		$this->mock_verified_user_store_api_token();

		$pre_http_request_cb = function ( $preempt, $parsed_args, $url ) {
			$body = json_decode( $parsed_args['body'], true );

			$this->assertArrayNotHasKey( 'verified_user_store_api_token', $body );
			$this->assertEmpty( WC()->session->get( 'woopay_verified_user_id' ) );

			return [ 'body' => wp_json_encode( [] ) ];
		};

		$wp_die_ajax_handler_cb = function () {
			return function ( $message, $title, $args ) {};
		};

		add_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler_cb );

		$this->set_woopay_feature_flag_enabled( true );

		$_POST['email'] = 'user@example.com';

		ob_start();
		WC_Payments::ajax_init_woopay();
		ob_get_clean();

		remove_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
	}

	public function test_ajax_init_woopay_has_not_verified_user_token_if_is_logged_off() {
		$user = self::factory()->user->create_and_get();

		$this->mock_verified_user_store_api_token();

		wp_set_current_user( $user->ID );

		$pre_http_request_cb = function ( $preempt, $parsed_args, $url ) {
			$body = json_decode( $parsed_args['body'], true );

			$this->assertArrayNotHasKey( 'verified_user_store_api_token', $body );
			$this->assertEmpty( WC()->session->get( 'woopay_verified_user_id' ) );

			return [ 'body' => wp_json_encode( [] ) ];
		};

		$wp_die_ajax_handler_cb = function () {
			return function ( $message, $title, $args ) {};
		};

		add_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler_cb );

		$this->set_woopay_feature_flag_enabled( true );

		$_POST['email'] = $user->user_email;

		ob_start();
		WC_Payments::ajax_init_woopay();
		ob_get_clean();

		wp_set_current_user( 0 );

		remove_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
	}

	public function test_ajax_init_woopay_has_verified_user_token_if_email_exists_and_is_logged_off() {
		$user = self::factory()->user->create_and_get();

		$this->mock_verified_user_store_api_token();

		$pre_http_request_cb = function ( $preempt, $parsed_args, $url ) use ( $user ) {
			$body = json_decode( $parsed_args['body'], true );

			$this->assertArrayHasKey( 'verified_user_store_api_token', $body );
			$this->assertEquals( WC()->session->get( 'woopay_verified_user_id' ), $user->ID );

			$payload = JsonWebToken::get_parts( $body['verified_user_store_api_token'] )->payload;
			$this->assertEquals( $payload->user_id, $user->ID );

			return [ 'body' => wp_json_encode( [] ) ];
		};

		$wp_die_ajax_handler_cb = function () {
			return function ( $message, $title, $args ) {};
		};

		add_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
		add_filter( 'wp_die_ajax_handler', $wp_die_ajax_handler_cb );

		$this->set_woopay_feature_flag_enabled( true );

		$_POST['email'] = $user->user_email;

		ob_start();
		WC_Payments::ajax_init_woopay();
		ob_get_clean();

		remove_filter( 'pre_http_request', $pre_http_request_cb, 10, 3 );
	}

	/**
	 * @param bool $is_enabled
	 */
	private function set_woopay_feature_flag_enabled( $is_enabled ) {
		// Make sure woopay hooks are not registered.
		foreach ( self::EXPECTED_WOOPAY_HOOKS as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}

		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_enabled ] );
		// Testing feature flag, so woopay setting should always be on.
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );

		WC_Payments::maybe_register_woopay_hooks();

		// Trigger the addition of the disable nonce filter when appropriate.
		apply_filters( 'rest_request_before_callbacks', [], [], new WP_REST_Request() );
	}

	private function set_woopay_enabled( $is_enabled ) {
		// Make sure woopay hooks are not registered.
		foreach ( self::EXPECTED_WOOPAY_HOOKS as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}

		// Testing woopay, so feature flag should always be on.
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', $is_enabled ? 'yes' : 'no' );

		WC_Payments::maybe_register_woopay_hooks();

		// Trigger the addition of the disable nonce filter when appropriate.
		apply_filters( 'rest_request_before_callbacks', [], [], new WP_REST_Request() );
	}

	private function mock_verified_user_store_api_token() {
		// Mocks the jetpack token.
		$jetpack_token = function ( $value, $name ) {
			if ( 'blog_token' === $name ) {
				return 'moked.token';
			}

			return $value;
		};

		add_filter( 'jetpack_options', $jetpack_token, 10, 2 );

		$customer_id = 'cus_123456789';

		$mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();
		$mock_customer_service
			->expects( $this->once() )
			->method( 'create_customer_for_user' )
			->with( $this->anything(), $this->anything() )
			->will( $this->returnValue( $customer_id ) );

		WC_Payments::set_customer_service( $mock_customer_service );

		add_filter(
			'active_plugins',
			function ( $args ) {
				return array_merge( $args, [ 'woocommerce-points-and-rewards/woocommerce-points-and-rewards.php' ] );
			}
		);
	}
}
