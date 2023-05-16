<?php
/**
 * Class Authentication_Required_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Payments;
use WC_Payments_Order_Service;
use WC_Payment_Gateway_WCPay;
use WC_Payments_API_Client;
use WC_Payments_API_Intention;
use WC_Payments_Account;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Intent_Authentication_Exception;
use WCPay\Payment\Payment;
use WCPay\Payment\Strategy\Redirect_If_Action_Is_Required;

/**
 * Indicates that authentication is required before the payment can be processed.
 */
final class Authentication_Required_State extends Payment_State {
	use Redirect_If_Action_Is_Required;

	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Holds the gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Contains the API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		// Load dependencies. @todo not here.
		$this->account             = WC_Payments::get_account_service();
		$this->order_service       = WC_Payments::get_order_service();
		$this->gateway             = WC_Payments::get_gateway();
		$this->payments_api_client = WC_Payments::get_payments_api_client();
	}

	/**
	 * Returns the response from the payment process.
	 *
	 * @return array
	 */
	public function get_response() {
		$intent = $this->context->get_intent();

		if ( ! $intent instanceof WC_Payments_API_Intention ) {
			return []; // Once there is an object for all intents, this can be removed.
		}

		return $this->redirect_if_action_is_required( $this->context, $intent );
	}

	/**
	 * Loads the intent after authentication.
	 *
	 * @param string $intent_id The provided intent ID.
	 */
	public function load_intent_after_authentication( string $intent_id ) {
		// Make sure we're working with a valid intent.
		$this->compare_received_and_stored_intents( $intent_id );

		// Load the payment/setup intent, and make it available for the rest of the process.
		$intent = $this->get_intent_from_server( $intent_id );
		$this->context->set_intent( $intent );

		$successful_status = WC_Payment_Gateway_WCPay::SUCCESSFUL_INTENT_STATUS;
		if ( in_array( $intent->get_status(), $successful_status, true ) ) {
			$this->context->switch_state( new Processed_State( $this->context ) );
		} else {
			$this->context->switch_state( new Processing_Failed_State( $this->context ) );
		}
	}

	/**
	 * Checks that the received intent ID matches the processing order.
	 *
	 * @param string $intent_id The received intent ID.
	 * @throws Intent_Authentication_Exception If something smells fishy.
	 */
	protected function compare_received_and_stored_intents( string $intent_id ) {
		$order = $this->context->get_order();

		// There must be a stored intent ID from `process_payment()`.
		$stored_intent_id = $this->order_service->get_intent_id_for_order( $order );
		if ( empty( $stored_intent_id ) ) {
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'empty_intent_id'
			);
		}

		// Check that the intent saved in the order matches the intent used as part of the
		// authentication process. The ID of the intent used is sent with
		// the AJAX request. We are about to use the status of the intent saved in
		// the order, so we need to make sure the intent that was used for authentication
		// is the same as the one we're using to update the status.
		if ( $stored_intent_id !== $intent_id ) {
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'intent_id_mismatch'
			);
		}
	}

	/**
	 * Loads the intent from the server.
	 *
	 * @param string $intent_id The ID of the intent to load.
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention
	 */
	protected function get_intent_from_server( string $intent_id ) {
		$order = $this->context->get_order();
		if ( $order->get_total() > 0 ) {
			// An exception is thrown if an intent can't be found for the given intent ID.
			$request = Get_Intention::create( $intent_id );
			return $request->send( 'wcpay_get_intent_request', $order );
		} else {
			// For $0 orders, fetch the Setup Intent instead.
			return $this->payments_api_client->get_setup_intent( $intent_id );
		}
	}
}
