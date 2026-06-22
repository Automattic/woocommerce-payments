<?php
/**
 * Class WC_Payments_Email_IPP_Receipt_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Email_IPP_Receipt unit tests.
 */
class WC_Payments_Email_IPP_Receipt_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Email_IPP_Receipt
	 */
	private $email;

	/**
	 * Setup test.
	 */
	public function set_up() {
		parent::set_up();

		// Load the email class - it extends WC_Email which requires WooCommerce to be initialized.
		if ( ! class_exists( 'WC_Payments_Email_IPP_Receipt' ) ) {
			require_once WCPAY_ABSPATH . 'includes/emails/class-wc-payments-email-ipp-receipt.php';
		}

		$this->email = new WC_Payments_Email_IPP_Receipt();
		$this->email->init_hooks();
	}

	/**
	 * Test that get_preview_order returns false when passed false.
	 *
	 * This ensures the method handles third-party plugin email previews gracefully,
	 * where WooCommerce may pass false instead of a WC_Order object.
	 *
	 * @see https://linear.app/a8c/issue/WOOPMNT-5617
	 */
	public function test_get_preview_order_returns_false_when_passed_false() {
		$result = $this->email->get_preview_order( false );
		$this->assertFalse( $result );
	}

	/**
	 * Test that get_preview_order returns null when passed null.
	 */
	public function test_get_preview_order_returns_null_when_passed_null() {
		$result = $this->email->get_preview_order( null );
		$this->assertNull( $result );
	}

	/**
	 * Test that get_preview_order sets payment method title when passed a valid order.
	 */
	public function test_get_preview_order_sets_payment_method_title_for_valid_order() {
		$order = WC_Helper_Order::create_order();

		$result = $this->email->get_preview_order( $order );

		$this->assertInstanceOf( WC_Order::class, $result );
		$this->assertSame( $order, $result );
		$this->assertSame( 'WooCommerce In-Person Payments', $result->get_payment_method_title() );
	}

	/**
	 * Test that trigger skips sending when the order has mobile_pos ipp_channel.
	 */
	public function test_trigger_skips_sending_for_pos_order() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WC_Payments_Order_Service::IPP_CHANNEL_META_KEY, 'mobile_pos' );
		$order->save();

		$merchant_settings = [
			'business_name' => 'Test Store',
			'support_info'  => [
				'address' => [],
				'phone'   => '',
				'email'   => '',
			],
		];
		$charge            = [
			'amount_captured'        => 1000,
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'visa',
					'last4'   => '4242',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test',
						'account_type'               => 'credit',
					],
				],
			],
		];

		$this->email->trigger( $order, $merchant_settings, $charge );

		$this->assertEmpty( $order->get_meta( '_new_receipt_email_sent' ) );
	}

	/**
	 * Test that trigger does not skip for mobile_store_management ipp_channel.
	 */
	public function test_trigger_sends_for_store_management_order() {
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( WC_Payments_Order_Service::IPP_CHANNEL_META_KEY, 'mobile_store_management' );
		$order->set_billing_email( 'test@example.com' );
		$order->save();

		$merchant_settings = [
			'business_name' => 'Test Store',
			'support_info'  => [
				'address' => [],
				'phone'   => '',
				'email'   => '',
			],
		];
		$charge            = [
			'amount_captured'        => 1000,
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'visa',
					'last4'   => '4242',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test',
						'account_type'               => 'credit',
					],
				],
			],
		];

		$this->email->trigger( $order, $merchant_settings, $charge );

		$this->assertEquals( 'true', $order->get_meta( '_new_receipt_email_sent' ) );
	}

	/**
	 * Test that trigger sends the email for non-POS card-present orders.
	 */
	public function test_trigger_sends_for_non_pos_card_present_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_billing_email( 'test@example.com' );
		$order->save();

		$merchant_settings = [
			'business_name' => 'Test Store',
			'support_info'  => [
				'address' => [],
				'phone'   => '',
				'email'   => '',
			],
		];
		$charge            = [
			'amount_captured'        => 1000,
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'visa',
					'last4'   => '4242',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test',
						'account_type'               => 'credit',
					],
				],
			],
		];

		$this->email->trigger( $order, $merchant_settings, $charge );

		$this->assertEquals( 'true', $order->get_meta( '_new_receipt_email_sent' ) );
	}

	public function test_compliance_details_displays_terminal_card_brand_name() {
		$charge = [
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'eftpos_au',
					'last4'   => '0978',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'debit',
					],
				],
			],
		];

		ob_start();
		$this->email->compliance_details( $charge, false );
		$result = ob_get_clean();

		$this->assertStringNotContainsString( '<img', $result );
		$this->assertStringContainsString( 'eftpos - 0978', $result );
	}

	public function test_compliance_details_prefers_card_network_for_brand_name() {
		$charge = [
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'visa',
					'network' => 'eftpos_au',
					'last4'   => '0978',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'debit',
					],
				],
			],
		];

		ob_start();
		$this->email->compliance_details( $charge, false );
		$result = ob_get_clean();

		$this->assertStringNotContainsString( '<img', $result );
		$this->assertStringContainsString( 'eftpos - 0978', $result );
	}

	public function test_compliance_details_uses_card_brand_for_unsupported_network() {
		$charge = [
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'visa',
					'network' => 'unsupported_network',
					'last4'   => '0978',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'debit',
					],
				],
			],
		];

		ob_start();
		$this->email->compliance_details( $charge, false );
		$result = ob_get_clean();

		$this->assertStringContainsString( 'Visa - 0978', $result );
		$this->assertStringNotContainsString( 'Unsupported_network - 0978', $result );
	}
}
