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
	 * Countries where FROD balance is not supported.
	 * Check: https://woocommerce.com/document/woopayments/fees-and-debits/preventing-negative-balances/#supported-countries
	 *
	 * @var array
	 */
	const FROD_UNSUPPORTED_COUNTRIES = [ 'HK', 'SG', 'AE' ];

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
	 * Makes direct refund bypassing any order checks.
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
				$result = $this->process_order_refund( $order, $amount, $reason );
				if ( is_wp_error( $result ) || false === $result ) {
					return rest_ensure_response(
						new WP_Error(
							'wcpay_refund_payment',
							__( 'Failed to create refund', 'woocommerce-payments' )
						)
					);
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
	 * Process refund for an order.
	 *
	 * @param WC_Order $order  The order to refund.
	 * @param int      $amount Refund amount.
	 * @param string   $reason Refund reason.
	 * @return WC_Order_Refund|WP_Error|false
	 */
	private function process_order_refund( WC_Order $order, $amount, $reason ) {
		return wc_create_refund(
			[
				'amount'         => WC_Payments_Utils::interpret_stripe_amount( $amount, $order->get_currency() ),
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
		$refund_request->set_reason( $reason );
		$refund_request->set_source( 'transaction_details_no_order' );
		return $refund_request->send();
	}

	/**
	 * Handle insufficient balance error.
	 *
	 * @param WC_Order $order  The order being refunded.
	 * @param int      $amount The refund amount.
	 */
	private function handle_insufficient_balance_error( WC_Order $order, $amount ) {
		$account         = WC_Payments::get_account_service();
		$account_country = $account->get_account_country();

		$formatted_amount = wc_price(
			WC_Payments_Utils::interpret_stripe_amount( $amount, $order->get_currency() ),
			[ 'currency' => $order->get_currency() ]
		);

		if ( $this->is_frod_supported_country( $account_country ) ) {
			$order->add_order_note( $this->get_frod_support_note( $formatted_amount ) );
		} else {
			$order->add_order_note( $this->get_insufficient_balance_note( $formatted_amount ) );
		}
	}

	/**
	 * Check if FROD is supported for the given country.
	 *
	 * @param string $country_code Two-letter country code.
	 * @return bool
	 */
	private function is_frod_supported_country( $country_code ) {
		return ! in_array(
			$country_code,
			self::FROD_UNSUPPORTED_COUNTRIES,
			true
		);
	}

	/**
	 * Get the order note for FROD supported countries.
	 *
	 * @param string $formatted_amount The formatted refund amount.
	 * @return string
	 */
	private function get_frod_support_note( $formatted_amount ) {
		return sprintf(
			/* translators: %1$s: Formatted refund amount, %2$s: Link to FROD documentation */
			__( 'Refund of %1$s failed due to insufficient funds in your WooPayments balance. To prevent delays in refunding customers, please consider adding funds to your Future Refunds or Disputes (FROD) balance. Learn more: %2$s', 'woocommerce-payments' ),
			$formatted_amount,
			'https://woocommerce.com/document/woopayments/fees-and-debits/preventing-negative-balances/#adding-funds'
		);
	}

	/**
	 * Get the order note for countries without FROD support.
	 *
	 * @param string $formatted_amount The formatted refund amount.
	 * @return string
	 */
	private function get_insufficient_balance_note( $formatted_amount ) {
		return sprintf(
			/* translators: %1$s: Formatted refund amount */
			__( 'Refund of %1$s failed due to insufficient funds in your WooPayments balance.', 'woocommerce-payments' ),
			$formatted_amount
		);
	}
}
