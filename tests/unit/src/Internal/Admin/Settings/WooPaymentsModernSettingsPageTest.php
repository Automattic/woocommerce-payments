<?php
/**
 * WooPayments modern settings page adapter tests.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Admin\Settings;

use WC_Payment_Gateway_WCPay;
use WCPay\Internal\Admin\Settings\WooPaymentsModernSettingsPage;
use WCPAY_UnitTestCase;

/**
 * WooPaymentsModernSettingsPage unit tests.
 */
class WooPaymentsModernSettingsPageTest extends WCPAY_UnitTestCase {

	/**
	 * Gateway mock.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Adapter under test.
	 *
	 * @var WooPaymentsModernSettingsPage
	 */
	private $page;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! interface_exists( '\Automattic\WooCommerce\Internal\Admin\Settings\ReactSettingsPageInterface' ) ) {
			$this->markTestSkipped( 'WooCommerce modern settings SDK is not available.' );
		}

		$this->gateway     = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_form_fields', 'get_option', 'get_payment_methods', 'get_account_country' ] )
			->getMock();
		$this->gateway->id = WC_Payment_Gateway_WCPay::GATEWAY_ID;

		$this->page = new WooPaymentsModernSettingsPage( $this->gateway );
	}

	public function test_maps_woopayments_custom_statement_descriptor_type_to_text(): void {
		$this->assertSame(
			[ 'account_statement_descriptor' => 'text' ],
			$this->page->get_extra_type_map( WC_Payment_Gateway_WCPay::GATEWAY_ID )
		);
	}

	public function test_normalizes_gateway_form_fields_for_react_settings_schema(): void {
		$this->gateway->method( 'get_form_fields' )->willReturn(
			[
				'manual_capture'                   => [
					'title'       => 'Manual capture',
					'label'       => 'Capture later',
					'type'        => 'checkbox',
					'description' => 'Authorize at checkout.',
				],
				'platform_checkout_custom_message' => [
					'default' => 'Default WooPay message',
				],
			]
		);
		$this->gateway->method( 'get_option' )->willReturnMap(
			[
				[ 'manual_capture', null, 'no' ],
				[ 'platform_checkout_custom_message', null, 'Default WooPay message' ],
			]
		);

		$definitions = $this->page->get_settings_definitions();

		$this->assertSame( 'manual_capture', $definitions[0]['id'] );
		$this->assertSame( 'woocommerce_woocommerce_payments_manual_capture', $definitions[0]['field_name'] );
		$this->assertSame( 'Capture later', $definitions[0]['title'] );
		$this->assertSame( 'Authorize at checkout.', $definitions[0]['desc'] );
		$this->assertSame( 'no', $definitions[0]['value'] );

		$this->assertSame( 'platform_checkout_custom_message', $definitions[1]['id'] );
		$this->assertSame( 'text', $definitions[1]['type'] );
		$this->assertSame( 'WooPay custom message', $definitions[1]['title'] );
		$this->assertSame( 'Default WooPay message', $definitions[1]['value'] );
	}

	public function test_synthesizes_options_for_payment_method_multiselect(): void {
		$card = new class() {
			public function get_title( string $country ): string {
				return 'US' === $country ? 'Card' : 'Card fallback';
			}
		};

		$this->gateway->method( 'get_payment_methods' )->willReturn(
			[
				'card' => $card,
			]
		);
		$this->gateway->method( 'get_account_country' )->willReturn( 'US' );

		$this->assertSame(
			[
				[
					'label' => 'Card',
					'value' => 'card',
				],
			],
			$this->page->get_field_options( 'upe_enabled_payment_method_ids', [], WC_Payment_Gateway_WCPay::GATEWAY_ID )
		);
	}

	public function test_lists_legacy_checkbox_post_field_names(): void {
		$this->gateway->method( 'get_form_fields' )->willReturn(
			[
				'manual_capture' => [ 'type' => 'checkbox' ],
				'button_type'    => [ 'type' => 'select' ],
			]
		);

		$this->assertSame(
			[ 'woocommerce_woocommerce_payments_manual_capture' ],
			$this->page->get_checkbox_field_names()
		);
	}
}
