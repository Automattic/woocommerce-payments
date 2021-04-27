<?php
/**
 * Class WC_Payments_Admin_Sections_Overwrite_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Admin_Sections_Overwrite unit tests.
 */
class WC_Payments_Admin_Sections_Overwrite_Test extends WP_UnitTestCase {

	/**
	 * @var stdClass|MockObject
	 */
	private $current_section_validator;

	public function test_current_section_defaults_to_woocommerce_payments_if_null_on_page_init() {
		global $current_section, $current_tab, $plugin_page;

		$current_tab         = 'checkout';
		$plugin_page         = 'wc-settings';
		$_REQUEST['section'] = null;

		$overwrite = new WC_Payments_Admin_Sections_Overwrite();
		$overwrite->page_init();

		$this->assertEquals( 'woocommerce_payments', $current_section );
	}

	/**
	 * @dataProvider params_not_causing_current_section_overwriting_on_page_init_provider
	 */
	public function test_current_section_is_not_modified_on_page_init( string $section = null, string $tab, string $page ) {
		global $current_section, $current_tab, $plugin_page;

		$current_tab         = $tab;
		$plugin_page         = $page;
		$_REQUEST['section'] = $section;
		$current_section     = $section;

		$overwrite = new WC_Payments_Admin_Sections_Overwrite();
		$overwrite->page_init();

		$this->assertEquals( $section, $current_section );
	}

	public function test_checkout_sections_are_modified() {
		$sections = [
			'' => 'Payment gateways',
		];

		$expected_sections = [
			'woocommerce_payments' => 'WooCommerce Payments',
			''                     => 'All payment methods',
		];

		$overwrite = new WC_Payments_Admin_Sections_Overwrite();

		$this->assertEquals(
			$expected_sections,
			$overwrite->add_checkout_sections( $sections )
		);
	}

	public function test_current_section_is_overwritten_in_section_list_if_prefixed_with_woocommerce_payments() {
		global $current_section, $current_tab;

		$current_section = 'woocommerce_payments_foo';
		$current_tab     = 'checkout';

		$this->check_current_section_is_overwritten_in_section_list( 'woocommerce_payments' );
	}

	public function test_current_section_is_not_overwritten_in_section_list_if_not_prefixed_with_woocommerce_payments() {
		global $current_section, $current_tab;

		$current_section = 'foo';
		$current_tab     = 'checkout';

		$this->check_current_section_is_overwritten_in_section_list( 'foo' );
	}

	public function test_current_section_is_not_overwritten_in_section_list_if_tab_is_not_checkout() {
		global $current_section, $current_tab;

		$current_section = 'woocommerce_payments_foo';
		$current_tab     = 'bar';

		$this->check_current_section_is_overwritten_in_section_list( 'woocommerce_payments_foo' );
	}

	/**
	 * Runs a test checking $current_section value before overwriting, after overwriting, and after resetting.
	 *
	 * @param string $expected_overwritten_value Value expected after overwriting.
	 */
	private function check_current_section_is_overwritten_in_section_list( string $expected_overwritten_value ) {
		global $current_section;

		$this->current_section_validator = $this->getMockBuilder( stdClass::class )
			->setMethods(
				[
					'check_at_priority_1',
					'check_at_priority_10',
					'check_at_priority_20',
				]
			)
			->getMock();

		$this->check_value_at_priority( 1, $current_section );
		$this->check_value_at_priority( 10, $expected_overwritten_value );
		$this->check_value_at_priority( 20, $current_section );

		new WC_Payments_Admin_Sections_Overwrite();

		/* Prevent outputting the section list HTML while running the action. */
		ob_start();
		do_action( 'woocommerce_sections_checkout' );
		ob_end_clean();
	}

	/**
	 * Adds an assertion checking $current_section value at a specified priority.
	 *
	 * @param int $priority Priority at which to run the check.
	 * @param string $expected_value Value expected at the specified priority.
	 */
	private function check_value_at_priority( int $priority, string $expected_value ) {
		$this->current_section_validator
			->expects( $this->once() )
			->method( 'check_at_priority_' . $priority )
			->willReturnCallback(
				function () use ( $expected_value ) {
					global $current_section;

					$this->assertEquals( $expected_value, $current_section );
				}
			);

		add_action(
			'woocommerce_sections_checkout',
			[ $this->current_section_validator, 'check_at_priority_' . $priority ],
			$priority
		);
	}

	public function params_not_causing_current_section_overwriting_on_page_init_provider() {
		return [
			'section is empty string' => [ '', 'checkout', 'wc-settings' ],
			'section is string'       => [ 'foo', 'checkout', 'wc-settings' ],
			'tab is not checkout'     => [ null, 'shipping', 'wc-settings' ],
			'page is not wc-settings' => [ null, 'checkout', 'foo' ],
		];
	}
}
