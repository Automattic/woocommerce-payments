<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Admin unit tests.
 */
class WC_Payments_Admin_Test extends WP_UnitTestCase {

	public function test_it_returns_is_payment_settings_page_for_main_settings_page() {
		global $current_section, $current_tab;

		$current_section = 'woocommerce_payments';
		$current_tab     = 'checkout';

		$this->assertTrue( WC_Payments_Admin::is_payments_settings_page() );
	}

	public function test_it_returns_is_payment_settings_page_for_payment_method_settings_page() {
		global $current_section, $current_tab;

		$current_section = 'woocommerce_payments_foo';
		$current_tab     = 'checkout';

		$this->assertTrue( WC_Payments_Admin::is_payments_settings_page() );
	}

	/**
	 * @dataProvider not_payment_settings_page_conditions_provider
	 */
	public function test_it_returns_it_is_not_payment_settings_page( $section, $tab ) {
		global $current_section, $current_tab;

		$current_section = $section;
		$current_tab     = $tab;

		$this->assertFalse( WC_Payments_Admin::is_payments_settings_page() );
	}

	public function not_payment_settings_page_conditions_provider(): array {
		return [
			'section is not woocommerce_payments' => [ 'foo', 'checkout' ],
			'tab is not checkout'                 => [ 'woocommerce_payments', 'shipping' ],
		];
	}
}
