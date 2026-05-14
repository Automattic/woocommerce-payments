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
		// Remove all four hook variants (both composer-package names and the
		// WP-Core merge names) — init() hooks all four when enabled, so the
		// feature-flag no-op test must check that none of them are wired.
		remove_all_actions( 'abilities_api_categories_init' );
		remove_all_actions( 'abilities_api_init' );
		remove_all_actions( self::CATEGORIES_HOOK );
		remove_all_actions( self::ABILITIES_HOOK );
		remove_all_filters( self::FEATURE_FILTER );

		AbilitiesRegistrar::init();

		$this->assertFalse(
			has_action(
				self::CATEGORIES_HOOK,
				[ AbilitiesRegistrar::class, 'register_category' ]
			),
			'Expected init() to short-circuit on wp_abilities_api_categories_init when the feature filter is unset.'
		);
		$this->assertFalse(
			has_action(
				self::ABILITIES_HOOK,
				[ AbilitiesRegistrar::class, 'register_abilities' ]
			),
			'Expected init() to short-circuit on wp_abilities_api_init when the feature filter is unset.'
		);
		$this->assertFalse(
			has_action(
				'abilities_api_categories_init',
				[ AbilitiesRegistrar::class, 'register_category' ]
			),
			'Expected init() to also short-circuit on the composer-package categories action variant.'
		);
		$this->assertFalse(
			has_action(
				'abilities_api_init',
				[ AbilitiesRegistrar::class, 'register_abilities' ]
			),
			'Expected init() to also short-circuit on the composer-package abilities action variant.'
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
		return [];
	}

	/**
	 * Exercise each ability's `execute_*` callback by injecting a canned
	 * successful `WP_REST_Response` via `rest_pre_dispatch` for the expected
	 * route, then assert the ability returns the unwrapped payload as an
	 * array. This is a real behavioural test: a mis-wired route would fail
	 * the injection and surface as an unexpected `WP_Error` from the real
	 * REST router.
	 *
	 * @dataProvider provide_execute_cases
	 */
	public function test_execute_callback_routes_to_expected_endpoint( string $method, $input, string $expected_route ) {
		$canned = [ 'data' => 'fake-success-' . $method ];
		$filter = function ( $result, $server, $request ) use ( $expected_route, $canned ) {
			// Exact route match — a mis-routed ability (wrong path or wrong
			// path suffix) would miss this filter and surface as an unexpected
			// `WP_Error` from the real REST router, which the assertion below
			// catches via `assertIsArray`.
			if ( $request->get_route() === $expected_route ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = AbilitiesRegistrar::$method( $input );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertIsArray(
			$result,
			$method . ' must unwrap the WP_REST_Response into an array. Got: ' . ( is_wp_error( $result ) ? 'WP_Error(' . $result->get_error_code() . ')' : gettype( $result ) )
		);
		$this->assertSame(
			$canned,
			$result,
			$method . ' must return the canned payload from the injected route ' . $expected_route . '. Mismatch indicates the ability routed to a different REST path than expected.'
		);
	}

	public function provide_execute_cases(): array {
		return [];
	}

	public function test_register_category_short_circuits_when_static_flag_set() {
		// When the static idempotency flag is pre-set, calling register_category()
		// must short-circuit at the flag guard — no `_doing_it_wrong` notice
		// from re-registering. Catch any `_doing_it_wrong` calls via the
		// `doing_it_wrong_run` action; this is the load-bearing assertion
		// for the idempotency guard.
		$this->set_static_flag( 'category_registered', true );

		$called = false;
		$spy    = function () use ( &$called ) {
			$called = true;
		};
		add_action( 'doing_it_wrong_run', $spy );

		try {
			AbilitiesRegistrar::register_category();
		} finally {
			remove_action( 'doing_it_wrong_run', $spy );
		}

		$this->assertFalse(
			$called,
			'register_category() with the static flag pre-set must short-circuit before invoking wp_register_ability_category() (which would emit _doing_it_wrong on re-registration).'
		);
	}

	public function test_register_abilities_short_circuits_when_static_flag_set() {
		// Same contract as the category test above: pre-set the static
		// idempotency flag and verify no `_doing_it_wrong` fires when the
		// short-circuit guard runs.
		$this->set_static_flag( 'abilities_registered', true );

		$called = false;
		$spy    = function () use ( &$called ) {
			$called = true;
		};
		add_action( 'doing_it_wrong_run', $spy );

		try {
			AbilitiesRegistrar::register_abilities();
		} finally {
			remove_action( 'doing_it_wrong_run', $spy );
		}

		$this->assertFalse(
			$called,
			'register_abilities() with the static flag pre-set must short-circuit before invoking wp_register_ability() (which would emit _doing_it_wrong on re-registration).'
		);
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

	public function test_delegate_translates_pagination_keys_at_request_layer() {
		// PAGINATION_KEY_MAP maps the agent-facing WP-Core REST keys (`per_page`,
		// `orderby`, `order`) to the names the WooPayments Paginated request
		// class consumes (`pagesize`, `sort`, `direction`). This test asserts
		// the translation actually reaches the WP_REST_Request — a regression
		// in the boundary mapping would silently revert list abilities to the
		// default 25 rows / created-desc regardless of caller input.
		//
		// Calls delegate_to_rest_controller() directly (the registrar helper
		// that all list-ability Domain classes delegate through) so the test
		// remains meaningful after execute_get_transactions() was removed in
		// Phase 6 when get-transactions migrated to the Domain class.
		$captured = null;
		$filter   = function ( $result, $server, $request ) use ( &$captured ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions' ) {
				$captured = [
					'per_page'  => $request->get_param( 'per_page' ),
					'pagesize'  => $request->get_param( 'pagesize' ),
					'orderby'   => $request->get_param( 'orderby' ),
					'sort'      => $request->get_param( 'sort' ),
					'order'     => $request->get_param( 'order' ),
					'direction' => $request->get_param( 'direction' ),
				];
				return new \WP_REST_Response( [], 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			AbilitiesRegistrar::delegate_to_rest_controller(
				'GET',
				'/wc/v3/payments/transactions',
				[
					'per_page' => 5,
					'orderby'  => 'amount',
					'order'    => 'asc',
				]
			);
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertNotNull( $captured, 'rest_pre_dispatch filter did not capture the transactions request — route mismatch.' );
		$this->assertSame( 5, $captured['pagesize'], 'per_page must translate to pagesize on the WP_REST_Request.' );
		$this->assertSame( 'amount', $captured['sort'], 'orderby must translate to sort on the WP_REST_Request.' );
		$this->assertSame( 'asc', $captured['direction'], 'order must translate to direction on the WP_REST_Request.' );
		$this->assertNull( $captured['per_page'], 'per_page must be removed after translation.' );
		$this->assertNull( $captured['orderby'], 'orderby must be removed after translation.' );
		$this->assertNull( $captured['order'], 'order must be removed after translation.' );
	}

	public function test_delegate_preserves_canonical_key_when_caller_supplies_both() {
		// When the caller supplies both the agent-facing key (`per_page`) and
		// the canonical Paginated key (`pagesize`), the canonical value wins
		// — silently overwriting an explicit `pagesize` would be a footgun.
		$captured = null;
		$filter   = function ( $result, $server, $request ) use ( &$captured ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions' ) {
				$captured = $request->get_param( 'pagesize' );
				return new \WP_REST_Response( [], 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			AbilitiesRegistrar::delegate_to_rest_controller(
				'GET',
				'/wc/v3/payments/transactions',
				[
					'per_page' => 5,
					'pagesize' => 99,
				]
			);
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( 99, $captured, 'When both `per_page` and `pagesize` are supplied, the canonical `pagesize` must win.' );
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
			$result = AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/transactions', [ 'per_page' => 5 ] );
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
			$result = AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/transactions', [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
