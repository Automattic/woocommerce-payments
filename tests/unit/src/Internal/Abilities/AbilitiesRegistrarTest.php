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

	const FEATURE_FILTER = 'woocommerce_payments_abilities_enabled';
	const READ_FILTER    = 'woocommerce_payments_read_abilities_enabled';
	const WRITE_FILTER   = 'woocommerce_payments_write_abilities_enabled';

	/**
	 * Reset filters and the current user between tests, then restore the
	 * default registrar state. The upstream `WP_Abilities_Registry`
	 * singleton keeps registrations across tests, so ability-presence
	 * assertions are suite post-conditions, not per-test.
	 */
	public function tear_down(): void {
		remove_all_filters( self::FEATURE_FILTER );
		remove_all_filters( self::READ_FILTER );
		remove_all_filters( self::WRITE_FILTER );
		remove_filter( 'woocommerce_ability_definition_classes', [ AbilitiesRegistrar::class, 'append_classes' ] );
		AbilitiesRegistrar::init();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_init_registers_only_read_abilities_by_default(): void {
		AbilitiesRegistrar::init();

		$names = $this->get_enabled_ability_names();
		$this->assertCount( 18, $names );
		$this->assertContains( 'woocommerce-payments/get-transactions', $names );
		$this->assertNotContains( 'woocommerce-payments/refund-charge', $names );
		$this->assertNotContains( 'woocommerce-payments/submit-dispute-evidence', $names );
		$this->assertNotContains( 'woocommerce-payments/accept-dispute', $names );
		$this->assertNotContains( 'woocommerce-payments/upload-dispute-evidence-file', $names );
	}

	public function test_init_wires_filter_by_default_when_loader_present(): void {
		if ( ! class_exists( '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilitiesLoader required.' );
		}

		remove_filter( 'woocommerce_ability_definition_classes', [ AbilitiesRegistrar::class, 'append_classes' ] );
		AbilitiesRegistrar::init();

		$this->assertNotFalse(
			has_filter( 'woocommerce_ability_definition_classes', [ AbilitiesRegistrar::class, 'append_classes' ] )
		);
	}

	public function test_write_filter_adds_write_abilities(): void {
		add_filter( self::WRITE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		$names = $this->get_enabled_ability_names();
		$this->assertCount( 22, $names );
		$this->assertContains( 'woocommerce-payments/get-transactions', $names );
		$this->assertContains( 'woocommerce-payments/refund-charge', $names );
		$this->assertContains( 'woocommerce-payments/submit-dispute-evidence', $names );
		$this->assertContains( 'woocommerce-payments/accept-dispute', $names );
		$this->assertContains( 'woocommerce-payments/upload-dispute-evidence-file', $names );
	}

	public function test_read_filter_disables_read_abilities(): void {
		add_filter( self::READ_FILTER, '__return_false' );
		remove_filter( 'woocommerce_ability_definition_classes', [ AbilitiesRegistrar::class, 'append_classes' ] );

		AbilitiesRegistrar::init();

		$this->assertSame( [], AbilitiesRegistrar::append_classes( [] ) );
		$this->assertFalse(
			has_filter( 'woocommerce_ability_definition_classes', [ AbilitiesRegistrar::class, 'append_classes' ] ),
			'Expected init() to skip the WC 10.9 filter wire when every ability is disabled.'
		);
	}

	public function test_read_filter_off_and_write_filter_on_registers_only_write_abilities(): void {
		add_filter( self::READ_FILTER, '__return_false' );
		add_filter( self::WRITE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		$names = $this->get_enabled_ability_names();
		$this->assertCount( 4, $names );
		$this->assertNotContains( 'woocommerce-payments/get-transactions', $names );
		$this->assertContains( 'woocommerce-payments/refund-charge', $names );
	}

	public function test_legacy_filter_true_enables_read_and_write_abilities(): void {
		$this->setExpectedDeprecated( self::FEATURE_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		$names = $this->get_enabled_ability_names();
		$this->assertCount( 22, $names );
		$this->assertContains( 'woocommerce-payments/get-transactions', $names );
		$this->assertContains( 'woocommerce-payments/refund-charge', $names );
	}

	/**
	 * The legacy filter enabled abilities for any value other than `false`.
	 */
	public function test_legacy_filter_non_false_value_enables_read_and_write_abilities(): void {
		$this->setExpectedDeprecated( self::FEATURE_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_zero' );

		AbilitiesRegistrar::init();

		$this->assertCount( 22, $this->get_enabled_ability_names() );
	}

	public function test_legacy_filter_false_disables_all_abilities(): void {
		$this->setExpectedDeprecated( self::FEATURE_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_false' );

		AbilitiesRegistrar::init();

		$this->assertSame( [], AbilitiesRegistrar::append_classes( [] ) );
	}

	public function test_legacy_filter_receives_false_as_default(): void {
		$this->setExpectedDeprecated( self::FEATURE_FILTER );
		$received = null;
		add_filter(
			self::FEATURE_FILTER,
			function ( $enabled ) use ( &$received ) {
				$received = $enabled;
				return $enabled;
			}
		);

		AbilitiesRegistrar::init();

		$this->assertFalse( $received );
	}

	public function test_new_filters_receive_legacy_result_and_can_override_it(): void {
		$this->setExpectedDeprecated( self::FEATURE_FILTER );
		add_filter( self::FEATURE_FILTER, '__return_true' );
		$received_read  = null;
		$received_write = null;
		add_filter(
			self::READ_FILTER,
			function ( $enabled ) use ( &$received_read ) {
				$received_read = $enabled;
				return $enabled;
			}
		);
		add_filter(
			self::WRITE_FILTER,
			function ( $enabled ) use ( &$received_write ) {
				$received_write = $enabled;
				return false;
			}
		);

		AbilitiesRegistrar::init();

		$this->assertTrue( $received_read );
		$this->assertTrue( $received_write );
		$names = $this->get_enabled_ability_names();
		$this->assertCount( 18, $names );
		$this->assertNotContains( 'woocommerce-payments/refund-charge', $names );
	}

	public function test_new_filters_receive_defaults_without_legacy_filter(): void {
		$received_read  = null;
		$received_write = null;
		add_filter(
			self::READ_FILTER,
			function ( $enabled ) use ( &$received_read ) {
				$received_read = $enabled;
				return $enabled;
			}
		);
		add_filter(
			self::WRITE_FILTER,
			function ( $enabled ) use ( &$received_write ) {
				$received_write = $enabled;
				return $enabled;
			}
		);

		AbilitiesRegistrar::init();

		$this->assertTrue( $received_read );
		$this->assertFalse( $received_write );
	}

	/**
	 * Filter callbacks can return anything; non-boolean-like values fall
	 * back to the default rather than enabling write abilities.
	 */
	public function test_non_boolean_like_write_filter_value_keeps_default(): void {
		add_filter(
			self::WRITE_FILTER,
			function () {
				return [ 'yes' ];
			}
		);

		AbilitiesRegistrar::init();

		$this->assertNotContains( 'woocommerce-payments/refund-charge', $this->get_enabled_ability_names() );
	}

	public function test_boolean_like_string_write_filter_value_is_honored(): void {
		add_filter(
			self::WRITE_FILTER,
			function () {
				return 'no';
			}
		);

		AbilitiesRegistrar::init();

		$this->assertNotContains( 'woocommerce-payments/refund-charge', $this->get_enabled_ability_names() );
	}

	public function test_current_user_can_manage_woocommerce_matches_capability(): void {
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
	 * Asserts the pagination/sort key translation reaches the dispatched
	 * `WP_REST_Request`. A regression here would silently revert list
	 * abilities to the controller's default page size / sort order.
	 */
	public function test_delegate_translates_pagination_keys_at_request_layer(): void {
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

	/**
	 * When the caller supplies both the agent-facing key (`per_page`) and
	 * the canonical Paginated key (`pagesize`), the canonical value wins.
	 */
	public function test_delegate_preserves_canonical_key_when_caller_supplies_both(): void {
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

	/**
	 * Exercises the success-unwrap branch: a `WP_REST_Response` returned by
	 * `rest_pre_dispatch` should reach `delegate_to_rest_controller()` and
	 * be unwrapped to its data array.
	 */
	public function test_delegate_unwraps_successful_wp_rest_response(): void {
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

	/**
	 * Error-status `WP_REST_Response` should be converted to `WP_Error` via
	 * `is_error()` → `as_error()` so callers can use `is_wp_error()`.
	 */
	public function test_delegate_returns_wp_error_for_error_wp_rest_response(): void {
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
		$this->assertSame( 'fake_error', $result->get_error_code() );
	}

	// -------------------------------------------------------------------------
	// Coordinator-level tests (Phase 8)
	// -------------------------------------------------------------------------

	public function test_append_classes_returns_all_ability_classes_when_writes_enabled(): void {
		add_filter( self::WRITE_FILTER, '__return_true' );
		AbilitiesRegistrar::init();

		$expected = $this->get_all_ability_classes();
		$this->assertCount( 22, $expected, 'READ_ABILITY_CLASSES and WRITE_ABILITY_CLASSES should contain all registered abilities.' );

		// Empty input → returns just the WCPay classes.
		$result = AbilitiesRegistrar::append_classes( [] );
		$this->assertSame( $expected, $result );

		// Merging onto a non-empty caller list.
		$caller = [ '\\Some\\Other\\Class' ];
		$result = AbilitiesRegistrar::append_classes( $caller );
		$this->assertSame( '\\Some\\Other\\Class', $result[0] );
		$this->assertCount( 23, $result );
	}

	/**
	 * Guards the read/write split: a write ability added to the read list
	 * would be registered by default.
	 */
	public function test_ability_lists_match_readonly_annotations(): void {
		if ( ! interface_exists( '\\Automattic\\WooCommerce\\Abilities\\AbilityDefinition' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilityDefinition interface required.' );
		}

		foreach ( $this->get_ability_class_constant( 'READ_ABILITY_CLASSES' ) as $class ) {
			$args = $class::get_registration_args();
			$this->assertTrue( $args['meta']['annotations']['readonly'] ?? null, "$class is in READ_ABILITY_CLASSES but is not annotated readonly." );
		}

		foreach ( $this->get_ability_class_constant( 'WRITE_ABILITY_CLASSES' ) as $class ) {
			$args = $class::get_registration_args();
			$this->assertFalse( $args['meta']['annotations']['readonly'] ?? null, "$class is in WRITE_ABILITY_CLASSES but is not annotated as non-readonly." );
		}
	}

	public function test_every_ability_class_implements_ability_definition(): void {
		if ( ! interface_exists( '\\Automattic\\WooCommerce\\Abilities\\AbilityDefinition' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilityDefinition interface required.' );
		}

		$classes = $this->get_all_ability_classes();

		foreach ( $classes as $class ) {
			$this->assertContains(
				'Automattic\\WooCommerce\\Abilities\\AbilityDefinition',
				class_implements( $class ),
				"$class should implement AbilityDefinition."
			);
		}
	}

	public function test_every_ability_class_has_a_well_formed_slug(): void {
		if ( ! interface_exists( '\\Automattic\\WooCommerce\\Abilities\\AbilityDefinition' ) ) {
			$this->markTestSkipped( 'WooCommerce 10.9 AbilityDefinition interface required.' );
		}

		$classes = $this->get_all_ability_classes();

		foreach ( $classes as $class ) {
			$this->assertTrue( method_exists( $class, 'get_name' ), "$class should define get_name()." );
			$name = $class::get_name();
			// Namespace (not category) — plugin ownership lives in the ability
			// name's leading prefix. Category is the broader `woocommerce`
			// discoverability bucket and would not match here.
			$this->assertStringStartsWith( 'woocommerce-payments/', $name );
		}
	}

	/**
	 * Names of the abilities `init()` enabled, via the loader filter callback.
	 *
	 * @return string[]
	 */
	private function get_enabled_ability_names(): array {
		return array_map(
			function ( $ability_class ) {
				return $ability_class::get_name();
			},
			AbilitiesRegistrar::append_classes( [] )
		);
	}

	/**
	 * @return string[]
	 */
	private function get_all_ability_classes(): array {
		return array_merge(
			$this->get_ability_class_constant( 'READ_ABILITY_CLASSES' ),
			$this->get_ability_class_constant( 'WRITE_ABILITY_CLASSES' )
		);
	}

	/**
	 * @param string $name Private constant name on AbilitiesRegistrar.
	 * @return string[]
	 */
	private function get_ability_class_constant( string $name ): array {
		return ( new \ReflectionClass( AbilitiesRegistrar::class ) )->getReflectionConstant( $name )->getValue();
	}
}
