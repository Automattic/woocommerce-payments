<?php
/**
 * Class WC_Payments_Payment_Method_Messaging_Element_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;

require_once WCPAY_ABSPATH . 'includes/class-wc-payments-payment-method-messaging-element.php';

/**
 * WC_Payments_Payment_Method_Messaging_Element unit tests.
 */
class WC_Payments_Payment_Method_Messaging_Element_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var MockObject|WC_Payments_Account
	 */
	private $mock_account;

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var MockObject|WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Payment method messaging element instance.
	 *
	 * @var WC_Payments_Payment_Method_Messaging_Element
	 */
	private $messaging_element;

	/**
	 * Pre-test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );

		$this->messaging_element = new WC_Payments_Payment_Method_Messaging_Element(
			$this->mock_account,
			$this->mock_gateway
		);

		global $wp_query;

		$wp_query->is_tax         = false;
		$wp_query->is_singular    = true;
		$wp_query->is_page        = false;
		$wp_query->queried_object = (object) [
			'post_type' => 'product',
			'post_name' => 'test',
		];
	}

	public function tear_down(): void {
		parent::tear_down();
		wp_reset_postdata();
	}

	private function get_script_data() {
		// this returns a string with value `var wcpayStripeSiteMessaging = {[...]};`.
		// but we just need the object within this string, so we can parse it through `json_decode`.
		$script_data_string = wp_scripts()->get_data( 'WCPAY_PRODUCT_DETAILS', 'data' );

		// finding the position of the first '{' and the last '}'.
		$start_pos = strpos( $script_data_string, '{' );
		$end_pos   = strrpos( $script_data_string, '}' );

		// extracting the substring within the braces.
		$json_substring = substr( $script_data_string, $start_pos, ( $end_pos - $start_pos ) + 1 );

		// now we got the associative array back!
		return json_decode( $json_substring, true );
	}

	/**
	 * Test that only active BNPL payment methods are returned in the script data.
	 */
	public function test_init_filters_active_bnpl_methods_only() {
		$this->mock_account->method( 'get_stripe_account_id' )->willReturn( 'acct_test' );
		$this->mock_account->method( 'get_publishable_key' )->willReturn( 'pk_test_key' );
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				Payment_Method::CARD,
				Payment_Method::AFFIRM,
				Payment_Method::AFTERPAY,
				Payment_Method::KLARNA,
				Payment_Method::IDEAL,
			]
		);
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_statuses' )->willReturn(
			[
				'affirm_payments'            => [ 'status' => 'active' ],
				'afterpay_clearpay_payments' => [ 'status' => 'inactive' ],
				'klarna_payments'            => [ 'status' => 'active' ],
				'ideal_payments'             => [ 'status' => 'active' ],
			]
		);
		$this->mock_gateway->method( 'get_payment_method_capability_key_map' )->willReturn(
			[
				Payment_Method::AFFIRM   => 'affirm_payments',
				Payment_Method::AFTERPAY => 'afterpay_clearpay_payments',
				Payment_Method::KLARNA   => 'klarna_payments',
				Payment_Method::IDEAL    => 'ideal_payments',
			]
		);

		$this->messaging_element->init();

		$script_data = $this->get_script_data();

		// only active BNPLs should be included.
		$this->assertContains( Payment_Method::AFFIRM, $script_data['paymentMethods'], 'Affirm should be included' );
		$this->assertContains( Payment_Method::KLARNA, $script_data['paymentMethods'], 'Klarna should be included' );
		$this->assertNotContains( Payment_Method::AFTERPAY, $script_data['paymentMethods'], 'Afterpay should not be included' );
		$this->assertNotContains( Payment_Method::CARD, $script_data['paymentMethods'], 'Card should not be included' );
		$this->assertNotContains( Payment_Method::IDEAL, $script_data['paymentMethods'], 'iDEAL should not be included' );

		wp_scripts()->remove( 'WCPAY_PRODUCT_DETAILS' );
	}

	/**
	 * Test that no BNPL methods are returned when all are inactive.
	 */
	public function test_init_returns_no_bnpl_methods_when_all_inactive() {
		$this->mock_account->method( 'get_stripe_account_id' )->willReturn( 'acct_test' );
		$this->mock_account->method( 'get_publishable_key' )->willReturn( 'pk_test_key' );
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )->willReturn(
			[
				Payment_Method::AFFIRM,
				Payment_Method::AFTERPAY,
				Payment_Method::KLARNA,
			]
		);
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_statuses' )->willReturn(
			[
				'affirm_payments'            => [ 'status' => 'inactive' ],
				'afterpay_clearpay_payments' => [ 'status' => 'inactive' ],
				'klarna_payments'            => [ 'status' => 'inactive' ],
			]
		);
		$this->mock_gateway->method( 'get_payment_method_capability_key_map' )->willReturn(
			[
				Payment_Method::AFFIRM   => 'affirm_payments',
				Payment_Method::AFTERPAY => 'afterpay_clearpay_payments',
				Payment_Method::KLARNA   => 'klarna_payments',
			]
		);

		$this->messaging_element->init();

		$script_data = $this->get_script_data();

		// no payment methods should be included.
		$this->assertEmpty( $script_data['paymentMethods'], 'No BNPL methods should be included when all are inactive' );

		wp_scripts()->remove( 'WCPAY_PRODUCT_DETAILS' );
	}
}
