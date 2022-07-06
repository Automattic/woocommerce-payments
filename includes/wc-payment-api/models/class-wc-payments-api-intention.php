<?php
/**
 * WC_Payments_API_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * An intention object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Intention implements \JsonSerializable {
	/**
	 * Intention ID
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Charge amount
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * Time charge created
	 *
	 * Server-side times are presumed to be UTC, (de)serializers should take care to set/respect the timezone on the
	 * DateTime object.
	 *
	 * @var DateTime
	 */
	private $created;

	/**
	 * The status of the intention
	 *
	 * @var string
	 */
	private $status;

	/**
	 * The client secret of the intention
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * The currency of the intention
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * ID of the customer making the payment
	 *
	 * @var string|null
	 */
	private $customer_id;

	/**
	 * ID of the payment method used.
	 *
	 * @var string|null
	 */
	private $payment_method_id;

	/**
	 * The next action needed of the intention
	 *
	 * @var array
	 */
	private $next_action;

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
	 * Set of key-value pairs that can be useful for storing
	 * additional information about the object in a structured format.
	 *
	 * @var array
	 */
	private $metadata;

	/**
	 * WC_Payments_API_Intention constructor.
	 *
	 * @param string                 $id                 - ID of the intention.
	 * @param integer                $amount             - Amount charged.
	 * @param string                 $currency           - The currency of the intention.
	 * @param string|null            $customer_id        - Stripe ID of the customer.
	 * @param string|null            $payment_method_id  - Stripe ID of the payment method.
	 * @param DateTime               $created            - Time charge created.
	 * @param string                 $status             - Intention status.
	 * @param string                 $client_secret      - The client secret of the intention.
	 * @param WC_Payments_API_Charge $charge             - An array containing payment method details of associated charge.
	 * @param array                  $next_action        - An array containing information for next action to take.
	 * @param array                  $last_payment_error - An array containing details of any errors.
	 * @param array                  $metadata           - An array containing additional metadata of associated charge or order.
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
		$metadata = []
	) {
		$this->id                 = $id;
		$this->amount             = $amount;
		$this->created            = $created;
		$this->status             = $status;
		$this->client_secret      = $client_secret;
		$this->currency           = strtoupper( $currency );
		$this->next_action        = $next_action;
		$this->last_payment_error = $last_payment_error;
		$this->customer_id        = $customer_id;
		$this->payment_method_id  = $payment_method_id;
		$this->charge             = $charge;
		$this->metadata           = $metadata;
	}

	/**
	 * Gets charge ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
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
	 * Gets charge created time
	 *
	 * @return DateTime
	 */
	public function get_created() {
		return $this->created;
	}

	/**
	 * Gets intention status
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Returns the client secret associated with this intention
	 *
	 * @return string
	 */
	public function get_client_secret() {
		return $this->client_secret;
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
	 * Returns the customer ID of this intention
	 *
	 * @return string|null
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Returns the payment method ID of this intention
	 *
	 * @return string|null
	 */
	public function get_payment_method_id() {
		return $this->payment_method_id;
	}

	/**
	 * Returns the next action of this intention
	 *
	 * @return array
	 */
	public function get_next_action() {
		return $this->next_action;
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
	 * Returns the metadata associated with this intention
	 *
	 * @return array
	 */
	public function get_metadata() {
		return $this->metadata;
	}

	/**
	 * Defines which data will be serialized to JSON
	 */
	public function jsonSerialize(): array {
		return [
			'id'             => $this->get_id(),
			'amount'         => $this->get_amount(),
			'currency'       => $this->get_currency(),
			'charge'         => $this->get_charge(),
			'created'        => $this->get_created()->getTimestamp(),
			'customer'       => $this->get_customer_id(),
			'metadata'       => $this->get_metadata(),
			'payment_method' => $this->get_payment_method_id(),
			'status'         => $this->get_status(),
		];
	}
}
