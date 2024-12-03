<?php
/**
 * Class LoggerContext
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

/**
 * Logger Context class.
 */
class LoggerContext {
	/**
	 * Request identifier.
	 *
	 * @var string
	 */
	private $request_id;

	/**
	 * Context data.
	 *
	 * @var array<string, string>
	 */
	private $context = [];

	/**
	 * Whether the hooks have been set.
	 *
	 * @var bool
	 */
	private $hooks_set = false;

	/**
	 * Initialises the logger context.
	 *
	 * @return void
	 */
	public function init() {
		$this->request_id = uniqid();
		$this->context    = [];

		$this->setup_hooks();
	}

	/**
	 * Sets a context value.
	 *
	 * @param string                     $key   The key to set.
	 * @param string|int|float|bool|null $value The value to set. Null removes value.
	 *
	 * @return void
	 */
	public function set_value( $key, $value ) {
		if ( null === $value && array_key_exists( $key, $this->context ) ) {
			unset( $this->context[ $key ] );
		} else {
			$this->context[ $key ] = (string) $value;
		}
	}

	/**
	 * Filter the log entry to include the request ID and context.
	 *
	 * @param string $entry   Log entry.
	 * @param array  $context Log entry context.
	 * @return string
	 */
	public function filter_log_entry( $entry, $context ): string {
		$entry_context = is_array( $context ) && array_key_exists( 'context', $context )
			? $context['context']
			: [];
		if ( ! array_key_exists( 'source', $entry_context ) || 'woopayments' !== $entry_context['source'] ) {
			return $entry;
		}

		$time_string   = gmdate( 'c', $context['timestamp'] );
		$level_string  = strtoupper( $context['level'] );
		$format_string = sprintf( '%s %s %s %%s', $time_string, $level_string, $this->request_id );

		$entries = [];

		if ( [] !== $this->context ) {
			$encoded = wp_json_encode( $this->context );
			if ( false !== $encoded ) {
				$entries[] = sprintf( 'CONTEXT: %s', $encoded );
			}
		}

		$entries[] = $context['message'];

		return implode(
			"\n",
			array_map(
				function ( $entry ) use ( $format_string ) {
					return sprintf( $format_string, $entry );
				},
				$entries
			)
		);
	}

	/**
	 * Adds hooks to filter and enhance log entries.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		if ( $this->hooks_set ) {
			return;
		}

		add_filter( 'woocommerce_format_log_entry', [ $this, 'filter_log_entry' ], 10, 2 );
		$this->hooks_set = true;
	}
}
