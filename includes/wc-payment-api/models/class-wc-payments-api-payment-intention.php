<?php
/**
 * WC_Payments_API_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * Payment Intent object used by the WooCommerce Payments API.
 *
 * Only add getters and properties existing in payment intent object https://stripe.com/docs/api/payment_intents/object.
 * Otherwise, add them in the (abstract) parent class.
 */
class WC_Payments_API_Payment_Intention extends WC_Payments_API_Abstract_Intention {
	/**
	 * Charge amount
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * The currency of the intention
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * The last payment error of the intention
	 *
	 * @var array
	 */
	private $last_payment_error;

	/**
	 * The latest charge object
	 *
	 * @var WC_Payments_API_Charge
	 */
	private $charge;

	/**
	 * The details on the state of the payment.
	 *
	 * @var array
	 */
	private $processing;

	/**
	 * WC_Payments_API_Intention constructor.
	 *
	 * @param string                 $id                   - ID of the intention.
	 * @param integer                $amount               - Amount charged.
	 * @param string                 $currency             - The currency of the intention.
	 * @param string|null            $customer_id          - Stripe ID of the customer.
	 * @param string|null            $payment_method_id    - Stripe ID of the payment method.
	 * @param DateTime               $created              - Time charge created.
	 * @param string                 $status               - Intention status.
	 * @param string                 $client_secret        - The client secret of the intention.
	 * @param WC_Payments_API_Charge $charge               - An array containing payment method details of associated charge.
	 * @param array                  $next_action          - An array containing information for next action to take.
	 * @param array                  $last_payment_error   - An array containing details of any errors.
	 * @param array                  $metadata             - An array containing additional metadata of associated charge or order.
	 * @param array                  $processing           - An array containing details of the processing state of the payment.
	 * @param array                  $payment_method_types - An array containing the possible payment methods for the intent.
	 * @param array                  $payment_method_options - An array containing the payment method options for the intent.
	 * @param array                  $order                - An array containing the order data associated with this intention.
	 */
	public function __construct(
		$id,
		$amount,
		string $currency,
		$customer_id,
		$payment_method_id,
		DateTime $created,
		$status,
		$client_secret,
		$charge = null,
		$next_action = [],
		$last_payment_error = [],
		$metadata = [],
		$processing = [],
		$payment_method_types = [],
		$payment_method_options = [],
		$order = []
	) {
		$this->id                     = $id;
		$this->amount                 = $amount;
		$this->created                = $created;
		$this->status                 = $status;
		$this->client_secret          = $client_secret;
		$this->currency               = strtoupper( $currency );
		$this->next_action            = $next_action;
		$this->last_payment_error     = $last_payment_error;
		$this->customer_id            = $customer_id;
		$this->payment_method_id      = $payment_method_id;
		$this->charge                 = $charge;
		$this->metadata               = $metadata;
		$this->processing             = $processing;
		$this->payment_method_types   = $payment_method_types;
		$this->payment_method_options = $payment_method_options;
		$this->order                  = $order;
	}

	/**
	 * Gets charge amount
	 *
	 * @return int
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * Returns the currency of this intention
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * Returns the last payment error of this intention
	 *
	 * @return array
	 */
	public function get_last_payment_error() {
		return $this->last_payment_error;
	}

	/**
	 * Returns the charge associated with this intention
	 *
	 * @return WC_Payments_API_Charge
	 */
	public function get_charge() {
		return $this->charge;
	}

	/**
	 * Returns the processing state of this intention
	 *
	 * @return array
	 */
	public function get_processing() {
		return $this->processing;
	}

	/**
	 * Defines which data will be serialized to JSON
	 */
	public function jsonSerialize(): array {
		return [
			'id'                   => $this->get_id(),
			'amount'               => $this->get_amount(),
			'currency'             => $this->get_currency(),
			'charge'               => $this->get_charge(),
			'created'              => $this->get_created()->getTimestamp(),
			'customer'             => $this->get_customer_id(),
			'metadata'             => $this->get_metadata(),
			'payment_method'       => $this->get_payment_method_id(),
			'payment_method_types' => $this->get_payment_method_types(),
			'processing'           => $this->get_processing(),
			'status'               => $this->get_status(),
			'order'                => $this->get_order(),
		];
	}
}
