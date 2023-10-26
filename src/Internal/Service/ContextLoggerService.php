<?php
/**
 * Class ContextLoggerService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Internal\Logger;
use WCPay\Internal\Payment\PaymentContext;
use WCPay\Internal\Payment\Change;

/**
 * Service for logging payment context.
 */
class ContextLoggerService {
	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;


	/**
	 * Service constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Returns the transitions and changes as a string that can be logged.
	 *
	 * @param PaymentContext $payment_context Payment context.
	 */
	public function log_changes( PaymentContext $payment_context ): void {
		$transitions = $payment_context->get_transitions();
		$log         = '';
		foreach ( $transitions as $transition ) {
			$to_state       = $transition->get_to_state();
			$previous_state = $transition->get_from_state();
			if ( $to_state && ! $previous_state ) {
				$log .= 'Payment for order #' . $transition->get_order_id() . " initialized in '" . $to_state;
			} elseif ( $previous_state && ! $to_state ) {
				$log .= "Changes within '" . $previous_state;
			} else {
				$log .= "Transition from '" . $previous_state . "' to '" . $to_state;
			}
			$log .= "' [" . PHP_EOL;
			$log .= implode( PHP_EOL, $this->changes_to_str( $transition->get_changes() ) ) . PHP_EOL;
			$log .= ']' . PHP_EOL;
		}
		$this->logger->debug( $log );
	}

	/**
	 * Returns the changes array as a string that can be logged.
	 *
	 * @param array $changes Array of Change objects.
	 *
	 * @return array
	 */
	private function changes_to_str( $changes ) : array {
		$changes_string = array_map(
			function( Change $change ) {
				if ( $change->get_old_value() ) {
					$str = 'Changed ' . $change->get_key() . ' from ' . wp_json_encode( $change->get_old_value(), JSON_PRETTY_PRINT ) . ' to ' . wp_json_encode( $change->get_new_value(), JSON_PRETTY_PRINT );
				} else {
					$str = 'Set ' . $change->get_key() . ' to ' . wp_json_encode( $change->get_new_value(), JSON_PRETTY_PRINT );
				}
				return $str;
			},
			$changes
		);
		return $changes_string;
	}
}
