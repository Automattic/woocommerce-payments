<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WC_Payments_Customer_Service;
use WCPay\Vendor\League\Container\Exception\ContainerException;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Service\OrderService;
use WCPay\Internal\Service\Level3Service;
use WCPay\Internal\Service\PaymentRequestService;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\PaymentRequest;
use WCPay\Internal\Payment\PaymentRequestException;

/**
 * Initial state, representing a freshly created payment.
 */
class InitialState extends AbstractPaymentState {
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
	 * Payment request service.
	 *
	 * @var PaymentRequestService
	 */
	private $payment_request_service;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory                 $state_factory           Factory for payment states.
	 * @param OrderService                 $order_service           Service for order-related actions.
	 * @param WC_Payments_Customer_Service $customer_service        Service for managing remote customers.
	 * @param Level3Service                $level3_service          Service for Level3 Data.
	 * @param PaymentRequestService        $payment_request_service Connection with the server.
	 */
	public function __construct(
		StateFactory $state_factory,
		OrderService $order_service,
		WC_Payments_Customer_Service $customer_service,
		Level3Service $level3_service,
		PaymentRequestService $payment_request_service
	) {
		parent::__construct( $state_factory );

		$this->order_service           = $order_service;
		$this->customer_service        = $customer_service;
		$this->level3_service          = $level3_service;
		$this->payment_request_service = $payment_request_service;
	}

	/**
	 * Initialtes the payment process.
	 *
	 * @param PaymentRequest $request    The incoming payment processing request.
	 * @return CompletedState            The next state.
	 * @throws StateTransitionException  In case the completed state could not be initialized.
	 * @throws ContainerException        When the dependency container cannot instantiate the state.
	 * @throws Order_Not_Found_Exception Order could not be found.
	 * @throws PaymentRequestException   When data is not available or invalid.
	 */
	public function process( PaymentRequest $request ) {
		$context  = $this->get_context();
		$order_id = $context->get_order_id();

		// Populate basic details from the request.
		$this->populate_context_from_request( $request );

		// Populate further details from the order.
		$this->populate_context_from_order();

		// Payments are currently based on intents, request one from the API.
		try {
			$intent = $this->payment_request_service->create_intent( $context );
		} catch ( Invalid_Request_Parameter_Exception | Extend_Request_Exception | Immutable_Parameter_Exception $e ) {
			return $this->create_state( SystemErrorState::class );
		}

		// Intent available, complete processing.
		$this->order_service->update_order_from_successful_intent( $order_id, $intent, $context );

		// If everything went well, transition to the completed state.
		return $this->create_state( CompletedState::class );
	}

	/**
	 * Populates the payment context before processing a payment.
	 *
	 * This method is the link between the payment request, and the payment process.
	 * Use it to make sure that all necessary parameters are provided in advance,
	 * or throw an exception otherwise. Once done, the payment process would rely
	 * on all needed parameters being in place.
	 *
	 * @param PaymentRequest $request The request to use.
	 * @throws PaymentRequestException When data is not available or invalid.
	 */
	protected function populate_context_from_request( PaymentRequest $request ) {
		$context = $this->get_context();

		$context->set_payment_method( $request->get_payment_method() );

		$cvc_confirmation = $request->get_cvc_confirmation();
		if ( ! is_null( $cvc_confirmation ) ) {
			$context->set_cvc_confirmation( $cvc_confirmation );
		}

		$fingerprint = $request->get_fingerprint();
		if ( ! is_null( $fingerprint ) ) {
			$context->set_fingerprint( $fingerprint );
		}
	}

	/**
	 * Populates the context with details, available in the order.
	 * This includes the update/creation of a customer.
	 *
	 * @throws Order_Not_Found_Exception In case the order could not be found.
	 */
	protected function populate_context_from_order() {
		$context  = $this->get_context();
		$order_id = $context->get_order_id();

		// Start by setting up all local objects.
		$this->order_service->import_order_data_to_payment_context( $order_id, $context );
		$context->set_metadata(
			array_merge(
				$this->order_service->get_payment_metadata( $order_id ),
				[ 'gateway_type' => 'src' ]
			)
		);
		$context->set_level3_data( $this->level3_service->get_data_from_order( $order_id ) );

		// Customer management involves a remote call.
		$customer_id = $this->customer_service->get_or_create_customer_id_from_order(
			$context->get_user_id(),
			$this->order_service->_deprecated_get_order( $order_id )
		);
		$context->set_customer_id( $customer_id );
	}
}
