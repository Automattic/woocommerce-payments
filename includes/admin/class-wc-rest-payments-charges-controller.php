<?php
/**
 * Class WC_REST_Payments_Charges_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Get_Charge;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for charges.
 */
class WC_REST_Payments_Charges_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/charges';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<charge_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_charge' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/order/(?P<order_id>\w+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'generate_charge_from_order' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve charge to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function get_charge( $request ) {
		$charge_id = $request->get_param( 'charge_id' );

		try {
			$wcpay_request = Get_Charge::create( $charge_id );
			$charge        = $wcpay_request->send();
		} catch ( API_Exception $e ) {
			return rest_ensure_response( new WP_Error( 'wcpay_get_charge', $e->getMessage() ) );
		}

		return rest_ensure_response( $charge );
	}

	/**
	 * Generates a charge-like object from an order.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function generate_charge_from_order( $request ) {
		$order_id = $request['order_id'];
		$order    = wc_get_order( $order_id );

		if ( false === $order ) {
			return new WP_Error( 'wcpay_missing_order', __( 'Order not found', 'woocommerce-payments' ), [ 'status' => 404 ] );
		}

		$currency        = $order->get_currency();
		$amount          = WC_Payments_Utils::prepare_amount( $order->get_total(), $currency );
		$billing_details = WC_Payments::get_order_service()->get_billing_data_from_order( $order ); // TODO: Inject order_service after #7464 is fixed.
		$date_created    = $order->get_date_created();
		$intent_id       = $order->get_meta( '_intent_id' );
		$intent_status   = $order->get_meta( '_intent_status' );

		$charge = [
			'id'                     => $order->get_id(),
			'amount'                 => $amount,
			'amount_captured'        => 0,
			'amount_refunded'        => 0,
			'application_fee_amount' => 0,
			'balance_transaction'    => [
				'currency' => $currency,
				'amount'   => $amount,
				'fee'      => 0,
			],
			'billing_details'        => $billing_details,
			'created'                => $date_created ? $date_created->getTimestamp() : null,
			'currency'               => $currency,
			'disputed'               => false,
			'outcome'                => false,
			'order'                  => $this->api_client->build_order_info( $order ),
			'paid'                   => false,
			'paydown'                => null,
			'payment_intent'         => ! empty( $intent_id ) ? $intent_id : null,
			'payment_method_details' => [
				'card' => [
					'country' => $order->get_billing_country(),
					'checks'  => [],
					'network' => '',
				],
				'type' => 'card',
			],
			'refunded'               => false,
			'refunds'                => null,
			'status'                 => ! empty( $intent_status ) ? $intent_status : $order->get_status(),
		];

		$charge = $this->api_client->add_formatted_address_to_charge_object( $charge );

		return rest_ensure_response( $charge );
	}
}
