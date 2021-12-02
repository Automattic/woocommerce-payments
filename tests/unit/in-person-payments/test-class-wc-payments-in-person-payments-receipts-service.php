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
		'business_name' => 'Test Business Name',
		'support_info'  => [
			'address' => [],
			'phone'   => '4242',
			'email'   => 'test@example.com',
		],
	];

	public function setUp() {
		parent::setUp();

		$this->receipts_service = new WC_Payments_In_Person_Payments_Receipts_Service();
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

	public function test_get_receipt_markup_handles_charge_validation_errors( $input_charge ) {
		$mock_order = WC_Helper_Order::create_order();
		$this->expectException( Exception::class );
		$this->receipts_service->get_receipt_markup( $this->mock_settings, $mock_order, $input_charge );
	}

	/**
	 * @dataProvider provide_settings_validation_data
	 */
	public function test_get_receipt_markup_handles_settings_validation_errors( $input_settings ) {
		$mock_order = WC_Helper_Order::create_order();
		$this->expectException( Exception::class );
		$this->receipts_service->get_receipt_markup( $input_settings, $mock_order, [] );
	}

	public function provide_settings_validation_data() {
		return [
			[ [] ],
			[ [ 'key' => 'value' ] ],
			[ [ 'support_info' => 'Test' ] ],
			[ [ 'support_info' => [ 'line1' => '42 Some Street' ] ] ],
			[
				[
					'business_name' => 'test',
					'support_info'  => [],
				],
			],
			[
				[
					'business_name' => 'test',
					'support_info'  => [ 'line1' => '42 Some Street' ],
				],
			],
		];
	}

	public function provide_charge_validation_data() {
		return [
			[ [] ],
			[ [ 'key' => 'value' ] ],
			[ [ 'amount_captured' => 100 ] ],
			[
				[
					'amount_captured'        => 100,
					'payment_method_details' => [],
				],
			],
			[
				[
					'amount_captured'        => 100,
					'payment_method_details' => [ 'card_present' => [] ],
				],
			],
			[
				[
					'amount_captured'        => 100,
					'payment_method_details' => [
						'card_present' => [
							'receipt' => [],
						],
					],
				],
			],
		];
	}
}
