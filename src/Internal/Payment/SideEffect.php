<?php
/**
 * Class SideEffect
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use Exception;
use ReflectionMethod;
use WCPay\Internal\Payment\State\State;

/**
 * Side effect definition.
 */
class SideEffect {
	/**
	 * Callback.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * Class constructor.
	 *
	 * @param callable $callback The callback to execute. Must return a state.
	 * @throws Exception         In case the callback returns something else.
	 */
	public function __construct( callable $callback ) {
		$reflection = new ReflectionMethod( $callback[0], $callback[1] );
		if ( State::class !== $reflection->getReturnType()->getName() ) {
			throw new Exception( 'Payment side effects must either return a new state, or null.' );
		}

		$this->callback = $callback;
	}

	/**
	 * Executes the side effect.
	 *
	 * @param State   $state   Current state of the payment.
	 * @param Payment $context Context of the state.
	 * @return State|null      Either the next state or nothing.
	 */
	public function execute( State $state, Payment $context ): ?State {
		return call_user_func( $this->callback, $state, $context );
	}
}
