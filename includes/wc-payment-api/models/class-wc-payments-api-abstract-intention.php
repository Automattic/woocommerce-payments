<?php
/**
 * WC_Payments_API_Abstract_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * An abstract object representing payment and setup intents used by the WooCommerce Payments API.
 */
abstract class WC_Payments_API_Abstract_Intention implements \JsonSerializable {
	/**
	 * Intention ID
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Time charge created
	 *
	 * Server-side times are presumed to be UTC, (de)serializers should take care to set/respect the timezone on the
	 * DateTime object.
	 *
	 * @var DateTime
	 */
	protected $created;

	/**
	 * The status of the intention
	 *
	 * @var string
	 */
	protected $status;

	/**
	 * The client secret of the intention
	 *
	 * @var string
	 */
	protected $client_secret;

	/**
	 * ID of the customer making the payment
	 *
	 * @var string|null
	 */
	protected $customer_id;

	/**
	 * ID of the payment method used.
	 *
	 * @var string|null
	 */
	protected $payment_method_id;

	/**
	 * The next action needed of the intention
	 *
	 * @var array
	 */
	protected $next_action;

	/**
	 * Set of key-value pairs that can be useful for storing
	 * additional information about the object in a structured format.
	 *
	 * @var array
	 */
	protected $metadata;

	/**
	 * The possible payment method types for the payment.
	 *
	 * @var array
	 */
	protected $payment_method_types;

	/**
	 * The order data associated with this intention.
	 *
	 * @var array
	 */
	protected $order;

	/**
	 * Gets charge ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
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
	 * Returns the metadata associated with this intention
	 *
	 * @return array
	 */
	public function get_metadata() {
		return $this->metadata;
	}

	/**
	 * Returns the payment_method_types state of this intention
	 *
	 * @return array
	 */
	public function get_payment_method_types() {
		return $this->payment_method_types;
	}

	/**
	 * Returns the order data associated with this intention
	 *
	 * @return array
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Defines which data will be serialized to JSON
	 */
	abstract public function jsonSerialize(): array;
}
