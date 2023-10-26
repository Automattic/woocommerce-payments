<?php
/**
 * Class PaymentContextLoggerService
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
class PaymentContextLoggerService {
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
		$log         = 'For order #' . $transitions[0]->get_order_id() . ' the following changes were made to the payment context:' . PHP_EOL;
		foreach ( $transitions as $transition ) {
			$to_state       = $transition->get_to_state();
			$previous_state = $transition->get_from_state();
			if ( $to_state && ! $previous_state ) {
				$log .= 'Payment initialized in "' . $to_state;
			} elseif ( $previous_state && ! $to_state ) {
				$log .= 'Changes within "' . $previous_state;
			} else {
				$log .= 'Transition from "' . $previous_state . '" to "' . $to_state;
			}
			$log .= '" [' . PHP_EOL;
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
					$str = "\t\tChanged " . $change->get_key() . ' from ' . $this->json_encode_with_indent( $change->get_old_value() ) .
							' to ' . $this->json_encode_with_indent( $change->get_new_value() );
				} else {
					$str = "\t\tSet " . $change->get_key() . ' to ' . $this->json_encode_with_indent( $change->get_new_value() );
				}
				return $str;
			},
			$changes
		);
		return $changes_string;
	}

	/**
	 * Pretty print the JSON and add tabs for indent.
	 *
	 * @param mixed $value The object to be json encoded and pretty printed.
	 *
	 * @return string
	 */
	private function json_encode_with_indent( $value ) : string {
		$str = wp_json_encode( $value, JSON_PRETTY_PRINT );
		$str = preg_replace( '/(?<=\n)/', "\t\t", $str );
		return $str;
	}
}
