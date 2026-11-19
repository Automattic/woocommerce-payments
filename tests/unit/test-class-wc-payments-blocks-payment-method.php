<?php
/**
 * Class WC_Payments_Blocks_Payment_Method_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\WC_Payments_Checkout;

/**
 * WC_Payments_Blocks_Payment_Method unit tests.
 */
class WC_Payments_Blocks_Payment_Method_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Blocks_Payment_Method
	 */
	private $system_under_test;

	public function set_up() {
		parent::set_up();

		$this->system_under_test = new WC_Payments_Blocks_Payment_Method();
		$this->system_under_test->initialize();
		wp_deregister_script( 'stripe' );
	}

	public function tear_down() {
		wp_deregister_script( 'stripe' );
		wp_deregister_script( 'WCPAY_BLOCKS_CHECKOUT' );
		delete_option( WC_Payments_Features::STRIPE_JS_DAHLIA_FLAG_NAME );

		parent::tear_down();
	}

	/**
	 * @dataProvider stripe_js_flag_provider
	 */
	public function test_get_payment_method_script_handles_registers_stripe_js_for_flag( string $flag_value, string $expected_src, $expected_ver ) {
		update_option( WC_Payments_Features::STRIPE_JS_DAHLIA_FLAG_NAME, $flag_value );

		$this->system_under_test->get_payment_method_script_handles();

		$this->assertSame( $expected_src, wp_scripts()->registered['stripe']->src );
		$this->assertSame( $expected_ver, wp_scripts()->registered['stripe']->ver );
	}

	public function test_get_payment_method_data_adds_amount_limited_payment_methods() {
		$mock_checkout = $this->createMock( WC_Payments_Checkout::class );
		$mock_checkout->method( 'get_payment_fields_js_config' )->willReturn(
			[
				'paymentMethodsConfig' => [
					'card' => [ 'title' => 'Filtered card title' ],
				],
			]
		);
		$mock_checkout->method( 'get_amount_limited_payment_method_config' )->willReturn(
			[
				'card'   => [ 'title' => 'Card' ],
				'klarna' => [ 'title' => 'Klarna' ],
			]
		);
		$this->set_checkout( $mock_checkout );

		$data = $this->system_under_test->get_payment_method_data();

		$this->assertSame(
			[
				'card'   => [ 'title' => 'Filtered card title' ],
				'klarna' => [ 'title' => 'Klarna' ],
			],
			$data['paymentMethodsConfig']
		);
	}

	public function test_get_payment_method_data_leaves_config_without_payment_methods_untouched() {
		$mock_checkout = $this->createMock( WC_Payments_Checkout::class );
		$mock_checkout->method( 'get_payment_fields_js_config' )->willReturn( [ 'publishableKey' => 'pk_test' ] );
		$mock_checkout->expects( $this->never() )->method( 'get_amount_limited_payment_method_config' );
		$this->set_checkout( $mock_checkout );

		$data = $this->system_under_test->get_payment_method_data();

		$this->assertArrayNotHasKey( 'paymentMethodsConfig', $data );
		$this->assertSame( 'pk_test', $data['publishableKey'] );
	}

	/**
	 * Replaces the checkout service the system under test reads its config from.
	 *
	 * @param WC_Payments_Checkout $checkout Checkout service.
	 */
	private function set_checkout( $checkout ): void {
		$property = new ReflectionProperty( WC_Payments_Blocks_Payment_Method::class, 'wc_payments_checkout' );
		$property->setAccessible( true );
		$property->setValue( $this->system_under_test, $checkout );
	}

	public function stripe_js_flag_provider(): array {
		return [
			'flag off' => [ '0', 'https://js.stripe.com/v3/', '3.0' ],
			'flag on'  => [ '1', 'https://js.stripe.com/dahlia/stripe.js', null ],
		];
	}
}
