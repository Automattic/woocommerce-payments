<?php
/**
 * Tests for invalidating shipping rates cached before the update.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace unit\migrations;

use WCPay\Migrations\Invalidate_Cached_Shipping_Rates;
use WCPAY_UnitTestCase;

/**
 * Checks the shipping cache version that WooCommerce adds to every package hash.
 */
class Invalidate_Cached_Shipping_Rates_Test extends WCPAY_UnitTestCase {

	const CACHED_VERSION = '1700000000.000000';

	public function set_up(): void {
		parent::set_up();
		set_transient( 'shipping-transient-version', self::CACHED_VERSION );
	}

	public function tear_down(): void {
		delete_transient( 'shipping-transient-version' );
		parent::tear_down();
	}

	/**
	 * @dataProvider provider_versions_before_11_3_0
	 */
	public function test_update_from_before_11_3_0_invalidates_cached_rates( string $previous_version ): void {
		update_option( 'woocommerce_woocommerce_payments_version', $previous_version );

		( new Invalidate_Cached_Shipping_Rates() )->maybe_migrate();

		$this->assertNotSame( '1700000000.000000', \WC_Cache_Helper::get_transient_version( 'shipping' ) );
	}

	public function provider_versions_before_11_3_0(): array {
		return [
			'upgrade from 11.2.0' => [ '11.2.0' ],
			'fresh install'       => [ '' ],
		];
	}

	public function test_update_from_11_3_0_keeps_cached_rates(): void {
		update_option( 'woocommerce_woocommerce_payments_version', '11.3.0' );

		( new Invalidate_Cached_Shipping_Rates() )->maybe_migrate();

		$this->assertSame( '1700000000.000000', \WC_Cache_Helper::get_transient_version( 'shipping' ) );
	}

	public function test_plugin_upgrade_hook_invalidates_cached_rates(): void {
		update_option( 'woocommerce_woocommerce_payments_version', '11.0.0' );

		\WC_Payments::install_actions();

		$this->assertNotSame( '1700000000.000000', \WC_Cache_Helper::get_transient_version( 'shipping' ) );
	}
}
