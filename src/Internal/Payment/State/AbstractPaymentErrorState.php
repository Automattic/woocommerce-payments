<?php
/**
 * Class AbstractPaymentErrorState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use Exception;
use WCPay\Internal\Logger;
use WCPay\Internal\Payment\Exception\StateTransitionException;
use WCPay\Internal\Service\OrderService;

/**
 * Base abstract class for all error states that will share common error state logic.
 */
abstract class AbstractPaymentErrorState extends AbstractPaymentState {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * Class constructor.
	 *
	 * @param StateFactory $state_factory State factory.
	 * @param Logger       $logger        Logger service.
	 * @param OrderService $order_service Order service.
	 */
	public function __construct( StateFactory $state_factory, Logger $logger, OrderService $order_service ) {
		parent::__construct( $state_factory );
		$this->logger        = $logger;
		$this->order_service = $order_service;
	}

	/**
	 * Create error state function. Almost same as original create state, but it also stores occurred exception.
	 *
	 * @param string    $state_class State class.
	 * @param Exception $exception   Occurred exception that triggered error state change.
	 *
	 * @return string|AbstractPaymentState
	 * @throws StateTransitionException
	 */
	public function create_error_state( string $state_class, $exception ) {
		$context = $this->get_context();
		$context->set_exception( $exception );
		return $this->create_state( $state_class );

	}

	/**
	 * Handle error state.
	 *
	 * @throws Exception
	 */
	public function handle_error_state() {
		$context   = $this->get_context();
		$order_id  = $context->get_order_id();
		$exception = $context->get_exception();
		if ( $this->should_log_error() ) {
			$this->logger->error( "Failed to process order with ID:  $order_id . Reason: " . $exception );
		}

		if ( $this->should_mark_order_as_failed() ) {
			$reason = '';
			if ( $exception ) {
				$reason = $exception->getMessage();
			}
			$this->order_service->mark_order_as_failed( $order_id, $reason );
		}

		// After everything is done, just throw the same exception and that's it. Gateway will pick it up and do its own thing.
		if ( null !== $exception ) {
			throw $exception;
		}
		throw new Exception( __( 'The payment process could not be completed.', 'woocommerce-payments' ) );
	}

	/**
	 * Determines whether an error should be logged.
	 *
	 * @return bool True if the error should be logged, otherwise false.
	 */
	protected function should_log_error(): bool {
		return false;
	}

	/**
	 * Determines whether the order should be marked as failed.
	 *
	 * @return bool True if the order should be marked as failed, otherwise false.
	 */
	protected function should_mark_order_as_failed(): bool {
		return false;
	}
}
