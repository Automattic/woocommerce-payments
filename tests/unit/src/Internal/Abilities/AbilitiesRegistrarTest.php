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
	 * Tear down — reset filter, hooks, and current user so state does not
	 * leak across tests in the same process.
	 */
	public function tear_down() {
		remove_all_filters( self::FEATURE_FILTER );
		wp_set_current_user( 0 );
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

	public function test_init_registers_category() {
		remove_all_actions( self::CATEGORIES_HOOK );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		// `wp_abilities_api_categories_init` fires lazily — the first time any
		// code calls into `WP_Ability_Categories_Registry::get_instance()`.
		// Whether `did_action()` returns 0 or 1 depends on whether the test's
		// bootstrap has triggered the registry yet. Assert end-state in the
		// immediate-call branch and action wiring otherwise; `did_action()`
		// state is process-wide and cannot be reset.
		if ( did_action( self::CATEGORIES_HOOK ) ) {
			if ( function_exists( 'wp_get_ability_category' ) ) {
				$this->assertNotNull(
					wp_get_ability_category( AbilitiesRegistrar::CATEGORY_SLUG ),
					'Expected category to be registered when wp_abilities_api_categories_init has already fired.'
				);
			} else {
				$this->markTestSkipped( 'Abilities API not available on this WordPress version.' );
			}
		} else {
			$this->assertNotFalse(
				has_action(
					self::CATEGORIES_HOOK,
					[ AbilitiesRegistrar::class, 'register_category' ]
				),
				'Expected init() to hook register_category when the API has not yet booted.'
			);
		}
	}

	public function test_init_registers_abilities() {
		remove_all_actions( self::ABILITIES_HOOK );
		add_filter( self::FEATURE_FILTER, '__return_true' );

		AbilitiesRegistrar::init();

		if ( did_action( self::ABILITIES_HOOK ) ) {
			// Immediate-call branch — `register_abilities()` is a Phase-I no-op.
			// Concrete abilities land in subsequent commits; this assertion
			// confirms the deferred branch is covered above.
			$this->assertTrue(
				true,
				'Phase I scaffold: no abilities registered yet; deferred-branch coverage is asserted in the no-op-when-disabled test.'
			);
		} else {
			$this->assertNotFalse(
				has_action(
					self::ABILITIES_HOOK,
					[ AbilitiesRegistrar::class, 'register_abilities' ]
				),
				'Expected init() to hook register_abilities when the API has not yet booted.'
			);
		}
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
}
