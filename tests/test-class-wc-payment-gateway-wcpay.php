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
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( array( 'get_account_data' ) )
			->getMock();

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay( $this->mock_api_client );
	}

	/**
	 * Post-test teardown
	 */
	public function tearDown() {
		delete_option( 'woocommerce_woocommerce_payments_settings' );
	}

	public function test_is_stripe_connected_returns_true_in_live_mode() {
		$this->wcpay_gateway->update_option( 'stripe_account_id', 'acct_test' );
		$this->wcpay_gateway->update_option( 'publishable_key', 'pk_live_aaa' );
		$this->wcpay_gateway->update_option( 'test_mode', 'no' );

		$this->assertTrue( $this->wcpay_gateway->is_stripe_connected() );
	}

	public function test_is_stripe_connected_returns_true_in_test_mode() {
		$this->wcpay_gateway->update_option( 'stripe_account_id', 'acct_test1' );
		$this->wcpay_gateway->update_option( 'test_publishable_key', 'pk_test' );
		$this->wcpay_gateway->update_option( 'test_mode', 'yes' );

		$this->assertTrue( $this->wcpay_gateway->is_stripe_connected() );
	}

	public function test_is_stripe_connected_returns_false_when_no_account_id() {
		$this->wcpay_gateway->update_option( 'test_publishable_key', 'pk_test' );
		$this->wcpay_gateway->update_option( 'test_mode', 'yes' );

		$this->assertFalse( $this->wcpay_gateway->is_stripe_connected() );
	}

	public function test_is_stripe_connected_returns_false_when_no_public_key() {
		$this->wcpay_gateway->update_option( 'stripe_account_id', 'acct_test1' );
		$this->wcpay_gateway->update_option( 'test_mode', 'yes' );

		$this->assertFalse( $this->wcpay_gateway->is_stripe_connected() );
	}
}
