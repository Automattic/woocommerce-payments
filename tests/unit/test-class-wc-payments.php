<?php
/**
 * Class WC_Payments_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Session;

/**
 * WC_Payments unit tests.
 */
class WC_Payments_Test extends WCPAY_UnitTestCase {

	const EXPECTED_WOOPAY_HOOKS = [
		'wc_ajax_wcpay_init_woopay'        => [ WooPay_Session::class, 'ajax_init_woopay' ],
		'wc_ajax_wcpay_get_woopay_session' => [ WooPay_Session::class, 'ajax_get_woopay_session' ],
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
		WC_Payments::mode()->dev();

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
		$all_gateways_before_registration = count( WC_Payments::get_payment_method_map() );
		$card_gateway_mock                = $this->createMock( WC_Payment_Gateway_WCPay::class );

		$card_gateway_mock
			->expects( $this->once() )
			->method( 'get_stripe_id' )
			->willReturn( 'card' );
		WC_Payments::set_gateway( $card_gateway_mock );

		$registered_gateways = WC_Payments::register_gateway( [] );

		$this->assertCount( $all_gateways_before_registration - 1, $registered_gateways );
		$this->assertInstanceOf( WC_Payment_Gateway_WCPay::class, $registered_gateways[0] );
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

	public function test_set_woopayments_gateways_before_other_gateways_when_not_in_ordering() {
		$woopayments_gateway_ids = WC_Payments::get_woopayments_gateway_ids();
		$main_gateway_id         = WC_Payments::get_gateway()->id;

		$initial_ordering = [
			'other_gateway_1' => 0,
			'other_gateway_2' => 1,
		];

		$result = WC_Payments::order_woopayments_gateways( $initial_ordering );

		$this->assertContainsAllGateways( $woopayments_gateway_ids, $result );
		$this->assertSequentialOrdering( $woopayments_gateway_ids, $result );
		$this->assertLessThan( $result['other_gateway_1'], $result[ $main_gateway_id ] );
	}

	public function test_set_woopayments_gateways_after_main_gateway() {
		$woopayments_gateway_ids = WC_Payments::get_woopayments_gateway_ids();
		$main_gateway_id         = WC_Payments::get_gateway()->id;

		$initial_ordering = [
			'other_gateway_1' => 0,
			$main_gateway_id  => 1,
			'other_gateway_2' => 2,
		];

		$result = WC_Payments::order_woopayments_gateways( $initial_ordering );

		$this->assertContainsAllGateways( $woopayments_gateway_ids, $result );
		$this->assertSequentialOrdering( $woopayments_gateway_ids, $result );
		$this->assertLessThan( $result[ $main_gateway_id ], $result['other_gateway_1'] );
		$this->assertLessThan( $result['other_gateway_2'], $result[ $main_gateway_id ] );
	}

	public function test_set_woopayments_gateways_at_beginning_when_ordering_is_empty() {
		$woopayments_gateway_ids = WC_Payments::get_woopayments_gateway_ids();
		$initial_ordering        = [];

		$result = WC_Payments::order_woopayments_gateways( $initial_ordering );

		$this->assertContainsAllGateways( $woopayments_gateway_ids, $result );
		$this->assertSequentialOrdering( $woopayments_gateway_ids, $result );
		$this->assertEquals( count( $woopayments_gateway_ids ), count( $result ) );
	}

	/**
	 * Assert that all WooPayments gateways are in the result
	 *
	 * @param array $gateways Expected gateway IDs
	 * @param array $result   Result from order_woopayments_gateways
	 */
	private function assertContainsAllGateways( $gateways, $result ) {
		foreach ( $gateways as $gateway_id ) {
			$this->assertArrayHasKey( $gateway_id, $result );
		}
	}

	/**
	 * Assert that the WooPayments gateways are in sequential order
	 *
	 * @param array $gateways Expected gateway IDs
	 * @param array $result   Result from order_woopayments_gateways
	 */
	private function assertSequentialOrdering( $gateways, $result ) {
		$positions = [];
		foreach ( $gateways as $gateway_id ) {
			$positions[ $gateway_id ] = $result[ $gateway_id ];
		}

		asort( $positions );

		// Check that positions are sequential.
		$previous_position = null;
		foreach ( $positions as $position ) {
			if ( null !== $previous_position ) {
				$this->assertEquals( $previous_position + 1, $position, 'Gateway positions should be sequential' );
			}
			$previous_position = $position;
		}
	}
}
