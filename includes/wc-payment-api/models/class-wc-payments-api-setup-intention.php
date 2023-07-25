<?php
/**
 * WC_Payments_API_Setup_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * Setup Intent object used by the WooCommerce Payments API.
 *
 * Only add getters and properties existing in setup intent object https://stripe.com/docs/api/setup_intents/object.
 * Otherwise, add them in the (abstract) parent class.
 */
class WC_Payments_API_Setup_Intention extends WC_Payments_API_Abstract_Intention {
	/**
	 * The last payment error of the intention
	 *
	 * @var array
	 */
	private $last_setup_error;

	/**
	 * WC_Payments_API_Setup_Intention constructor.
	 *
	 * @param string      $id                   - ID of the intention.
	 * @param string|null $customer_id          - Stripe ID of the customer.
	 * @param string|null $payment_method_id    - Stripe ID of the payment method.
	 * @param DateTime    $created              - Time charge created.
	 * @param string      $status               - Intention status.
	 * @param string      $client_secret        - The client secret of the intention.
	 * @param array       $next_action          - An array containing information for next action to take.
	 * @param array       $last_setup_error     - An array containing details of any errors.
	 * @param array       $metadata             - An array containing additional metadata of associated charge or order.
	 * @param array       $payment_method_types - An array containing the possible payment methods for the intent.
	 * @param array       $payment_method_options - An array containing the payment method options for the intent.
	 * @param array       $order                - An array containing the order data associated with this intention.
	 */
	public function __construct(
		string $id,
		?string $customer_id,
		?string $payment_method_id,
		DateTime $created,
		string $status,
		string $client_secret,
		array $next_action = [],
		array $last_setup_error = [],
		array $metadata = [],
		array $payment_method_types = [],
		array $payment_method_options = [],
		array $order = []
	) {
		$this->id                     = $id;
		$this->created                = $created;
		$this->status                 = $status;
		$this->client_secret          = $client_secret;
		$this->next_action            = $next_action;
		$this->last_setup_error       = $last_setup_error;
		$this->customer_id            = $customer_id;
		$this->payment_method_id      = $payment_method_id;
		$this->metadata               = $metadata;
		$this->payment_method_types   = $payment_method_types;
		$this->payment_method_options = $payment_method_options;
		$this->order                  = $order;
	}

	/**
	 * Returns the last payment error of this intention
	 *
	 * @return array
	 */
	public function get_last_setup_error(): array {
		return $this->last_setup_error;
	}

	/**
	 * Defines which data will be serialized to JSON
	 */
	public function jsonSerialize(): array {
		return [
			'id'                   => $this->get_id(),
			'created'              => $this->get_created()->getTimestamp(),
			'customer'             => $this->get_customer_id(),
			'metadata'             => $this->get_metadata(),
			'payment_method'       => $this->get_payment_method_id(),
			'payment_method_types' => $this->get_payment_method_types(),
			'status'               => $this->get_status(),
			'order'                => $this->get_order(),
		];
	}
}
