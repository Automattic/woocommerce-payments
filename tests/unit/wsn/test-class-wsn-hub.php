<?php
/**
 * Class WSN_Hub_Test
 *
 * @package WooCommerce\Payments\WSN
 */

/**
 * Integration tests for WSN_Hub::init_hooks() wire-up.
 *
 * The unit tests for each WSN component (settings controller, emitter,
 * composer, etc.) cover the components in isolation. This test class
 * covers the WIRE-UP: when init_hooks() runs, does the right set of
 * listeners get registered for each feature-flag combination?
 *
 * A wire-up regression (wrong service injection, missing require_once,
 * sub-flag check inverted) would surface as a runtime fatal in production
 * because none of the per-component test files exercise the full
 * instantiation chain through `new WSN_Hub()`.
 */
class WSN_Hub_Test extends WCPAY_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Clear any listeners left by other test files so our before/after
		// has_action checks are sensitive only to what init_hooks() did.
		remove_all_actions( 'wcpay_wsn_profile_changed' );
		remove_all_actions( 'wcpay_woopay_appearance_changed' );

		delete_option( WC_Payments_Features::WSN_PROFILE_EMITTER_FLAG_NAME );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_BACKSTOP_SCHEDULED );
	}

	public function tear_down() {
		remove_all_actions( 'wcpay_wsn_profile_changed' );
		remove_all_actions( 'wcpay_woopay_appearance_changed' );

		delete_option( WC_Payments_Features::WSN_PROFILE_EMITTER_FLAG_NAME );
		delete_transient( WSN_Profile_Emitter::TRANSIENT_BACKSTOP_SCHEDULED );

		parent::tear_down();
	}

	public function test_init_hooks_does_not_register_emitter_when_sub_flag_off() {
		// Default: sub-flag absent. Emitter should NOT be booted.
		( new WSN_Hub() )->init_hooks();

		$this->assertFalse(
			has_action( 'wcpay_wsn_profile_changed' ),
			'No emitter listener should be registered when the sub-flag is off.'
		);
		$this->assertFalse(
			has_action( 'wcpay_woopay_appearance_changed' ),
			'No emitter listener should be registered when the sub-flag is off.'
		);
	}

	public function test_init_hooks_registers_emitter_listeners_when_sub_flag_on() {
		update_option( WC_Payments_Features::WSN_PROFILE_EMITTER_FLAG_NAME, '1' );

		( new WSN_Hub() )->init_hooks();

		$this->assertNotFalse(
			has_action( 'wcpay_wsn_profile_changed' ),
			'Sub-flag ON: emitter must register the profile-changed listener so settings PUTs trigger a push.'
		);
		$this->assertNotFalse(
			has_action( 'wcpay_woopay_appearance_changed' ),
			'Sub-flag ON: emitter must register the appearance-changed listener so theme/style changes trigger a push.'
		);
	}

	public function test_init_hooks_always_registers_admin_menu_and_assets_listeners() {
		// These are not gated on the emitter sub-flag — they should fire
		// regardless of whether the Profile push is enabled.
		( new WSN_Hub() )->init_hooks();

		$this->assertNotFalse( has_action( 'admin_menu' ) );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts' ) );
		$this->assertNotFalse( has_action( 'rest_api_init' ) );
		$this->assertNotFalse( has_action( 'in_admin_header' ) );
	}

	public function test_emitter_option_write_listener_registered_on_non_admin_request() {
		// Regression guard: emitter listeners that respond to option writes and
		// shipping-zone changes must be registered on EVERY request type (REST,
		// frontend, AS workers) — not only when the Hub admin page is loaded.
		//
		// This test simulates what happens when `WC_Payments::init()` calls
		// `WSN_Hub::init_hooks()` during a non-admin bootstrap by ensuring the
		// `updated_option` and `woocommerce_after_shipping_zone_object_save`
		// listeners land when the sub-flag is on, irrespective of `is_admin()`.
		update_option( WC_Payments_Features::WSN_PROFILE_EMITTER_FLAG_NAME, '1' );

		remove_all_actions( 'updated_option' );
		remove_all_actions( 'woocommerce_after_shipping_zone_object_save' );

		( new WSN_Hub() )->init_hooks();

		$this->assertNotFalse(
			has_action( 'updated_option' ),
			'Emitter must listen on updated_option on every request type so option writes outside the Hub admin page still trigger a sync.'
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_after_shipping_zone_object_save' ),
			'Emitter must listen on shipping-zone changes on every request type (AS workers handle zone-save hooks, not just admin pages).'
		);

		remove_all_actions( 'updated_option' );
		remove_all_actions( 'woocommerce_after_shipping_zone_object_save' );
	}
}
