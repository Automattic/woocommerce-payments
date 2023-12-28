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
use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;

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
	 * @param PaymentContext $context Payment context.
	 */
	public function log_changes( PaymentContext $context ): void {
		$log = 'For order #' . $context->get_order_id() . ' the following changes were made to the payment context: {' . PHP_EOL;

		foreach ( $context->get_transitions() as $transition ) {
			$to_state       = $transition->get_to_state();
			$previous_state = $transition->get_from_state();
			$changes        = $transition->get_changes();
			$time           = gmdate( 'c', $transition->get_timestamp() );

			// Do not log changes within the last state if there were none.
			if ( ! $to_state && empty( $changes ) ) {
				continue;
			}

			if ( $to_state && ! $previous_state ) {
				$label = "Payment initialized in '$to_state'";
			} elseif ( $previous_state && ! $to_state ) {
				$label = "Changes within '$previous_state'";
			} else {
				$label = "Transition from '$previous_state' to '$to_state'";
			}

			$log .= "\t$time $label {" . PHP_EOL;
			$log .= implode( PHP_EOL, $this->changes_to_str( $changes ) ) . PHP_EOL;
			$log .= "\t}" . PHP_EOL;
		}

		$log .= '}';
		$this->logger->debug( $log );
	}

	/**
	 * Returns the changes array as a string that can be logged.
	 *
	 * @param Change[] $changes Array of Change objects.
	 *
	 * @return string[]
	 */
	private function changes_to_str( $changes ): array {
		$changes_string = array_map(
			function ( Change $change ) {
				if ( $change->get_old_value() ) {
					$str = "\t\tChanged " . $change->get_key() . ' from ' . $this->value_to_string( $change->get_old_value() ) .
							' to ' . $this->value_to_string( $change->get_new_value() );
				} else {
					$str = "\t\tSet " . $change->get_key() . ' to ' . $this->value_to_string( $change->get_new_value() );
				}
				return $str;
			},
			$changes
		);
		return $changes_string;
	}

	/**
	 * Converts a value into the right human-readable format.
	 *
	 * @param mixed $value Value to convert to string.
	 * @return string
	 */
	private function value_to_string( $value ) {
		if ( $value instanceof PaymentMethodInterface ) {
			return get_class( $value ) . ' ' . $this->json_encode_with_indent( $value->get_data() );
		}

		return $this->json_encode_with_indent( $value );
	}

	/**
	 * Pretty print the JSON and add tabs for indent.
	 *
	 * @param mixed $value The object to be json encoded and pretty printed.
	 *
	 * @return string
	 */
	private function json_encode_with_indent( $value ): string {
		$str = wp_json_encode( $value, JSON_PRETTY_PRINT );
		$str = preg_replace( '/\n/', "\n\t\t", $str );
		return $str;
	}
}
