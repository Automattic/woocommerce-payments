<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WC_Customer;
use WC_Order;
use WC_Payments_API_Abstract_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\Level3Service;
use WC_Payments_API_Payment_Intention;
use WC_Payments_Customer_Service;

/**
 * Initial state, representing a freshly created payment.
 */
class InitialState extends PaymentState {
	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * Customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * Level3 Data service.
	 *
	 * @var Level3Service
	 */
	private $level3_service;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory                 $state_factory    Factory for payment states.
	 * @param OrderService                 $order_service    Service for order-related actions.
	 * @param WC_Payments_Customer_Service $customer_service Service for managing remote customers.
	 * @param Level3Service                $level3_service   Service for Level3 Data.
	 */
	public function __construct(
		StateFactory $state_factory,
		OrderService $order_service,
		WC_Payments_Customer_Service $customer_service,
		Level3Service $level3_service
	) {
		parent::__construct( $state_factory );

		$this->order_service    = $order_service;
		$this->customer_service = $customer_service;
		$this->level3_service   = $level3_service;
	}

	/**
	 * Initialtes the payment process.
	 *
	 * @return CompletedState The next state.
	 * @throws StateTransitionException Completed state could not be initialized.
	 * @throws Order_Not_Found_Exception Order could not be found.
	 */
	public function process() {
		$context  = $this->get_context();
		$order_id = $context->get_order_id();

		$this->order_service->import_order_data_to_payment_context( $order_id, $context );
		$context->set_metadata( $this->order_service->get_payment_metadata( $order_id ) );

		$customer_id = $this->customer_service->get_or_create_customer_id_from_order(
			$context->get_user_id(),
			$this->order_service->get_order( $order_id )
		);
		$context->set_customer_id( $customer_id );

		// All data has been gathered now. Store whatever we can to avoid it from being lost later.
		// tbd.

		$intent = $this->create_intent();
		$this->order_service->update_order_from_successful_intent( $order_id, $intent, $context );

		return $this->create_state( CompletedState::class );
	}

	/**
	 * Creates a payment intent. To be replaced by an abstraction soon.
	 *
	 * @return WC_Payments_API_Payment_Intention
	 * @throws Invalid_Request_Parameter_Exception
	 */
	private function create_intent() {
		$context = $this->get_context();

		$request = Create_And_Confirm_Intention::create();
		$request->set_amount( $context->get_amount() );
		$request->set_currency_code( $context->get_currency() );
		$request->set_payment_method( $this->get_context()->get_payment_method()->get_id() );
		$request->set_customer( $context->get_customer_id() );
		$request->set_capture_method( $context->should_capture_manually() );
		$request->set_metadata( $context->get_metadata() );
		$request->set_level3( $this->level3_service->get_data_from_order( $context->get_order_id() ) );
		$request->set_payment_methods( [ 'card' ] ); // Initial payment process only supports cards.
		$request->set_cvc_confirmation( $context->get_cvc_confirmation() );
		$request->set_fingerprint( $context->get_fingerprint() );

		return $request->send( 'wcpay_create_and_confirm_intent_request_2' ); // @todo: This is weird.
	}
}
