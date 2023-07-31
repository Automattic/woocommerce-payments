<?php
/**
 * Class WC_Payments_Admin_Settings_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Admin_Settings unit tests.
 */
class WC_Payments_Admin_Settings_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * @var WC_Payments_Admin_Settings
	 */
	private $payments_admin_settings;

	public function set_up() {
		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->payments_admin_settings = new WC_Payments_Admin_Settings( $this->mock_gateway );
	}

	public function test_it_hides_test_mode_notice() {
		WC_Payments::mode()->live();

		ob_start();
		$this->payments_admin_settings->display_test_mode_notice();
		$result = ob_get_clean();

		$this->assertStringNotContainsString( 'Test mode active', $result );
	}

	public function test_it_renders_test_mode_notice() {
		WC_Payments::mode()->test();

		ob_start();
		$this->payments_admin_settings->display_test_mode_notice();
		$result = ob_get_clean();

		$this->assertStringContainsStringIgnoringCase( 'Test mode active', $result );
	}

	public function test_it_adds_plugin_links() {
		$links = $this->payments_admin_settings->add_plugin_links( [ '<a href="#some-link">Mock link</a>' ] );

		$this->assertCount( 2, $links );
	}
}
