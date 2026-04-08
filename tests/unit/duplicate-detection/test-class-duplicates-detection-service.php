<?php
/**
 * Class Duplicates_Detection_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicates_Detection_Service;
use WCPay\PaymentMethods\Configs\Definitions\CardDefinition;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;

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

		$registry = PaymentMethodDefinitionRegistry::instance();
		$registry->register_payment_method( \WCPay\PaymentMethods\Configs\Definitions\IdealDefinition::class );
		$registry->register_payment_method( \WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition::class );
	}

	public function tear_down() {
		WC()->payment_gateways()->payment_gateways = $this->cached_gateways;

		// resetting to prevent test pollution.
		$reflection        = new \ReflectionClass( PaymentMethodDefinitionRegistry::class );
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );
		$instance_property->setAccessible( false );
	}

	public function test_two_cc_both_enabled() {
		$this->set_duplicates( 'card', 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( 'card', array_keys( $result )[0] );
	}

	public function test_two_cc_one_enabled() {
		$this->set_duplicates( CardDefinition::get_id(), 'yes', 'no' );

		$result = $this->service->find_duplicates();

		$this->assertEmpty( $result );
	}

	public function test_two_apms_enabled() {
		$this->set_duplicates( \WCPay\PaymentMethods\Configs\Definitions\IdealDefinition::get_id(), 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( \WCPay\PaymentMethods\Configs\Definitions\IdealDefinition::get_id(), array_keys( $result )[0] );
	}

	public function test_two_bnpls_enabled() {
		$this->set_duplicates( \WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition::get_id(), 'yes', 'yes' );

		$result = $this->service->find_duplicates();

		$this->assertCount( 1, $result );
		$this->assertEquals( \WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition::get_id(), array_keys( $result )[0] );
	}

	public function test_two_prbs_enabled() {
		$this->set_duplicates( CardDefinition::get_id(), 'yes', 'yes' );
		$this->woopayments_gateway->is_payment_request_enabled_value = true;
		$this->woopayments_gateway->enabled                          = 'yes';
		$this->gateway_from_another_plugin->id                       = 'apple_pay';

		$result = $this->service->find_duplicates();

		$this->assertEquals( 'apple_pay_google_pay', array_keys( $result )[0] );
	}

	public function test_prb_detection_excludes_disabled_stripe_gateway() {
		// WCPay gateway enabled with PRB.
		$this->woopayments_gateway->id                               = 'woocommerce_payments';
		$this->woopayments_gateway->enabled                          = 'yes';
		$this->woopayments_gateway->is_payment_request_enabled_value = true;

		// Stripe gateway disabled but with payment_request still enabled in settings.
		$this->gateway_from_another_plugin->id       = 'stripe';
		$this->gateway_from_another_plugin->enabled  = 'no';
		$this->gateway_from_another_plugin->settings = [ 'payment_request' => 'yes' ];

		$result = $this->service->find_duplicates();

		$this->assertEmpty( $result );
	}

	public function test_duplicate_not_enabled_in_woopayments() {
		$this->set_duplicates( CardDefinition::get_id(), 'yes', 'yes' );
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
