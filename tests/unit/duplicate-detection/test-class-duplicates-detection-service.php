<?php
/**
 * Class Duplicates_Detection_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicates_Detection_Service;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Giropay_Payment_Method;
use WCPay\Payment_Methods\Klarna_Payment_Method;

/**
 * WCPay\Duplicates_Detection_Service_Test unit tests.
 */
class Duplicates_Detection_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var Duplicate_Detection_Service
	 */
	private $service;

	/**
	 * Gateway from another plugin.
	 *
	 * @var Test_Gateway
	 */
	private $gateway_from_another_plugin;

	/**
	 * WooPayments gateway.
	 *
	 * @var Test_Gateway
	 */
	private $woopayments_gateway;

	/**
	 * Cached gateways.
	 *
	 * @var array
	 */
	private $cached_gateways;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		$this->service = new Duplicates_Detection_Service();

		$this->woopayments_gateway         = new Test_Gateway();
		$this->gateway_from_another_plugin = new Test_Gateway();

		$this->cached_gateways                     = WC()->payment_gateways()->payment_gateways;
		WC()->payment_gateways()->payment_gateways = [ $this->woopayments_gateway, $this->gateway_from_another_plugin ];
	}

	public function tear_down() {
		WC()->payment_gateways()->payment_gateways = $this->cached_gateways;
	}

	public function test_two_cc_both_enabled() {
		$this->set_duplicates( 'card', 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( 'card', array_keys( $result )[0] );
	}

	public function test_two_cc_one_enabled() {
		$this->set_duplicates( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, 'yes', 'no' );

		$result = $this->service->find_duplicates();

		$this->assertEmpty( $result );
	}

	public function test_two_apms_enabled() {
		$this->set_duplicates( Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID, 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID, array_keys( $result )[0] );
	}

	public function test_two_bnpls_enabled() {
		$this->set_duplicates( Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID, 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( Klarna_Payment_Method::PAYMENT_METHOD_STRIPE_ID, array_keys( $result )[0] );
	}

	public function test_two_prbs_enabled() {
		$this->set_duplicates( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, 'yes', 'yes' );
		$this->woopayments_gateway->update_option( 'payment_request', 'yes' );
		$this->woopayments_gateway->enabled    = 'yes';
		$this->gateway_from_another_plugin->id = 'apple_pay';

		$result = $this->service->find_duplicates();

		$this->assertEquals( 'apple_pay_google_pay', array_keys( $result )[0] );
	}

	public function test_duplicate_not_enabled_in_woopayments() {
		$this->set_duplicates( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID, 'yes', 'yes' );
		$this->woopayments_gateway->id = 'not_woopayments_card';

		$result = $this->service->find_duplicates();

		$this->assertEmpty( $result );
	}

	private function set_duplicates( $id, $woopayments_gateway_enabled, $gateway_from_another_plugin_enabled ) {
		$this->woopayments_gateway->enabled         = $woopayments_gateway_enabled;
		$this->gateway_from_another_plugin->enabled = $gateway_from_another_plugin_enabled;

		if ( 'card' === $id ) {
			$this->woopayments_gateway->id = 'woocommerce_payments';
		} else {
			$this->woopayments_gateway->id = 'woocommerce_payments_' . $id;
		}
		$this->gateway_from_another_plugin->id = 'another_plugin_' . $id;
	}
}
