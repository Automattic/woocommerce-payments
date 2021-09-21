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
	 * @var string
	 */
	const PAYMENTS_TAB_PATH = 'admin.php?page=wc-settings&tab=checkout';

	/**
	 * @var stdClass|MockObject
	 */
	private $current_section_validator;

	/**
	 * @var WC_Payments_Account
	 */
	private $account_service;

	public function setUp() {
		parent::setUp();

		$this->account_service = $this->getMockBuilder( WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'get_cached_account_data' ] )->getMock();
	}

	public function test_payments_tab_url_is_overwritten_when_account_connected() {
		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service ); // Instantiate to add hook callbacks.

		do_action( 'woocommerce_settings_start' );
		$url = admin_url( self::PAYMENTS_TAB_PATH );
		do_action( 'woocommerce_settings_tabs' );

		$this->assertEquals(
			'http://example.org/wp-admin/' . self::PAYMENTS_TAB_PATH . '&section=woocommerce_payments',
			$url
		);
	}

	public function test_payments_tab_url_not_is_overwritten_when_account_disconnected() {
		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service ); // Instantiate to add hook callbacks.

		do_action( 'woocommerce_settings_start' );
		$url = admin_url( self::PAYMENTS_TAB_PATH );
		do_action( 'woocommerce_settings_tabs' );

		$this->assertEquals(
			'http://example.org/wp-admin/' . self::PAYMENTS_TAB_PATH,
			$url
		);
	}

	public function test_payments_tab_url_is_not_overwritten_outside_expected_scope_when_account_connected() {
		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service ); // Instantiate to add hook callbacks.

		$url_before = admin_url( self::PAYMENTS_TAB_PATH );
		do_action( 'woocommerce_settings_start' );
		$url_during = admin_url( self::PAYMENTS_TAB_PATH );
		do_action( 'woocommerce_settings_tabs' );
		$url_after = admin_url( self::PAYMENTS_TAB_PATH );

		$expected_unchanged_url   = 'http://example.org/wp-admin/' . self::PAYMENTS_TAB_PATH;
		$expected_overwritten_url = 'http://example.org/wp-admin/' . self::PAYMENTS_TAB_PATH . '&section=woocommerce_payments';

		$this->assertEquals( $expected_unchanged_url, $url_before );
		$this->assertEquals( $expected_overwritten_url, $url_during );
		$this->assertEquals( $expected_unchanged_url, $url_after );
	}

	/**
	 * @dataProvider urls_left_intact_provider
	 *
	 * @param string $path Path that should not be overwritten.
	 */
	public function test_url_other_than_payments_tab_is_not_overwritten( $path ) {
		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service ); // Instantiate to add hook callbacks.

		do_action( 'woocommerce_settings_start' );
		$url = admin_url( $path );
		do_action( 'woocommerce_settings_tabs' );

		$this->assertEquals(
			'http://example.org/wp-admin/' . $path,
			$url
		);
	}

	public function test_checkout_sections_are_modified() {
		$sections = [
			'' => 'Payment gateways',
		];

		$expected_sections = [
			'woocommerce_payments' => 'WooCommerce Payments',
			''                     => 'All payment methods',
		];

		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service );

		$this->assertEquals(
			$expected_sections,
			apply_filters( 'woocommerce_get_sections_checkout', $sections )
		);
	}

	public function test_current_section_is_overwritten_in_section_list_if_prefixed_with_woocommerce_payments() {
		global $current_section, $current_tab;

		$this->set_is_admin();
		$current_section = 'woocommerce_payments_foo';
		$current_tab     = 'checkout';

		$this->check_current_section_is_overwritten_in_section_list( 'woocommerce_payments' );
	}

	public function test_current_section_is_not_overwritten_in_section_list_if_not_prefixed_with_woocommerce_payments() {
		global $current_section, $current_tab;

		$this->set_is_admin();
		$current_section = 'foo';
		$current_tab     = 'checkout';

		$this->check_current_section_is_overwritten_in_section_list( 'foo' );
	}

	public function test_current_section_is_not_overwritten_in_section_list_if_tab_is_not_checkout() {
		global $current_section, $current_tab;

		$this->set_is_admin();
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

		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		new WC_Payments_Admin_Sections_Overwrite( $this->account_service );

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

	/**
	 * URLs that should not be overwritten by the callback to the `admin_url` filter.
	 *
	 * @return string[][]
	 */
	public function urls_left_intact_provider() {
		return [
			'tab is not "checkout"' => [ 'admin.php?page=wc-settings&tab=not-checkout' ],
			'section is not empty'  => [ 'admin.php?page=wc-settings&tab=checkout&section=foo' ],
		];
	}

	private function set_is_admin() {
		global $current_screen;

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( true );
	}
}
