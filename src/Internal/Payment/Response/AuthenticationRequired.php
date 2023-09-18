<?php
/**
 * Class AuthenticationRequired
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\Response;

/**
 * Response class for successfully completed payment processing.
 */
final class AuthenticationRequired implements ResponseInterface {
	/**
	 * URL string where the buyer should get redirected.
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
	 * Indicates that the payment process is not complete, and
	 * there are required additional steps.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return false;
	}

	/**
	 * Returns the hash where buyers should be redirected.
	 *
	 * @return string Returns a string if available.
	 */
	public function get_redirect_url(): ?string {
		return $this->redirect_url;
	}

	/**
	 * Returns the customer-facing error message, none in this case.
	 *
	 * @return string
	 */
	public function get_error_message(): ?string {
		return null;
	}
}
