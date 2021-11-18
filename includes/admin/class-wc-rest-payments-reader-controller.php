<?php
/**
 * Class WC_REST_Payments_Reader_Charges
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for reader charges.
 */
class WC_REST_Payments_Reader_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/readers';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/charges/(?P<transaction_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_summary' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		// TODO Definitive route definition when we implement the API in #3021.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/receipt',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_print_receipt' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Retrieve payment readers charges to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_summary( $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {
			// retrieve transaction details to get the charge date.
			$transaction = $this->api_client->get_transaction( $transaction_id );

			if ( empty( $transaction ) ) {
				return rest_ensure_response( [] );
			}
			$summary = $this->api_client->get_readers_charge_summary( gmdate( 'Y-m-d', $transaction['created'] ) );
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_summary', $e->getMessage() ) );
		}

		return rest_ensure_response( $summary );
	}

	/**
	 * Renders HTML for a print receipt
	 *
	 * @return void
	 */
	public function generate_print_receipt() {
		include_once WCPAY_ABSPATH . 'includes/in-person-payments/class-wc-payments-in-person-payments-receipt-service.php';

		// TODO remove hard-coded values when we implement the final API #3021.
		$support_address = [
			'city'        => 'Happytown',
			'country'     => 'US',
			'line1'       => '1234 Happy Lane',
			'line2'       => 'Line 2',
			'postal_code' => '92929',
			'state'       => 'CA',
		];
		$support_phone   = '+1 (949) 365-5438';
		$support_email   = 'support@storename.com';

		$settings = [
			'business_name' => 'Store Name',
			'support_info'  => [
				'address' => $support_address,
				'phone'   => $support_phone,
				'email'   => $support_email,
			],
		];

		$order = [
			'id'           => 1234,
			'currency'     => 'USD',
			'subtotal'     => 84.50,
			'line_items'   => [
				[
					'name'     => 'Product 1',
					'quantity' => 10,
					'product'  => [
						'id'            => '34243546',
						'price'         => 1,
						'regular_price' => 1,
					],
					'subtotal' => 10,
				],
				[
					'name'     => 'Product 2',
					'quantity' => 2,
					'product'  => [
						'id'            => '34545351',
						'price'         => 1,
						'regular_price' => 2,
					],
					'subtotal' => 2,
				],
				[
					'name'     => 'Product 3',
					'quantity' => 4,
					'product'  => [
						'id'            => '54656573',
						'price'         => 10,
						'regular_price' => 10,
					],
					'subtotal' => 40,
				],
				[
					'name'     => 'Product 4',
					'quantity' => 5,
					'product'  => [
						'id'            => '54656573',
						'price'         => 6.50,
						'regular_price' => 7.50,
					],
					'subtotal' => 32.50,
				],
			],
			'coupon_lines' => [
				[
					'code'        => '25OFF',
					'description' => '25% off',
					'discount'    => 21.13,
				],
			],
			'tax_lines'    => [
				[
					'rate_percent' => 7.75,
					'tax_total'    => 5.14,
				],
			],
			'total'        => 68.51,
		];

		$charge = [
			'amount_captured'        => 68.51,
			'payment_method_details' => [
				'brand'                      => 'Visa',
				'last4'                      => '4242',
				'application_preferred_name' => 'Visa Credit',
				'dedicated_file_name'        => 'A00000000031010',
				'account_type'               => 'credit',
			],
		];

		header( 'Content-Type: text/html; charset=UTF-8' );
		WC_Payments_In_Person_Payments_Receipt_Service::render_receipt( $settings, $order, $charge );
		exit;
	}

}
