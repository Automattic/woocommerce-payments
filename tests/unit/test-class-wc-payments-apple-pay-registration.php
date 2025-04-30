<?php
/**
 * Class WC_Payments_Apple_Pay_Registration_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Apple_Pay_Registration unit tests.
 */
class WC_Payments_Apple_Pay_Registration_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Apple_Pay_Registration
	 */
	private $wc_apple_pay_registration;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_account;


	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( 'WC_Payments_Account' )
			->disableOriginalConstructor()
			->getMock();

		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->wc_apple_pay_registration = new WC_Payments_Apple_Pay_Registration( $this->mock_api_client, $this->mock_account, $mock_gateway );
		$this->wc_apple_pay_registration->init_hooks();
	}

	public function test_it_verifies_domain_during_upgrade() {
		$verify_callback_priority = has_action(
			'woocommerce_woocommerce_payments_updated',
			[ $this->wc_apple_pay_registration, 'verify_domain_on_update' ]
		);

		$this->assertIsInt( $verify_callback_priority );
	}
}
