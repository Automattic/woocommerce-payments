<?php
/**
 * Class AbilitiesRegistrarTest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Tests\Internal\Abilities;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

/**
 * Tests for the AbilitiesRegistrar scaffold.
 */
class AbilitiesRegistrarTest extends WCPAY_UnitTestCase {

	// The registrar hooks both `abilities_api_*_init` (the abilities-api
	// composer package) and `wp_abilities_api_*_init` (the WP-Core merge).
	// Asserting on either action name verifies the wiring; we use the WP-Core
	// merge names since that's what fires on WP 6.9+ test environments.
	const CATEGORIES_HOOK = 'wp_abilities_api_categories_init';
	const ABILITIES_HOOK  = 'wp_abilities_api_init';
	const FEATURE_FILTER  = 'woocommerce_payments_abilities_enabled';

	/**
	 * Tear down — reset filter, hooks, current user, and the registrar's
	 * static idempotency flags so each test exercises a fresh registration
	 * path. Note: the upstream WP_Abilities_Registry singleton holds onto
	 * registrations across tests; assertions about ability presence are a
	 * post-condition of any test in the suite, not of the test in isolation.
	 */
	public function tear_down() {
		remove_all_filters( self::FEATURE_FILTER );
		wp_set_current_user( 0 );
		AbilitiesRegistrar::reset_for_testing();
		parent::tear_down();
	}

	public function test_init_is_no_op_when_feature_flag_disabled() {
		remove_all_actions( self::CATEGORIES_HOOK );
		remove_all_actions( self::ABILITIES_HOOK );
		remove_all_filters( self::FEATURE_FILTER );

		AbilitiesRegistrar::init();

		$this->assertFalse(
			has_action(
				self::CATEGORIES_HOOK,
				[ AbilitiesRegistrar::class, 'register_category' ]
			),
			'Expected init() to short-circuit when the feature filter is unset (default false).'
		);
		$this->assertFalse(
			has_action(
				self::ABILITIES_HOOK,
				[ AbilitiesRegistrar::class, 'register_abilities' ]
			),
			'Expected init() to short-circuit when the feature filter is unset (default false).'
		);
	}

	public function test_init_hooks_register_category_on_categories_action() {
		// Assert hook wiring on either action variant — the abilities-api
		// composer package fires `abilities_api_categories_init`; the WP Core
		// merge fires `wp_abilities_api_categories_init`. The registrar hooks
		// both for forward compatibility, so either being wired is a pass.
		//
		// We deliberately do NOT fall through to checking the registry
		// singleton state, because the WP_Abilities_Registry singleton
		// persists across tests in the same PHP process; once any prior
		// registration-triggering test populates it, that check would always
		// succeed regardless of whether `init()` actually wired the hooks
		// (a vacuous green). The hook-wiring check is the load-bearing one.
		remove_all_actions( 'abilities_api_categories_init' );
		remove_all_actions( 'wp_abilities_api_categories_init' );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		$wp_core_hook = has_action(
			'wp_abilities_api_categories_init',
			[ AbilitiesRegistrar::class, 'register_category' ]
		);
		$package_hook = has_action(
			'abilities_api_categories_init',
			[ AbilitiesRegistrar::class, 'register_category' ]
		);

		$this->assertTrue(
			false !== $wp_core_hook || false !== $package_hook,
			'Expected init() to hook register_category on either `abilities_api_categories_init` or `wp_abilities_api_categories_init`.'
		);
	}

	public function test_init_hooks_register_abilities_on_abilities_action() {
		// See the note on the category-hook test above for why we only assert
		// hook wiring and deliberately skip the singleton state check.
		remove_all_actions( 'abilities_api_init' );
		remove_all_actions( 'wp_abilities_api_init' );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		$wp_core_hook = has_action(
			'wp_abilities_api_init',
			[ AbilitiesRegistrar::class, 'register_abilities' ]
		);
		$package_hook = has_action(
			'abilities_api_init',
			[ AbilitiesRegistrar::class, 'register_abilities' ]
		);

		$this->assertTrue(
			false !== $wp_core_hook || false !== $package_hook,
			'Expected init() to hook register_abilities on either `abilities_api_init` or `wp_abilities_api_init`.'
		);
	}

	public function test_current_user_can_manage_woocommerce_matches_capability() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			AbilitiesRegistrar::current_user_can_manage_woocommerce(),
			'Subscribers must not pass the manage_woocommerce capability check.'
		);

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertTrue(
			AbilitiesRegistrar::current_user_can_manage_woocommerce(),
			'Administrators must pass the manage_woocommerce capability check.'
		);
	}

	public function test_get_account_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WordPress version.' );
		}

		add_filter( self::FEATURE_FILTER, '__return_true' );
		AbilitiesRegistrar::init();

		// Categories and abilities can only be registered from inside the
		// abilities-init action callbacks. The actions fire lazily on first
		// access of the registry singleton — wp_get_abilities() is the
		// shortest path to trigger both initializations.
		wp_get_abilities();

		$ability = wp_get_ability( 'woocommerce-payments/get-account' );
		$this->assertNotNull( $ability, 'woocommerce-payments/get-account should be registered.' );
		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertIsArray( $meta );
		$this->assertArrayHasKey( 'annotations', $meta );

		$annotations = $meta['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-account should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-account should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-account should be idempotent.' );
		$this->assertTrue(
			$meta['show_in_rest'] ?? false,
			'get-account must be exposed via show_in_rest for the REST bridge.'
		);
		$this->assertTrue(
			$meta['mcp']['public'] ?? false,
			'get-account must be opted into MCP discovery via meta.mcp.public.'
		);
	}

	/**
	 * @dataProvider provide_expected_abilities
	 */
	public function test_all_read_abilities_are_registered_with_read_only_shape( string $ability_name ) {
		if ( ! function_exists( 'wp_get_ability' ) || ! function_exists( 'wp_get_abilities' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WordPress version.' );
		}

		add_filter( self::FEATURE_FILTER, '__return_true' );
		AbilitiesRegistrar::init();
		wp_get_abilities();

		$ability = wp_get_ability( $ability_name );
		$this->assertNotNull( $ability, $ability_name . ' should be registered.' );
		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertTrue( $meta['annotations']['readonly'], $ability_name . ' must be readonly.' );
		$this->assertFalse( $meta['annotations']['destructive'], $ability_name . ' must not be destructive.' );
		$this->assertTrue( $meta['annotations']['idempotent'], $ability_name . ' must be idempotent.' );
		$this->assertTrue( $meta['show_in_rest'] ?? false, $ability_name . ' must opt into show_in_rest.' );
		$this->assertTrue( $meta['mcp']['public'] ?? false, $ability_name . ' must opt into MCP discovery.' );
	}

	public function provide_expected_abilities(): array {
		return [
			[ 'woocommerce-payments/get-account' ],
			[ 'woocommerce-payments/get-deposits-overview' ],
			[ 'woocommerce-payments/get-transactions' ],
			[ 'woocommerce-payments/get-transactions-summary' ],
			[ 'woocommerce-payments/get-disputes' ],
			[ 'woocommerce-payments/get-disputes-summary' ],
			[ 'woocommerce-payments/get-dispute' ],
			[ 'woocommerce-payments/get-authorizations' ],
			[ 'woocommerce-payments/get-authorizations-summary' ],
			[ 'woocommerce-payments/get-deposits' ],
			[ 'woocommerce-payments/get-deposits-summary' ],
			[ 'woocommerce-payments/get-payment-intent' ],
			[ 'woocommerce-payments/get-charge' ],
			[ 'woocommerce-payments/get-timeline' ],
			[ 'woocommerce-payments/get-active-loan-summary' ],
		];
	}

	public function test_execute_get_dispute_rejects_missing_id() {
		$result = AbilitiesRegistrar::execute_get_dispute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_get_payment_intent_rejects_missing_id() {
		$result = AbilitiesRegistrar::execute_get_payment_intent( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_payment_intent_id', $result->get_error_code() );
	}

	public function test_execute_get_charge_rejects_missing_id() {
		$result = AbilitiesRegistrar::execute_get_charge( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_charge_id', $result->get_error_code() );
	}

	public function test_execute_get_timeline_rejects_missing_id() {
		$result = AbilitiesRegistrar::execute_get_timeline( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_intention_id', $result->get_error_code() );
	}

	/**
	 * Exercise each ability's `execute_*` callback so the body, the
	 * `delegate_to_rest_controller()` helper, and the `is_error()` /
	 * `WP_REST_Response` / raw-array unwrap branches all run. The unit
	 * environment has no live platform API, so we only assert the call
	 * returns array|\WP_Error without fataling — coverage of the
	 * dispatch path is the point.
	 *
	 * @dataProvider provide_execute_cases
	 */
	public function test_execute_callback_returns_array_or_wp_error( string $method, $input ) {
		$result = AbilitiesRegistrar::$method( $input );
		$this->assertTrue(
			is_array( $result ) || is_wp_error( $result ),
			$method . ' must return either an array or a WP_Error; got ' . gettype( $result )
		);
	}

	public function provide_execute_cases(): array {
		return [
			'get-account'                => [ 'execute_get_account', null ],
			'get-deposits-overview'      => [ 'execute_get_deposits_overview', null ],
			'get-transactions'           => [ 'execute_get_transactions', [ 'per_page' => 5 ] ],
			'get-transactions-summary'   => [ 'execute_get_transactions_summary', [] ],
			'get-disputes'               => [ 'execute_get_disputes', [ 'per_page' => 5 ] ],
			'get-disputes-summary'       => [ 'execute_get_disputes_summary', [] ],
			'get-dispute'                => [ 'execute_get_dispute', [ 'dispute_id' => 'du_test_invalid' ] ],
			'get-authorizations'         => [ 'execute_get_authorizations', [ 'per_page' => 5 ] ],
			'get-authorizations-summary' => [ 'execute_get_authorizations_summary', null ],
			'get-deposits'               => [ 'execute_get_deposits', [ 'per_page' => 5 ] ],
			'get-deposits-summary'       => [ 'execute_get_deposits_summary', [] ],
			'get-payment-intent'         => [ 'execute_get_payment_intent', [ 'payment_intent_id' => 'pi_test_invalid' ] ],
			'get-charge'                 => [ 'execute_get_charge', [ 'charge_id' => 'ch_test_invalid' ] ],
			'get-timeline'               => [ 'execute_get_timeline', [ 'intention_id' => 'pi_test_invalid' ] ],
			'get-active-loan-summary'    => [ 'execute_get_active_loan_summary', null ],
		];
	}

	public function test_register_category_short_circuits_when_static_flag_set() {
		$this->set_static_flag( 'category_registered', true );

		// With the flag pre-set, the call must short-circuit at the
		// static-flag guard before any abilities-API method runs.
		AbilitiesRegistrar::register_category();

		$this->assertTrue( true );
	}

	public function test_register_abilities_short_circuits_when_static_flag_set() {
		$this->set_static_flag( 'abilities_registered', true );

		AbilitiesRegistrar::register_abilities();

		$this->assertTrue( true );
	}

	public function test_init_takes_immediate_call_branch_when_actions_have_fired() {
		// `did_action()` is process-wide. If a prior test in this suite ran
		// `wp_get_abilities()` (which fires `wp_abilities_api_*_init`), the
		// counter is > 0 and init() takes the immediate-call branch. Force
		// it here so this test does not depend on suite ordering.
		if ( function_exists( 'wp_get_abilities' ) ) {
			wp_get_abilities();
		}

		// Pre-set both static flags so the immediate `self::register_category()`
		// and `self::register_abilities()` calls short-circuit cleanly — we
		// don't want the test to trigger `_doing_it_wrong` from calling the
		// abilities-API registrars outside their action callbacks.
		$this->set_static_flag( 'category_registered', true );
		$this->set_static_flag( 'abilities_registered', true );

		add_filter( self::FEATURE_FILTER, '__return_true' );
		AbilitiesRegistrar::init();

		$this->assertTrue( true );
	}

	/**
	 * Set a private static flag on AbilitiesRegistrar via Reflection so
	 * tests can exercise the static-guard branches without triggering the
	 * upstream WP_Abilities_Registry / WP_Ability_Categories_Registry
	 * `_doing_it_wrong` checks.
	 */
	private function set_static_flag( string $name, bool $value ): void {
		$reflection = new \ReflectionClass( AbilitiesRegistrar::class );
		$property   = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	public function test_delegate_unwraps_successful_wp_rest_response() {
		// rest_pre_dispatch fires before route dispatch and short-circuits the
		// pipeline if it returns a non-null value. Returning a WP_REST_Response
		// here exercises delegate_to_rest_controller's success-unwrap branch
		// (`get_data()` + `is_array` check) without needing the platform API.
		$filter = function ( $result, $server, $request ) {
			if ( strpos( $request->get_route(), '/wc/v3/payments/transactions' ) === 0 ) {
				return new \WP_REST_Response( [ 'data' => 'fake-success' ], 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = AbilitiesRegistrar::execute_get_transactions( [ 'per_page' => 5 ] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertIsArray( $result );
		$this->assertSame( [ 'data' => 'fake-success' ], $result );
	}

	public function test_delegate_returns_wp_error_for_error_wp_rest_response() {
		// Returning an error-status WP_REST_Response exercises
		// delegate_to_rest_controller's `$response->is_error()` →
		// `$response->as_error()` branch.
		$filter = function ( $result, $server, $request ) {
			if ( strpos( $request->get_route(), '/wc/v3/payments/transactions' ) === 0 ) {
				return new \WP_REST_Response(
					[
						'code'    => 'fake_error',
						'message' => 'Intentional test failure',
						'data'    => [ 'status' => 400 ],
					],
					400
				);
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = AbilitiesRegistrar::execute_get_transactions( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
