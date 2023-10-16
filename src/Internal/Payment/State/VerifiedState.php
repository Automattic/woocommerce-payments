<?php
/**
 * Class VerifiedState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WCPay\Constants\Intent_Status;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Service\PaymentRequestService;
use WCPay\Vendor\League\Container\Exception\ContainerException;

/**
 * This state is used to create payment intent and verify next actions based on the intent state.
 */
class VerifiedState extends AbstractPaymentState {

	/**
	 * Payment request service.
	 *
	 * @var PaymentRequestService
	 */
	private $payment_request_service;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory          $state_factory Factory for payment states.
	 * @param PaymentRequestService $payment_request_service Connection with the server.
	 */
	public function __construct(
		StateFactory $state_factory,
		PaymentRequestService $payment_request_service
	) {
		parent::__construct( $state_factory );

		$this->payment_request_service = $payment_request_service;
	}

	/**
	 * Process all needed verifications.
	 *
	 * @return AbstractPaymentState
	 *
	 * @throws Order_Not_Found_Exception
	 * @throws StateTransitionException
	 * @throws ContainerException
	 */
	public function process() {
		$context = $this->get_context();

		// Payments are currently based on intents, request one from the API.
		try {
			$intent = $this->payment_request_service->create_intent( $context );
			$context->set_intent( $intent );
		} catch ( Invalid_Request_Parameter_Exception | Extend_Request_Exception | Immutable_Parameter_Exception $e ) {
			return $this->create_state( SystemErrorState::class );
		}

		// Intent requires authorization (3DS check).
		if ( Intent_Status::REQUIRES_ACTION === $intent->get_status() ) {
			return $this->create_state( AuthenticationRequiredState::class );
		}

		// All good. Proceed to processed state.
		$next_state = $this->create_state( ProcessedState::class );
		return $next_state->complete();
	}
}
