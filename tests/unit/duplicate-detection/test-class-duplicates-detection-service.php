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
		parent::set_up();

		$this->reset_payment_method_registry();

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

		parent::tear_down();
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

	public function test_woopayments_wallets_only_are_not_duplicates() {
		$apple_pay_gateway          = new Test_Gateway();
		$apple_pay_gateway->id      = 'woocommerce_payments_apple_pay';
		$apple_pay_gateway->enabled = 'yes';

		$google_pay_gateway          = new Test_Gateway();
		$google_pay_gateway->id      = 'woocommerce_payments_google_pay';
		$google_pay_gateway->enabled = 'yes';

		$this->woopayments_gateway->id                               = 'woocommerce_payments';
		$this->woopayments_gateway->enabled                          = 'yes';
		$this->woopayments_gateway->is_payment_request_enabled_value = true;

		WC()->payment_gateways()->payment_gateways = [ $this->woopayments_gateway, $apple_pay_gateway, $google_pay_gateway ];

		$result = $this->service->find_duplicates();

		$this->assertArrayNotHasKey( 'apple_pay_google_pay', $result );
	}

	public function test_woopayments_wallet_and_stripe_prb_are_duplicates() {
		$apple_pay_gateway          = new Test_Gateway();
		$apple_pay_gateway->id      = 'woocommerce_payments_apple_pay';
		$apple_pay_gateway->enabled = 'yes';

		$this->woopayments_gateway->id                               = 'woocommerce_payments';
		$this->woopayments_gateway->enabled                          = 'yes';
		$this->woopayments_gateway->is_payment_request_enabled_value = true;

		$this->gateway_from_another_plugin->id       = 'stripe';
		$this->gateway_from_another_plugin->enabled  = 'yes';
		$this->gateway_from_another_plugin->settings = [ 'payment_request' => 'yes' ];

		WC()->payment_gateways()->payment_gateways = [ $this->woopayments_gateway, $apple_pay_gateway, $this->gateway_from_another_plugin ];

		$result = $this->service->find_duplicates();

		$this->assertArrayHasKey( 'apple_pay_google_pay', $result );
	}

	/**
	 * @dataProvider provider_third_party_wallet_settings
	 */
	public function test_third_party_wallet_settings( $gateway_id, $settings, $site_options, $is_duplicate ) {
		foreach ( $site_options as $option => $value ) {
			update_option( $option, $value );
		}

		$this->enable_woopayments_wallets();
		$this->gateway_from_another_plugin->id       = $gateway_id;
		$this->gateway_from_another_plugin->enabled  = 'yes';
		$this->gateway_from_another_plugin->settings = $settings;

		$result = $this->service->find_duplicates();

		foreach ( array_keys( $site_options ) as $option ) {
			delete_option( $option );
		}

		$this->assertSame( $is_duplicate, array_key_exists( 'apple_pay_google_pay', $result ) );
	}

	public function provider_third_party_wallet_settings() {
		return [
			'stripe express_checkout on'       => [ 'stripe', [ 'express_checkout' => 'yes' ], [], true ],
			'stripe express_checkout off'      => [ 'stripe', [ 'express_checkout' => 'no' ], [], false ],
			'stripe legacy payment_request on' => [ 'stripe', [ 'payment_request' => 'yes' ], [], true ],
			'stripe stale payment_request'     => [
				'stripe',
				[
					'express_checkout' => 'no',
					'payment_request'  => 'yes',
				],
				[],
				false,
			],
			'square default'                   => [ 'square_credit_card', [ 'title' => 'Square' ], [], true ],
			'square off'                       => [ 'square_credit_card', [ 'enable_digital_wallets' => 'no' ], [], false ],
			'square one wallet hidden'         => [ 'square_credit_card', [ 'digital_wallets_hide_button_options' => [ 'apple' ] ], [], true ],
			'square both wallets hidden'       => [ 'square_credit_card', [ 'digital_wallets_hide_button_options' => [ 'apple', 'google' ] ], [], false ],
			'braintree apple pay on'           => [ 'braintree_credit_card', [ 'title' => 'Braintree' ], [ 'sv_wc_apple_pay_enabled' => 'yes' ], true ],
			'braintree google pay on'          => [ 'braintree_credit_card', [ 'title' => 'Braintree' ], [ 'sv_wc_google_pay_enabled' => 'yes' ], true ],
			'braintree wallets off'            => [ 'braintree_credit_card', [ 'title' => 'Braintree' ], [ 'sv_wc_apple_pay_enabled' => 'no' ], false ],
			'express_checkout_enabled option'  => [ 'cpsw_express_checkout', [ 'express_checkout_enabled' => 'yes' ], [], true ],
			'unknown gateway'                  => [ 'square_cash_app_pay', [ 'title' => 'Cash App' ], [], false ],
		];
	}

	/**
	 * @dataProvider provider_stripe_express_checkout_method
	 */
	public function test_stripe_method_takes_priority_over_stored_option( $method_result, $is_duplicate ) {
		$stripe_gateway                                    = new class() extends Test_Gateway {
			/**
			 * Value returned by is_express_checkout_enabled().
			 *
			 * @var bool
			 */
			public $is_express_checkout_enabled_value = false;

			public function is_express_checkout_enabled() {
				return $this->is_express_checkout_enabled_value;
			}
		};
		$stripe_gateway->id                                = 'stripe';
		$stripe_gateway->enabled                           = 'yes';
		$stripe_gateway->settings                          = [ 'express_checkout' => $method_result ? 'no' : 'yes' ];
		$stripe_gateway->is_express_checkout_enabled_value = $method_result;

		$this->enable_woopayments_wallets();
		WC()->payment_gateways()->payment_gateways = [ $this->woopayments_gateway, $stripe_gateway ];

		$result = $this->service->find_duplicates();

		$this->assertSame( $is_duplicate, array_key_exists( 'apple_pay_google_pay', $result ) );
	}

	public function provider_stripe_express_checkout_method() {
		return [
			'enabled'  => [ true, true ],
			'disabled' => [ false, false ],
		];
	}

	public function test_error_from_third_party_gateway_is_contained() {
		$stripe_gateway          = new class() extends Test_Gateway {
			public function is_express_checkout_enabled() {
				throw new Error( 'Third-party failure' );
			}
		};
		$stripe_gateway->id      = 'stripe';
		$stripe_gateway->enabled = 'yes';

		$this->enable_woopayments_wallets();
		WC()->payment_gateways()->payment_gateways = [ $this->woopayments_gateway, $stripe_gateway ];

		$result = $this->service->find_duplicates();

		$this->assertArrayNotHasKey( 'apple_pay_google_pay', $result );
		$this->assertSame( [ 'woocommerce_payments', 'stripe' ], $result['card'] );
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

	private function enable_woopayments_wallets() {
		$this->woopayments_gateway->id                               = 'woocommerce_payments';
		$this->woopayments_gateway->enabled                          = 'yes';
		$this->woopayments_gateway->is_payment_request_enabled_value = true;
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
