<?php
/**
 * Class Failure
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\Response;

/**
 * Response class for successfully completed payment processing.
 */
final class Failure implements ResponseInterface {
	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Class constructor.
	 *
	 * @param string $message Error message.
	 */
	public function __construct( string $message ) {
		$this->message = $message;
	}

	/**
	 * Indicates that processing the payment did not go well.
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return false;
	}

	/**
	 * Indicates that the payment is not complete yet.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return false;
	}

	/**
	 * Returns the URL where buyers should be redirected.
	 *
	 * @return string Returns a string if available.
	 */
	public function get_redirect_url(): ?string {
		return null;
	}

	/**
	 * Returns the customer-facing error message.
	 *
	 * @return string
	 */
	public function get_error_message(): ?string {
		return $this->message;
	}
}
