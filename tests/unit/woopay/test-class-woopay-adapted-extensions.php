<?php
/**
 * Class WooPay_Adapted_Extensions_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WooPay\WooPay_Adapted_Extensions;
use WCPay\WooPay\WooPay_Scheduler;

/**
 * WooPay_Adapted_Extensions unit tests.
 */
class WooPay_Adapted_Extensions_Test extends WCPAY_UnitTestCase {
	const BASE_POINTS_AND_REWARDS_SCRIPT_DATA = [
		'woocommerce-points-and-rewards-blocks' => 'active',
		'points_available'                      => 0,
		'minimum_points_amount'                 => 0,
		'partial_redemption_enabled'            => true,
		'points_label_singular'                 => 'Point',
		'points_label_plural'                   => 'Points',
	];

	/**
	 * @var WP_User
	 */
	private $test_user = null;

	/**
	 * @var WooPay_Adapted_Extensions
	 */
	private $woopay_adapted_extensions = null;

	public function setUp() : void {
		parent::setUp();

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function( $integration_registry ) {
				$integration_registry->register( new WC_Points_Rewards_Integration() );
			}
		);

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function( $integration_registry ) {
				$integration_registry->register( new WC_GC_Checkout_Blocks_Integration() );
			}
		);

		update_option( 'wc_points_rewards_redeem_points_ratio', '100:1' );

		$this->test_user                 = self::factory()->user->create_and_get();
		$this->woopay_adapted_extensions = new WooPay_Adapted_Extensions();
	}

	public function test_get_adapted_extensions_data_without_enable_adapted_extensions() {
		update_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		$this->assertEquals( $this->woopay_adapted_extensions->get_adapted_extensions_data( 'test@example.com' ), [] );
	}

	public function test_get_points_and_rewards_data_while_logged_out() {
		$expected = array_merge(
			self::BASE_POINTS_AND_REWARDS_SCRIPT_DATA,
			[
				'points_ratio'        => [
					'points'         => 100.0,
					'monetary_value' => 1,
				],
				'points_available'    => $this->test_user->ID,
				'should_verify_email' => true,
			]
		);

		$this->assertEquals( $this->woopay_adapted_extensions->get_points_and_rewards_data( $this->test_user ), $expected );
	}

	public function test_get_points_and_rewards_data_while_logged_in() {
		wp_set_current_user( $this->test_user->ID );

		$expected = array_merge(
			self::BASE_POINTS_AND_REWARDS_SCRIPT_DATA,
			[
				'points_ratio'        => [
					'points'         => 100.0,
					'monetary_value' => 1,
				],
				'points_available'    => $this->test_user->ID,
				'should_verify_email' => false,
			]
		);

		$this->assertEquals( $this->woopay_adapted_extensions->get_points_and_rewards_data( $this->test_user ), $expected );
	}

	public function test_get_points_and_rewards_data_with_zero_points() {
		$expected            = array_merge(
			self::BASE_POINTS_AND_REWARDS_SCRIPT_DATA,
			[
				'points_ratio'     => [
					'points'         => 100.0,
					'monetary_value' => 1,
				],
				'points_available' => 0,
			]
		);
		$this->test_user->ID = 0;

		$this->assertEquals( $this->woopay_adapted_extensions->get_points_and_rewards_data( $this->test_user ), $expected );
	}

	public function test_get_gift_cards_data() {
		wp_set_current_user( $this->test_user->ID );

		$expected = [
			'account_orders_link' => add_query_arg( [ 'wc_gc_show_pending_orders' => 'yes' ], wc_get_account_endpoint_url( 'orders' ) ),
		];

		$this->assertEquals( $this->woopay_adapted_extensions->get_gift_cards_data( $this->test_user ), $expected );
	}

	public function test_get_gift_cards_data_while_logged_out() {
		$expected = [
			'account_orders_link' => add_query_arg( [ 'wc_gc_show_pending_orders' => 'yes' ], wc_get_account_endpoint_url( 'orders' ) ),
			'should_verify_email' => true,
		];

		$this->assertEquals( $this->woopay_adapted_extensions->get_gift_cards_data( $this->test_user ), $expected );
	}

	public function test_get_gift_cards_data_while_logged_out_with_zero_balance() {
		$expected            = [
			'account_orders_link' => add_query_arg( [ 'wc_gc_show_pending_orders' => 'yes' ], wc_get_account_endpoint_url( 'orders' ) ),
		];
		$this->test_user->ID = 0;

		$this->assertEquals( $this->woopay_adapted_extensions->get_gift_cards_data( $this->test_user ), $expected );
	}

	public function test_get_store_api_nonce_for_user_nonce_does_not_works_for_guest_user() {
		wp_set_current_user( 0 );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertFalse( wp_verify_nonce( $nonce_for_test_user, 'wc_store_api' ) );
	}

	public function test_get_store_api_nonce_for_user_nonce_works_for_authenticated_user() {
		wp_set_current_user( $this->test_user->ID );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertEquals( wp_verify_nonce( $nonce_for_test_user, 'wc_store_api' ), 1 );
	}

	public function test_get_store_api_nonce_for_user_does_not_work_for_guest_user() {
		wp_set_current_user( 0 );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertFalse( wp_verify_nonce( $nonce_for_test_user, 'wc_store_api' ) );
	}

	public function test_get_store_api_nonce_for_user_works_for_authenticated_user() {
		wp_set_current_user( $this->test_user->ID );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertEquals( wp_verify_nonce( $nonce_for_test_user, 'wc_store_api' ), 1 );
	}

	public function test_get_store_api_nonce_for_user_does_not_work_for_guest_user_with_another_action() {
		wp_set_current_user( 0 );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertFalse( wp_verify_nonce( $nonce_for_test_user, 'nonce_action' ) );
	}

	public function test_get_store_api_nonce_for_user_does_not_work_for_authenticated_user_with_another_action() {
		wp_set_current_user( $this->test_user->ID );

		$nonce_for_test_user = $this->woopay_adapted_extensions->get_store_api_nonce_for_user( $this->test_user );

		$this->assertFalse( wp_verify_nonce( $nonce_for_test_user, 'nonce_action' ) );
	}
}
