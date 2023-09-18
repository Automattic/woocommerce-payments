<?php
/**
 * Interface ResponseInterface
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\Response;

/**
 * Payment processing response interface, used to
 * describe the response of processing a payment.
 */
interface ResponseInterface {
	/**
	 * Indicates whether the processing the payment went well.
	 * This does not indicate whether the payment is complete yet.
	 *
	 * @return bool
	 */
	public function is_successful(): bool;

	/**
	 * Indicates whether the payment process is complete, and
	 * there are no required additional steps.
	 *
	 * @return bool
	 */
	public function is_complete(): bool;

	/**
	 * Returns the URL where buyers should be redirected.
	 * This could either be the URL (or hash) for the next payment step,
	 * or the URL of the Order Received page if the payment is complete.
	 *
	 * @return string Returns a string if available.
	 */
	public function get_redirect_url(): ?string;

	/**
	 * Returns the customer-facing error message, if any.
	 *
	 * @return string
	 */
	public function get_error_message(): ?string;
}
