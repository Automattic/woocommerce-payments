<?php
/**
 * Trait Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

/**
 * A helper trait for `Payment`, containing logic for logs.
 *
 * This could live in the `Payment` class as well, but we
 * should try to keep the main class undiluted.
 */
trait Logger {
	/**
	 * Logs the change of a variable.
	 *
	 * @param string $key      The key of the var, which changed.
	 * @param mixed  $previous The previous value, if any.
	 * @param mixed  $value    The new value.
	 */
	protected function log_var_change( string $key, $previous, $value ) {
		// Only log the change, if anything actually changed.
		if ( $previous === $value ) {
			return;
		}

		$log = [
			'event' => is_null( $previous ) ? 'var_set' : 'var_change',
			'stage' => $this->current_stage,
		];

		if ( ! is_null( $this->current_step ) ) {
			$log['step'] = $this->current_step->get_id();
		}

		$log['key'] = $key;

		if ( ! is_null( $previous ) ) {
			$log['previous'] = $previous;
		}

		$log['value'] = $value;

		$this->logs[] = $log;
	}
}
