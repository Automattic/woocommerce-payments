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
class WC_Payments_Admin_Sections_Overwrite_Test extends WCPAY_UnitTestCase {

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

	public function set_up() {
		parent::set_up();

		$this->account_service = $this->getMockBuilder( WC_Payments_Account::class )->disableOriginalConstructor()->setMethods( [ 'get_cached_account_data' ] )->getMock();
	}

	public function test_checkout_sections_are_modified() {
		$sections = [
			'' => 'Payment gateways',
		];

		$expected_sections = [
			'woocommerce_payments' => 'WooPayments',
			''                     => 'All payment methods',
		];

		$this->account_service
			->expects( $this->any() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_live' => true ] );
		$admin_sections_overwrite = new WC_Payments_Admin_Sections_Overwrite( $this->account_service );
		$admin_sections_overwrite->init_hooks();

		$this->assertEquals(
			$expected_sections,
			apply_filters( 'woocommerce_get_sections_checkout', $sections )
		);
	}
}
