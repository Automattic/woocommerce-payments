<?php
/**
 * Class WC_REST_Payments_Timeline_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request\Refund_Charge;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the timeline, which includes all events related to an intention.
 */
class WC_REST_Payments_Refunds_Controller extends WC_Payments_REST_Controller {
	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/refund';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_refund' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Refunds a charge. A charge with an order is refunded through the order, so WooCommerce's
	 * checks apply and the refund is recorded on it; one without an order is refunded directly.
	 *
	 * @internal Not intended for usage in integrations or outside of WooCommerce Payments.
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_refund( $request ) {
		$order_id  = $request->get_param( 'order_id' );
		$charge_id = $request->get_param( 'charge_id' );
		$amount    = $request->get_param( 'amount' );
		$reason    = $request->get_param( 'reason' );

		$order = null;
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( false !== $order && $order instanceof WC_Order ) {
				$refund_amount = WC_Payments_Utils::interpret_stripe_amount( $amount, $order->get_currency() );

				// wc_create_refund() rejects this with only "Invalid refund amount.", so say why here.
				if ( $refund_amount > $order->get_remaining_refund_amount() ) {
					return rest_ensure_response( $this->exceeds_order_remaining_error( $order ) );
				}

				$result = $this->process_order_refund( $order, $refund_amount, $reason );
				if ( is_wp_error( $result ) ) {
					return rest_ensure_response( new WP_Error( 'wcpay_refund_payment', $result->get_error_message() ) );
				}
				return rest_ensure_response( $result );
			}
		}

		try {
			return rest_ensure_response( $this->process_charge_refund( $charge_id, $amount, $reason ) );
		} catch ( API_Exception $e ) {
			if ( 'insufficient_balance_for_refund' === $e->get_error_code() && $order instanceof WC_Order ) {
				WC_Payments::get_order_service()->handle_insufficient_balance_for_refund( $order, $amount );
			}
			return rest_ensure_response( new WP_Error( 'wcpay_refund_payment', $e->getMessage() ) );
		}
	}

	/**
	 * Builds the error for a refund larger than what WooCommerce has left to refund on the order.
	 *
	 * @param WC_Order $order The order.
	 * @return WP_Error
	 */
	private function exceeds_order_remaining_error( WC_Order $order ): WP_Error {
		$currency = $order->get_currency();

		return new WP_Error(
			'wcpay_refund_exceeds_order_remaining',
			sprintf(
				/* translators: %1$s: order number, %2$s: amount already refunded, %3$s: amount left to refund */
				__( 'Order #%1$s already has %2$s refunded in WooCommerce, which leaves %3$s to refund. If a refund on the order was recorded without refunding the payment, delete it from the order and refund again.', 'woocommerce-payments' ),
				$order->get_order_number(),
				WC_Payments_Utils::format_currency( (float) $order->get_total_refunded(), $currency ),
				WC_Payments_Utils::format_currency( (float) $order->get_remaining_refund_amount(), $currency )
			),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Process refund for an order.
	 *
	 * @param WC_Order $order  The order to refund.
	 * @param float    $amount Refund amount, in the order's currency.
	 * @param string   $reason Refund reason.
	 * @return WC_Order_Refund|WP_Error
	 */
	private function process_order_refund( WC_Order $order, float $amount, $reason ) {
		return wc_create_refund(
			[
				'amount'         => $amount,
				'reason'         => $reason,
				'order_id'       => $order->get_id(),
				'refund_payment' => true,
				'restock_items'  => true,
			]
		);
	}

	/**
	 * Process refund for a charge.
	 *
	 * @param string $charge_id The charge to refund.
	 * @param int    $amount    Refund amount.
	 * @param string $reason    Refund reason.
	 * @return array
	 */
	private function process_charge_refund( $charge_id, $amount, $reason ) {
		$refund_request = Refund_Charge::create( $charge_id );
		$refund_request->set_charge( $charge_id );
		$refund_request->set_amount( $amount );
		$refund_request->set_full_reason( $reason );
		$refund_request->set_source( 'transaction_details_no_order' );
		return $refund_request->send();
	}
}
