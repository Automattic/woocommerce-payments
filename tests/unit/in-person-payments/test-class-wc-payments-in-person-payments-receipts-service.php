<?php
/**
 * Class WC_Payments_In_Person_Payments_Receipts_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_In_Person_Payments_Receipts_Service_Test unit tests.
 */
class WC_Payments_In_Person_Payments_Receipts_Service_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 * @var WC_Payments_In_Person_Payments_Receipts_Service
	 */
	private $receipts_service;

	/**
	 * mock_settings
	 *
	 * @var array
	 */
	private $mock_settings = [
		'branding_logo' => [],
		'business_name' => 'Test Business Name',
		'support_info'  => [
			'address' => [],
			'phone'   => '4242',
			'email'   => 'test@example.com',
		],
	];

	public function set_up() {
		parent::set_up();

		$this->receipts_service = new WC_Payments_In_Person_Payments_Receipts_Service();
		reset_phpmailer_instance();
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	public function test_get_receipt_markup_is_EMV_compliant() {
		$mock_order  = WC_Helper_Order::create_order();
		$mock_charge = [
			'amount_captured'        => 10,
			'order'                  => [
				'number' => $mock_order->get_id(),
			],
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'test',
					'last4'   => 'Test',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'test',
					],
				],
			],
		];

		$result = $this->receipts_service->get_receipt_markup( $this->mock_settings, $mock_order, $mock_charge );

		$doc = new DOMDocument();
		$doc->loadHTML( $result );

		$this->assertSame( $doc->getElementById( 'application-preferred-name' )->textContent, 'Application name: Test' );
		$this->assertSame( $doc->getElementById( 'dedicated-file-name' )->textContent, 'AID: Test 42' );
		$this->assertSame( $doc->getElementById( 'account_type' )->textContent, 'Account Type: Test' );
	}

	/**
	 * @dataProvider provide_charge_validation_data
	 */

	public function test_get_receipt_markup_handles_charge_validation_errors( $input_charge, $expected_message ) {
		$mock_order = WC_Helper_Order::create_order();
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( $expected_message );
		$this->receipts_service->get_receipt_markup( $this->mock_settings, $mock_order, $input_charge );
	}

	/**
	 * @dataProvider provide_settings_validation_data
	 */
	public function test_get_receipt_markup_handles_settings_validation_errors( $input_settings, $expected_message ) {
		$mock_order = WC_Helper_Order::create_order();
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( $expected_message );
		$this->receipts_service->get_receipt_markup( $input_settings, $mock_order, [] );
	}

	public function test_send_customer_receipt_email_success() {
		$mock_order  = WC_Helper_Order::create_order();
		$mock_charge = [
			'amount_captured'        => 10,
			'order'                  => [
				'number' => $mock_order->get_id(),
			],
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'test',
					'last4'   => 'Test',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'Test',
					],
				],
			],
		];

		$mock_mailer = tests_retrieve_phpmailer_instance();

		$this->receipts_service->send_customer_ipp_receipt_email( $mock_order, $mock_charge );

		$sent_email = $mock_mailer->get_sent();

		$this->assertSame( $mock_order->get_billing_email(), $sent_email->to[0][0] );
		$this->assertSame( 'Your Test Blog Receipt', $sent_email->subject );

		$doc = new DOMDocument();
		$doc->loadHTML( $sent_email->body );
		$this->assertSame( $mock_charge['payment_method_details']['card_present']['receipt']['application_preferred_name'], $doc->getElementById( 'application-preferred-name' )->textContent );
		$this->assertSame( $mock_charge['payment_method_details']['card_present']['receipt']['dedicated_file_name'], $doc->getElementById( 'dedicated-file-name' )->textContent );
		$this->assertSame( $mock_charge['payment_method_details']['card_present']['receipt']['account_type'], $doc->getElementById( 'account-type' )->textContent );
	}

	public function test_send_customer_receipt_email_not_sent_twice() {
		$mock_order = WC_Helper_Order::create_order();
		$mock_order->update_meta_data( '_new_receipt_email_sent', 'true' );

		$mock_mailer = tests_retrieve_phpmailer_instance();

		$this->receipts_service->send_customer_ipp_receipt_email( $mock_order, [] );

		$sent_email = $mock_mailer->get_sent();

		$this->assertFalse( $sent_email );
	}

	public function provide_settings_validation_data() {
		return [
			[ [], 'Business name needs to be provided.' ],
			[ [ 'key' => 'value' ], 'Business name needs to be provided.' ],
			[
				[
					'branding_logo' => [],
					'business_name' => 'test',
					'support_info'  => 'Test',
				],
				'Support information needs to be provided.',
			],
			[
				[
					'branding_logo' => [],
					'business_name' => 'test',
					'support_info'  => [],
				],
				'Support information needs to be provided.',
			],
			[
				[
					'branding_logo' => [],
					'business_name' => 'test',
					'support_info'  => [ 'line1' => '42 Some Street' ],
				],
				'Error validating support information. Missing key: address',
			],
			[
				[
					'branding_logo' => [],
					'business_name' => 'test',
					'support_info'  => [
						'address' => 'Test',
						'phone'   => '4242',
					],
				],
				'Error validating support information. Missing key: email',
			],
			[
				[
					'branding_logo' => [],
					'business_name' => 'test',
					'support_info'  => [
						'address' => 'Test',
						'line1'   => '42 Some Street',
						'email'   => 'Test',
					],
				],
				'Error validating support information. Missing key: phone',
			],
		];
	}

	public function provide_charge_validation_data() {
		return [
			[ [], 'Captured amount needs to be provided.' ],
			[ [ 'key' => 'value' ], 'Captured amount needs to be provided.' ],
			[ [ 'amount_captured' => '42' ], 'Payment method details needs to be provided.' ],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => 'test',
				],
				'Payment method details needs to be provided.',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [],
				],
				'Payment method details needs to be provided.',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [ 'key' => 'value' ],
				],
				'Payment method details needs to be provided.',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [ 'card_present' => 'value' ],
				],
				'Payment method details needs to be provided.',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [ 'card_present' => [] ],
				],
				'Payment method details needs to be provided.',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [ 'card_present' => [ 'last4' => 'test' ] ],
				],
				'Error validating payment information. Missing key: brand',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [ 'card_present' => [ 'brand' => 'test' ] ],
				],
				'Error validating payment information. Missing key: last4',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [
						'card_present' => [
							'brand' => 'test',
							'last4' => 'test',
						],
					],
				],
				'Error validating payment information. Missing key: receipt',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [
						'card_present' => [
							'brand'   => 'test',
							'last4'   => 'test',
							'receipt' => [],
						],
					],
				],
				'Error validating receipt information. Missing key: application_preferred_name',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [
						'card_present' => [
							'brand'   => 'test',
							'last4'   => 'test',
							'receipt' => [
								'application_preferred_name' => 'test',
								'account_type' => 'test',
							],
						],
					],
				],
				'Error validating receipt information. Missing key: dedicated_file_name',
			],
			[
				[
					'amount_captured'        => '42',
					'payment_method_details' => [
						'card_present' => [
							'brand'   => 'test',
							'last4'   => 'test',
							'receipt' => [
								'application_preferred_name' => 'test',
								'dedicated_file_name' => 'test',
							],
						],
					],
				],
				'Error validating receipt information. Missing key: account_type',
			],
		];
	}
}
