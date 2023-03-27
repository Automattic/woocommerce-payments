<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WCPay\Payment_Process\Step\Abstract_Step;

/**
 * A helper class for `Payment`, containing logic for logs.
 *
 * This could live in the `Payment` class as well, but we
 * should try to keep the main class undiluted.
 */
class Logger {
	/**
	 * Holds all logs.
	 *
	 * @var mixed[]
	 */
	protected $logs;

	/**
	 * Initializes the logger.
	 *
	 * @param mixed[] $logs The existing logs (Optional).
	 */
	public function __construct( $logs = [] ) {
		$this->logs = $logs;
	}

	/**
	 * Allows the logs to be overwritten.
	 *
	 * @param mixed[] $logs The new logs.
	 */
	public function set_logs( array $logs = null ) {
		if ( ! is_null( $logs ) ) {
			$this->logs = $logs;
		}
	}

	/**
	 * Retrieves all logs.
	 *
	 * @return mixed[]
	 */
	public function get_logs() {
		return $this->logs;
	}

	/**
	 * Logs the change of a variable.
	 *
	 * @param string             $key      Key of the var, which changed.
	 * @param mixed              $previous Previous value, if any.
	 * @param mixed              $value    New value.
	 * @param string             $stage    Stage of the payment.
	 * @param Abstract_Step|null $step     Current step (Optional).
	 */
	public function var_changed( string $key, $previous, $value, string $stage, Abstract_Step $step = null ) {
		// Only log the change, if anything actually changed.
		if ( $previous === $value ) {
			return;
		}

		$log = [
			'event' => is_null( $previous ) ? 'var_set' : 'var_change',
			'stage' => $stage,
		];

		if ( ! is_null( $step ) ) {
			$log['step'] = $step->get_id();
		}

		$log['key'] = $key;

		if ( ! is_null( $previous ) ) {
			$log['previous'] = $previous;
		}

		$log['value'] = $value;

		$this->logs[] = $log;
	}

	/**
	 * Logs that a step has been entered.
	 *
	 * @param string $step The freetext step identifier.
	 */
	public function enter_step( string $step ) {
		$this->logs[] = sprintf( 'Starting %s', $step );
	}

	/**
	 * Logs that a step has been completed.
	 *
	 * @param string $step The freetext step identifier.
	 */
	public function finish_step( string $step ) {
		$this->logs[] = sprintf( 'Finished %s', $step );
	}
}
