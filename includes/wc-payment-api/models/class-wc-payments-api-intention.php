<?php
/**
 * WC_Payments_API_Intention class
 *
 * @package WooCommerce\Payments
 */

/**
 * An intention object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Intention {
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
	 * WC_Payments_API_Intention constructor.
	 *
	 * @param string      $id                 - ID of the intention.
	 * @param integer     $amount             - Amount charged.
	 * @param string      $currency           - The currency of the intention.
	 * @param string|null $customer_id        - Stripe ID of the customer.
	 * @param string|null $payment_method_id  - Stripe ID of the payment method.
	 * @param DateTime    $created            - Time charge created.
	 * @param string      $status             - Intention status.
	 * @param string      $charge_id          - ID of charge associated with intention.
	 * @param string      $client_secret      - The client secret of the intention.
	 * @param array       $next_action        - An array containing information for next action to take.
	 * @param array       $last_payment_error - An array containing details of any errors.
	 */
	public function __construct(
		$id,
		$amount,
		string $currency,
		$customer_id,
		$payment_method_id,
		DateTime $created,
		$status,
		$charge_id,
		$client_secret,
		$next_action = [],
		$last_payment_error = []
	) {
		$this->id                 = $id;
		$this->amount             = $amount;
		$this->created            = $created;
		$this->status             = $status;
		$this->charge_id          = $charge_id;
		$this->client_secret      = $client_secret;
		$this->currency           = strtoupper( $currency );
		$this->next_action        = $next_action;
		$this->last_payment_error = $last_payment_error;
		$this->customer_id        = $customer_id;
		$this->payment_method_id  = $payment_method_id;
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
	 * Returns the charge ID associated with this intention
	 *
	 * @return string
	 */
	public function get_charge_id() {
		return $this->charge_id;
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
}
