<?php
/**
 * Class SideEffects
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Payments_Order_Service;
use WCPay\Internal\Payment\State\State;

/**
 * Class for managing side effects.
 */
class SideEffects {
	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_Order_Service $order_service Order service.
	 */
	public function __construct( WC_Payments_Order_Service $order_service ) {
		$this->order_service = $order_service;
	}

	/**
	 * Returns all side-effects for a given event.
	 *
	 * @param string $event Events that requires side effects.
	 * @return SideEffect[]
	 */
	private function get_side_effects_for_event( $event ) {
		switch ( $event ) {
			case 'process':
				return [
					new SideEffect( [ $this->order_service, 'maybe_mark_order_paid' ] ),
				];
		}

		return [];
	}

	/**
	 * Triggers side effects.
	 *
	 * @param string $event Name of the event, which triggers side-effects.
	 * @param State  $state Current state.
	 * @return State|null Either a new state (if generated), or null.
	 */
	public function trigger( string $event, State $state ): ?State {
		$side_effects = $this->get_side_effects_for_event( $event );

		foreach ( $side_effects as $effect ) {
			$result = $effect->execute( $state, $state->get_context() );
			if ( ! is_null( $result ) ) {
				return $result;
			}
		}
	}
}
