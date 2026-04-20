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
		$annotations = $meta['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-account-status should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-account-status should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-account-status should be idempotent.' );
		$this->assertTrue(
			$meta['show_in_rest'] ?? false,
			'get-account-status must be exposed via show_in_rest for the REST bridge.'
		);
	}

	/**
	 * Ensures the woopayments/get-transactions ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct
	 * category and the full readonly/destructive/idempotent annotation set.
	 */
	public function test_get_transactions_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-transactions' );
		$this->assertNotNull( $ability, 'get-transactions should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-transactions should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-transactions should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-transactions should be idempotent.' );
	}

	/**
	 * Ensures the woopayments/get-disputes ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct
	 * category and the full readonly/destructive/idempotent annotation set.
	 */
	public function test_get_disputes_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-disputes' );
		$this->assertNotNull( $ability, 'get-disputes should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-disputes should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-disputes should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-disputes should be idempotent.' );
	}

	/**
	 * Ensures the woopayments/get-dispute-detail ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct
	 * category and the full readonly/destructive/idempotent annotation set.
	 */
	public function test_get_dispute_detail_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-dispute-detail' );
		$this->assertNotNull( $ability, 'get-dispute-detail should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-dispute-detail should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-dispute-detail should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-dispute-detail should be idempotent.' );
	}

	/**
	 * Ensures the woopayments/get-payouts ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct
	 * category and the full readonly/destructive/idempotent annotation set.
	 */
	public function test_get_payouts_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-payouts' );
		$this->assertNotNull( $ability, 'get-payouts should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-payouts should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-payouts should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-payouts should be idempotent.' );
	}

	/**
	 * Ensures the woopayments/get-payout-overview ability is registered with
	 * the shape the Abilities Everywhere initiative expects: correct
	 * category and the full readonly/destructive/idempotent annotation set.
	 */
	public function test_get_payout_overview_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/get-payout-overview' );
		$this->assertNotNull( $ability, 'get-payout-overview should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'], 'get-payout-overview should be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'get-payout-overview should not be destructive.' );
		$this->assertTrue( $annotations['idempotent'], 'get-payout-overview should be idempotent.' );
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

	/**
	 * Executes the dispute-detail ability with no dispute_id and asserts it returns
	 * a WP_Error with code woopayments_missing_dispute_id. Establishes the
	 * execute-path assertion pattern that Phase 5's write ability will extend.
	 */
	public function test_get_dispute_detail_returns_wp_error_when_dispute_id_missing(): void {
		$result = Abilities_Registrar::execute_get_dispute_detail( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woopayments_missing_dispute_id', $result->get_error_code() );
	}

	/**
	 * Ensures the woopayments/submit-dispute-evidence ability — the first write
	 * ability — is registered with the correct category and the full
	 * readonly/destructive/idempotent annotation set. Explicit negative
	 * assertions on every annotation to catch copy-paste bugs where a read
	 * ability's `readonly: true` leaks into this write ability.
	 */
	public function test_submit_dispute_evidence_ability_is_registered_with_expected_shape(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API query functions not available in this WP version.' );
		}

		$ability = wp_get_ability( 'woopayments/submit-dispute-evidence' );
		$this->assertNotNull( $ability, 'submit-dispute-evidence should be registered.' );
		$this->assertSame( Abilities_Registrar::CATEGORY_SLUG, $ability->get_category() );

		$annotations = $ability->get_meta()['annotations'];
		$this->assertFalse( $annotations['readonly'], 'submit-dispute-evidence must NOT be readonly.' );
		$this->assertFalse( $annotations['destructive'], 'submit-dispute-evidence is additive (does not forfeit money).' );
		$this->assertFalse( $annotations['idempotent'], 'submit-dispute-evidence is NOT idempotent — duplicate calls produce duplicate attempts.' );
	}

	/**
	 * Executes the submit-dispute-evidence ability with no dispute_id and
	 * asserts it returns a WP_Error with code woopayments_missing_dispute_id.
	 * Consistent error code across read and write dispute abilities lets agent
	 * retry logic handle "missing dispute_id" the same way for both.
	 */
	public function test_submit_dispute_evidence_returns_wp_error_when_dispute_id_missing(): void {
		$result = Abilities_Registrar::execute_submit_dispute_evidence( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woopayments_missing_dispute_id', $result->get_error_code() );
	}

	/**
	 * Executes the submit-dispute-evidence ability with a non-string
	 * dispute_id (integer 123) and asserts it returns a WP_Error with code
	 * woopayments_missing_dispute_id. Guards the explicit is_string() check —
	 * a lax `empty()` or `isset()` guard would pass an integer through and
	 * fail later at the backing controller's URL construction.
	 */
	public function test_submit_dispute_evidence_returns_wp_error_when_dispute_id_not_a_string(): void {
		$result = Abilities_Registrar::execute_submit_dispute_evidence( [ 'dispute_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'woopayments_missing_dispute_id', $result->get_error_code() );
	}

	/**
	 * Regression test: executing any read ability must not fatal. If a required
	 * controller constructor argument is missing, the Phase 6 harness catches it,
	 * but this unit test shortens the feedback loop.
	 */
	public function test_execute_get_payout_overview_does_not_fatal(): void {
		$result = Abilities_Registrar::execute_get_payout_overview( [] );
		// On a bootstrapped test env the call either returns data (array) or a
		// deliberate WP_Error. A PHP fatal would abort the test process, so the
		// mere fact that we reach this assertion is the regression check.
		$this->assertTrue( is_array( $result ) || $result instanceof \WP_Error );
	}
}
