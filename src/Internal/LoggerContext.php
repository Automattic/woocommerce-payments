<?php
/**
 * Class LoggerContext
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal;

use WC_Payments;
use WCPay\Constants\Order_Mode;
use WCPay\Internal\Logger;

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
	 * If context has been initialized.
	 *
	 * @var bool
	 */
	private $context_initialized = false;

	/**
	 * If context has been updated.
	 *
	 * @var bool
	 */
	private $context_updated = false;

	/**
	 * Sequential number for the log entry.
	 *
	 * @var int
	 */
	private $entry_number = 0;

	/**
	 * LoggerContext constructor.
	 */
	public function __construct() {
		$this->request_id   = uniqid();
		$this->entry_number = 0;
	}

	/**
	 * Adds hooks to filter and enhance log entries.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( $this->hooks_set ) {
				return;
		}

		add_filter( 'woocommerce_format_log_entry', [ $this, 'filter_log_entry' ], 10, 2 );
		$this->hooks_set = true;
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
		$this->context_updated = true;
	}

	/**
	 * Returns the context.
	 *
	 * @return array<string, string>
	 */
	public function get_context(): array {
		if ( ! $this->context_initialized ) {
			$this->init_context();
		}
		$this->context_updated = false;
		return $this->context;
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
		if ( ! array_key_exists( 'source', $entry_context ) || Logger::LOG_FILENAME !== $entry_context['source'] ) {
			return $entry;
		}

		$entry_number = ++$this->entry_number;
		$time_string  = gmdate( 'c', $context['timestamp'] );
		$level_string = strtoupper( $context['level'] );
		$line_prefix  = sprintf( '%s %s %s-%04d ', $time_string, $level_string, $this->request_id, $entry_number );

		return $line_prefix . $context['message'];
	}

	/**
	 * Initialises the context.
	 *
	 * @return void
	 */
	private function init_context() {
		$this->set_value( 'WP_USER', is_user_logged_in() ? wp_get_current_user()->user_login : 'Guest (non logged-in user)' );
		$this->set_value( 'HTTP_REFERER', sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '--' ) ) );
		$this->set_value( 'HTTP_USER_AGENT', sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '--' ) ) );
		$this->set_value( 'REQUEST_URI', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '--' ) ) );
		$this->set_value( 'DOING_AJAX', defined( 'DOING_AJAX' ) && DOING_AJAX );
		$this->set_value( 'DOING_CRON', defined( 'DOING_CRON' ) && DOING_CRON );
		$this->set_value( 'WP_CLI', defined( 'WP_CLI' ) && WP_CLI );

		/**
		 * Retrieving the WooPayments mode can only throw an exception if the
		 * plugin is not initialised yet. It is not a testable scenario.
		 */
		// @codeCoverageIgnoreStart
		try {
			$woopayments_mode = WC_Payments::mode()->is_test() ? Order_Mode::TEST : Order_Mode::PRODUCTION;
		} catch ( \Exception $e ) {
			$woopayments_mode = 'N/A';
		}
		// @codeCoverageIgnoreEnd

		$this->set_value( 'WOOPAYMENTS_MODE', $woopayments_mode );

		$this->context_initialized = true;
	}
}
