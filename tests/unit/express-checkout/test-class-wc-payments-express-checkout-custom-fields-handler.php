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
	 * Classic checkout process callbacks added during a test.
	 *
	 * @var array
	 */
	private $classic_checkout_process_callbacks = [];

	/**
	 * Classic checkout update order meta callbacks added during a test.
	 *
	 * @var array
	 */
	private $classic_checkout_update_order_meta_callbacks = [];

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
		remove_all_actions( 'wcpay_express_checkout_update_custom_fields_order_meta' );
		remove_all_actions( 'wcpay_express_checkout_after_custom_fields_validation' );
		foreach ( $this->classic_checkout_process_callbacks as $callback ) {
			remove_action( 'woocommerce_checkout_process', $callback );
		}
		foreach ( $this->classic_checkout_update_order_meta_callbacks as $callback ) {
			remove_action( 'woocommerce_checkout_update_order_meta', $callback );
		}
		$this->classic_checkout_process_callbacks           = [];
		$this->classic_checkout_update_order_meta_callbacks = [];
		wc_clear_notices();
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

	public function test_process_store_api_checkout_request_passes_sanitized_custom_checkout_data_to_custom_field_actions() {
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
			'wcpay_express_checkout_update_custom_fields_order_meta',
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

	public function test_process_store_api_checkout_request_saves_registered_custom_checkout_fields_to_order_meta() {
		add_filter(
			'woocommerce_checkout_fields',
			function ( $fields ) {
				$fields['order']['my_field_name'] = [
					'type'     => 'text',
					'label'    => 'My field name',
					'required' => false,
				];

				return $fields;
			}
		);

		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request(
			[
				'my_field_name' => ' A required value ',
			]
		);

		$this->system_under_test->process_store_api_checkout_request( $order, $request );

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( 'A required value', $order->get_meta( 'my_field_name' ) );
	}

	public function test_process_store_api_checkout_request_runs_classic_checkout_process_validation() {
		$checkout_process_callback = function () {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( empty( $_POST['my_field_name'] ) ) {
				wc_add_notice( 'Please enter something into this new shiny field.', 'error' );
			}
		};

		$this->classic_checkout_process_callbacks[] = $checkout_process_callback;

		add_action(
			'woocommerce_checkout_process',
			$checkout_process_callback
		);

		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request(
			[
				'my_field_name' => '',
			]
		);

		$this->expectException( RouteException::class );
		$this->expectExceptionMessage( 'Please enter something into this new shiny field.' );

		$this->system_under_test->process_store_api_checkout_request( $order, $request );
	}

	public function test_process_store_api_checkout_request_runs_classic_checkout_update_order_meta_callbacks() {
		$checkout_update_order_meta_callback = function ( $order_id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['my_field_name'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				update_post_meta( $order_id, 'My Field', sanitize_text_field( wp_unslash( $_POST['my_field_name'] ) ) );
			}
		};

		$this->classic_checkout_update_order_meta_callbacks[] = $checkout_update_order_meta_callback;

		add_action(
			'woocommerce_checkout_update_order_meta',
			$checkout_update_order_meta_callback
		);

		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request(
			[
				'my_field_name' => ' A required value ',
			]
		);

		$this->system_under_test->process_store_api_checkout_request( $order, $request );

		$this->assertSame( 'A required value', get_post_meta( $order->get_id(), 'My Field', true ) );
	}

	public function test_process_store_api_checkout_request_ignores_empty_custom_checkout_data() {
		$order   = WC_Helper_Order::create_order();
		$request = $this->create_request_with_raw_custom_checkout_data( '' );

		$this->system_under_test->process_store_api_checkout_request( $order, $request );

		$this->assertSame( '', $order->get_meta( 'my_field_name' ) );
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
		return $this->create_request_with_raw_custom_checkout_data( wp_json_encode( $custom_checkout_data ) );
	}

	/**
	 * Creates a Store API checkout request containing raw custom checkout data.
	 *
	 * @param mixed $custom_checkout_data Custom checkout data.
	 * @return WP_REST_Request
	 */
	private function create_request_with_raw_custom_checkout_data( $custom_checkout_data ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'extensions',
			[
				'woocommerce-payments/express-checkout' => [
					'custom_checkout_data' => $custom_checkout_data,
				],
			]
		);

		return $request;
	}
}
