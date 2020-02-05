<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Test extends WP_UnitTestCase {

	const NO_REQUIREMENTS      = false;
	const PENDING_REQUIREMENTS = true;

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

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
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( array( 'get_account_data' ) )
			->getMock();

		$this->mock_account = $this->getMockBuilder( 'WC_Payments_Account' )
			->disableOriginalConstructor()
			->setMethods( array( 'get_publishable_key', 'get_stripe_account_id' ) )
			->getMock();

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay( $this->mock_api_client, $this->mock_account );
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		delete_option( 'woocommerce_woocommerce_payments_settings' );
	}

	public function test_payment_fields_outputs_fields() {
		$this->mock_account->expects( $this->once() )->method( 'get_stripe_account_id' )->will(
			$this->returnValue( 'acct_test' )
		);

		$this->mock_account->expects( $this->once() )->method( 'get_publishable_key' )->will(
			$this->returnValue( 'pk_test_' )
		);

		$this->wcpay_gateway->payment_fields();

		$this->expectOutputRegex( '/<div id="wcpay-card-element"><\/div>/' );
	}

	public function test_payment_fields_outputs_error() {
		$this->mock_account->expects( $this->once() )->method( 'get_publishable_key' )->will(
			$this->throwException( new Exception() )
		);

		$this->wcpay_gateway->payment_fields();

		$this->expectOutputRegex( '/An error was encountered when preparing the payment form\. Please try again later\./' );
	}
}
