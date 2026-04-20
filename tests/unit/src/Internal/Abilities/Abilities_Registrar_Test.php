<?php
/**
 * Class Abilities_Registrar_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Abilities_Registrar;

/**
 * Tests for the Abilities_Registrar scaffold.
 */
class Abilities_Registrar_Test extends WCPAY_UnitTestCase {

	/**
	 * Abilities API category init hook name.
	 *
	 * @var string
	 */
	const CATEGORIES_HOOK = 'wp_abilities_api_categories_init';

	/**
	 * Abilities API init hook name.
	 *
	 * @var string
	 */
	const ABILITIES_HOOK = 'wp_abilities_api_init';

	/**
	 * Ensures ::init() registers the category-registration callback on the
	 * Abilities API category init hook.
	 */
	public function test_init_hooks_category_registration() {
		remove_all_actions( self::CATEGORIES_HOOK );
		remove_all_actions( self::ABILITIES_HOOK );

		Abilities_Registrar::init();

		$this->assertNotFalse(
			has_action(
				self::CATEGORIES_HOOK,
				[ Abilities_Registrar::class, 'register_category' ]
			),
			'Expected Abilities_Registrar::init() to hook register_category on ' . self::CATEGORIES_HOOK
		);
	}

	/**
	 * Ensures ::init() registers the ability-registration callback on the
	 * Abilities API init hook.
	 */
	public function test_init_hooks_abilities_registration() {
		remove_all_actions( self::CATEGORIES_HOOK );
		remove_all_actions( self::ABILITIES_HOOK );

		Abilities_Registrar::init();

		$this->assertNotFalse(
			has_action(
				self::ABILITIES_HOOK,
				[ Abilities_Registrar::class, 'register_abilities' ]
			),
			'Expected Abilities_Registrar::init() to hook register_abilities on ' . self::ABILITIES_HOOK
		);
	}

	/**
	 * Ensures the permission helper mirrors the base REST controller's
	 * manage_woocommerce capability check: subscribers are denied, admins allowed.
	 */
	public function test_can_manage_payments_matches_manage_woocommerce_capability() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			Abilities_Registrar::can_manage_payments(),
			'Subscribers must not pass the manage_woocommerce capability check.'
		);

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertTrue(
			Abilities_Registrar::can_manage_payments(),
			'Administrators must pass the manage_woocommerce capability check.'
		);
	}

	/**
	 * Ensures the woopayments/get-account-status ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct category,
	 * readonly annotation, and show_in_rest exposed.
	 *
	 * End-to-end check: relies on plugin bootstrap (WC_Payments::init() →
	 * Abilities_Registrar::init()) having registered the ability by the time
	 * tests run. Not an isolated unit test of register_abilities().
	 */
	public function test_get_account_status_ability_is_registered_with_expected_shape() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-account-status' );
		$this->assertNotNull(
			$ability,
			'wp_get_ability( "woopayments/get-account-status" ) returned null — ability not registered.'
		);

		$this->assertSame(
			Abilities_Registrar::CATEGORY_SLUG,
			$ability->get_category(),
			'Ability category must be the woopayments category slug.'
		);

		$meta = $ability->get_meta();
		$this->assertIsArray( $meta, 'Ability meta must be an array.' );
		$this->assertArrayHasKey( 'annotations', $meta );
		$this->assertTrue(
			$meta['annotations']['readonly'] ?? false,
			'get-account-status must advertise itself as readonly.'
		);
		$this->assertTrue(
			$meta['show_in_rest'] ?? false,
			'get-account-status must be exposed via show_in_rest for the REST bridge.'
		);
	}

	/**
	 * Ensures the ability's permission callback — shared across every
	 * WooPayments ability — denies non-admins and allows admins. This is the
	 * executable-on-current-user surface that matters for real agents.
	 */
	public function test_get_account_status_permission_requires_manage_woocommerce() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-account-status' );
		$this->assertNotNull( $ability, 'Ability must be registered before checking permissions.' );

		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			Abilities_Registrar::can_manage_payments(),
			'Subscribers must not be able to execute get-account-status.'
		);

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertTrue(
			Abilities_Registrar::can_manage_payments(),
			'Administrators must be able to execute get-account-status.'
		);
	}
}
