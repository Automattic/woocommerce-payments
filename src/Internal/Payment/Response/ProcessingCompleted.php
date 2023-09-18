<?php
/**
 * Class ProcessingCompleted
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\Response;

/**
 * Response class for successfully completed payment processing.
 */
final class ProcessingCompleted implements ResponseInterface {
	/**
	 * URL to to redirect to after processing.
	 *
	 * @var string
	 */
	private $redirect_url;

	/**
	 * Class constructor.
	 *
	 * @param string $redirect_url Where the buyer should be redirected.
	 */
	public function __construct( string $redirect_url ) {
		$this->redirect_url = $redirect_url;
	}

	/**
	 * Indicates that the processing the payment went well.
	 * This does not indicate whether the payment is complete yet.
	 *
	 * @return bool
	 */
	public function is_successful(): bool {
		return true;
	}

	/**
	 * Indicates that the payment process is complete.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return true;
	}

	/**
	 * Returns the URL where buyers should be redirected.
	 * This would be the URL of the Order Received page.
	 *
	 * @return string Returns a string if available.
	 */
	public function get_redirect_url(): ?string {
		return $this->redirect_url;
	}

	/**
	 * Returns the customer-facing error message, though this response has none.
	 *
	 * @return string
	 */
	public function get_error_message(): ?string {
		return null;
	}
}
