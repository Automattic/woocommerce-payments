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
	 * @return CompletedState            The next state.
	 * @throws StateTransitionException  In case the completed state could not be initialized.
	 * @throws ContainerException        When the dependency container cannot instantiate the state.
	 * @throws Order_Not_Found_Exception Order could not be found.
	 */
	public function process() {
		$context  = $this->get_context();
		$order_id = $context->get_order_id();

		// Start by setting up all local objects.
		$this->order_service->import_order_data_to_payment_context( $order_id, $context );
		$context->set_metadata( $this->order_service->get_payment_metadata( $order_id ) );
		$context->set_level3_data( $this->level3_service->get_data_from_order( $order_id ) );

		// Customer management involves a remote call.
		$customer_id = $this->customer_service->get_or_create_customer_id_from_order(
			$context->get_user_id(),
			$this->order_service->_deprecated_get_order( $order_id )
		);
		$context->set_customer_id( $customer_id );

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
}
