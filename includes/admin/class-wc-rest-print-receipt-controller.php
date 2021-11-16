<?php
/**
 * Class WC_REST_Print_Receipt_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_REST_Print_Receipt_Controller
 */
class WC_REST_Print_Receipt_Controller extends WC_Payments_REST_Controller {
	/**
	 * Doc rest_base
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/receipts';

	/**
	 * Doc gateway
	 *
	 * @var mixed
	 */
	private $gateway;

	/**
	 * Doc __construct
	 *
	 * @param  mixed $api_client the api client.
	 * @param  mixed $gateway the gateway.
	 * @return void
	 */
	public function __construct( $api_client, $gateway ) {
		parent::__construct( $api_client );

		$this->gateway = $gateway;
	}

	/**
	 * TODO register_routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/generate',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_print_receipt' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * TODO generate_print_receipt
	 *
	 * @return void
	 */
	public function generate_print_receipt() {
		// TODO remove, this endpoint is only for testing issue #3224.
		include_once WCPAY_ABSPATH . 'includes/in-person-payments/class-wc-payments-in-person-payment-receipt-service.php';

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

		$receipt_data = [
			'store'  => [
				'business_name' => 'Store Name',
				'support_info'  => [
					'address' => $support_address,
					'phone'   => $support_phone,
					'email'   => $support_email,
				],
			],
			'order'  => [
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
			],
			'charge' => [
				'amount_captured'        => 68.51,
				'payment_method_details' => [
					'brand'                      => 'Visa',
					'last4'                      => '4242',
					'application_preferred_name' => 'Visa Credit',
					'dedicated_file_name'        => 'A00000000031010',
					'account_type'               => 'credit',
				],
			],
		];

		header( 'Content-Type: text/html; charset=UTF-8' );
		WC_Payments_In_Person_Payment_Receipt_Service::get_receipt_html( $receipt_data );
		exit;
	}
}
