<?php
/**
 * Class WC_Payments_Express_Checkout_Custom_Fields_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * WC_Payments_Express_Checkout_Custom_Fields_Handler unit tests.
 */
class WC_Payments_Express_Checkout_Custom_Fields_Handler_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Express_Checkout_Custom_Fields_Handler
	 */
	private $system_under_test;

	/**
	 * Test setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->system_under_test = new WC_Payments_Express_Checkout_Custom_Fields_Handler();
	}

	/**
	 * Test teardown.
	 */
	public function tear_down() {
		remove_all_filters( 'woocommerce_checkout_fields' );
		remove_all_actions( 'wcpay_express_checkout_update_order_meta' );
		remove_all_actions( 'wcpay_express_checkout_after_checkout_validation' );
		WC()->checkout()->checkout_fields = null;

		parent::tear_down();
	}

	public function test_get_custom_checkout_fields_includes_only_non_standard_fields() {
		add_filter(
			'woocommerce_checkout_fields',
			function ( $fields ) {
				$fields['billing']['billing_vat_id'] = [
					'type'     => 'text',
					'label'    => 'VAT ID',
					'required' => true,
				];

				$fields['order']['delivery_note'] = [
					'type'     => 'textarea',
					'label'    => 'Delivery note',
					'required' => false,
				];

				return $fields;
			}
		);

		$custom_fields = WC_Payments_Express_Checkout_Custom_Fields_Handler::get_custom_checkout_fields();

		$this->assertArrayHasKey( 'billing_vat_id', $custom_fields );
		$this->assertArrayHasKey( 'delivery_note', $custom_fields );
		$this->assertArrayNotHasKey( 'billing_first_name', $custom_fields );
		$this->assertSame( 'text', $custom_fields['billing_vat_id']['type'] );
		$this->assertSame( 'billing', $custom_fields['billing_vat_id']['location'] );
		$this->assertTrue( $custom_fields['billing_vat_id']['required'] );
	}

	public function test_process_store_api_checkout_request_passes_sanitized_custom_checkout_data_to_actions() {
		add_filter(
			'woocommerce_checkout_fields',
			function ( $fields ) {
				$fields['order']['my_field_name'] = [
					'type'     => 'text',
					'label'    => 'My field name',
					'required' => false,
				];
				$fields['order']['order_note']    = [
					'type'     => 'textarea',
					'label'    => 'Order note',
					'required' => false,
				];

				return $fields;
			}
		);

		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request(
			[
				'my_field_name' => ' A required value ',
				'order_note'    => "Line one\nLine two",
			]
		);

		$captured_order_id = null;
		$captured_data     = null;

		add_action(
			'wcpay_express_checkout_update_order_meta',
			function ( $order_id, $custom_checkout_data ) use ( &$captured_order_id, &$captured_data ) {
				$captured_order_id = $order_id;
				$captured_data     = $custom_checkout_data;
			},
			10,
			2
		);

		$this->system_under_test->process_store_api_checkout_request( $order, $request );

		$this->assertSame( $order->get_id(), $captured_order_id );
		$this->assertSame(
			[
				'my_field_name' => 'A required value',
				'order_note'    => "Line one\nLine two",
			],
			$captured_data
		);
	}

	public function test_process_store_api_checkout_request_throws_when_required_custom_field_is_empty() {
		add_filter(
			'woocommerce_checkout_fields',
			function ( $fields ) {
				$fields['order']['my_field_name'] = [
					'type'     => 'text',
					'label'    => 'My field name',
					'required' => true,
				];

				return $fields;
			}
		);

		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request(
			[
				'my_field_name' => '',
			]
		);

		$this->expectException( RouteException::class );
		$this->expectExceptionMessage( 'My field name is a required field.' );

		$this->system_under_test->process_store_api_checkout_request( $order, $request );
	}

	/**
	 * Creates a Store API checkout request containing custom checkout data.
	 *
	 * @param array $custom_checkout_data Custom checkout data.
	 * @return WP_REST_Request
	 */
	private function create_request( array $custom_checkout_data ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'extensions',
			[
				'woocommerce-payments/express-checkout' => [
					'custom_checkout_data' => wp_json_encode( $custom_checkout_data ),
				],
			]
		);

		return $request;
	}
}
