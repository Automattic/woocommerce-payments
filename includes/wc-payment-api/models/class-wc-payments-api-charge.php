<?php
/**
 * WC_Payments_API_Charge class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * A charge object used by the WooCommerce Payments API.
 */
class WC_Payments_API_Charge implements \JsonSerializable {
	/**
	 * Charge ID
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
	 * Flag indicated whether the charge has been captured or not
	 *
	 * @var bool
	 */
	private $captured;

	/**
	 * Payment method details object
	 *
	 * @var array
	 */
	private $payment_method_details;

	/**
	 * Payment method id
	 *
	 * @var string|null
	 */
	private $payment_method;

	/**
	 * Amount in cents captured
	 *
	 * @var int|null
	 */
	private $amount_captured;

	/**
	 * Amount in cents refunded
	 *
	 * @var int|null
	 */
	private $amount_refunded;

	/**
	 * The amount of the application fee requested for the charge
	 *
	 * @var int|null
	 */
	private $application_fee_amount;

	/**
	 * Balance transaction that describes the impact of this charge on the account balance
	 *
	 * @var array
	 */
	private $balance_transaction;

	/**
	 * Billing information associated with the payment method at the time of the transaction
	 *
	 * @var array
	 */
	private $billing_details;

	/**
	 * Charge currency
	 *
	 * @var string|null
	 */
	private $currency;

	/**
	 * Charge dispute object
	 *
	 * @var array
	 */
	private $dispute;

	/**
	 * Flag indicating whether the charge has been disputed or not
	 *
	 * @var bool|null
	 */
	private $disputed;

	/**
	 * Charge order object
	 *
	 * @var array
	 */
	private $order;

	/**
	 * Details about whether the payment was accepted, and why
	 *
	 * @var array
	 */
	private $outcome;

	/**
	 * Flag indicating whether the charge has been paid or not
	 *
	 * @var bool|null
	 */
	private $paid;

	/**
	 * Charge paydown object
	 *
	 * @var array
	 */
	private $paydown;

	/**
	 * Charge payment intent id
	 *
	 * @var string|null
	 */
	private $payment_intent;

	/**
	 * Flag indicating whether the charge has been refunded or not
	 *
	 * @var bool|null
	 */
	private $refunded;

	/**
	 * Charge refunds object
	 *
	 * @var array
	 */
	private $refunds;

	/**
	 * Charge status
	 *
	 * @var string|null
	 */
	private $status;

	/**
	 * WC_Payments_API_Charge constructor.
	 *
	 * @param string      $id                     - ID of the charge.
	 * @param integer     $amount                 - Amount charged.
	 * @param DateTime    $created                - Time charge created.
	 * @param array       $payment_method_details - Payment method details object.
	 * @param string|null $payment_method         - Payment method id.
	 * @param int|null    $amount_captured        - Amount in cents captured.
	 * @param int|null    $amount_refunded        - Amount in cents refunded.
	 * @param int|null    $application_fee_amount - The amount of the application fee requested for the charge.
	 * @param array       $balance_transaction    - Balance transaction that describes the impact of this charge on the account balance.
	 * @param array       $billing_details        - Billing information associated with the payment method at the time of the transaction.
	 * @param string|null $currency               - Charge currency.
	 * @param array       $dispute                - Charge dispute object.
	 * @param bool|null   $disputed               - Flag indicating whether the charge has been disputed or not.
	 * @param array       $order                  - Charge order object.
	 * @param array       $outcome                - Details about whether the payment was accepted, and why.
	 * @param bool|null   $paid                   - Flag indicating whether the charge has been paid or not.
	 * @param array       $paydown                - Charge paydown object.
	 * @param string|null $payment_intent         - Charge payment intent id.
	 * @param bool|null   $refunded               - Flag indicating whether the charge has been refunded or not.
	 * @param array       $refunds                - Charge refunds object.
	 * @param string|null $status                 - Charge status.
	 */
	public function __construct(
		$id,
		$amount,
		DateTime $created,
		$payment_method_details = [],
		$payment_method = null,
		$amount_captured = null,
		$amount_refunded = null,
		$application_fee_amount = null,
		$balance_transaction = [],
		$billing_details = [],
		$currency = null,
		$dispute = [],
		$disputed = null,
		$order = [],
		$outcome = [],
		$paid = null,
		$paydown = [],
		$payment_intent = null,
		$refunded = null,
		$refunds = [],
		$status = null
	) {
		$this->id                     = $id;
		$this->amount                 = $amount;
		$this->created                = $created;
		$this->payment_method_details = $payment_method_details;
		$this->payment_method         = $payment_method;
		$this->amount_captured        = $amount_captured;
		$this->amount_refunded        = $amount_refunded;
		$this->application_fee_amount = $application_fee_amount;
		$this->balance_transaction    = $balance_transaction;
		$this->billing_details        = $billing_details;
		$this->currency               = $currency;
		$this->dispute                = $dispute;
		$this->disputed               = $disputed;
		$this->order                  = $order;
		$this->outcome                = $outcome;
		$this->paid                   = $paid;
		$this->paydown                = $paydown;
		$this->payment_intent         = $payment_intent;
		$this->refunded               = $refunded;
		$this->refunds                = $refunds;
		$this->status                 = $status;

		// Set default properties.
		$this->captured = false;
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
	 * Is the charge captured?
	 *
	 * @return bool
	 */
	public function is_captured() {
		return $this->captured;
	}

	/**
	 * Sets charge captured flag
	 *
	 * @param bool $captured - Flag indicating capture status of charge.
	 */
	public function set_captured( $captured ) {
		$this->captured = $captured;
	}

	/**
	 * Returns the payment method details associated with this charge
	 *
	 * @return array
	 */
	public function get_payment_method_details() {
		return $this->payment_method_details;
	}

	/**
	 * Returns the payment method id associated with this charge
	 *
	 * @return string|null
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns the amount captured associated with this charge
	 *
	 * @return int|null
	 */
	public function get_amount_captured() {
		return $this->amount_captured;
	}

	/**
	 * Returns the amount refunded associated with this charge
	 *
	 * @return int|null
	 */
	public function get_amount_refunded() {
		return $this->amount_refunded;
	}

	/**
	 * Returns the application fee amount associated with this charge
	 *
	 * @return int|null
	 */
	public function get_application_fee_amount() {
		return $this->application_fee_amount;
	}

	/**
	 * Returns the balance transaction associated with this charge
	 *
	 * @return array
	 */
	public function get_balance_transaction() {
		return $this->balance_transaction;
	}

	/**
	 * Returns the billing details associated with this charge
	 *
	 * @return array
	 */
	public function get_billing_details() {
		return $this->billing_details;
	}

	/**
	 * Returns the currency associated with this charge
	 *
	 * @return string|null
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * Returns the dispute object associated with this charge
	 *
	 * @return array
	 */
	public function get_dispute() {
		return $this->dispute;
	}

	/**
	 * Returns the disputed flag associated with this charge
	 *
	 * @return bool|null
	 */
	public function get_disputed() {
		return $this->disputed;
	}

	/**
	 * Returns the order object associated with this charge
	 *
	 * @return array
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Returns the outcome object associated with this charge
	 *
	 * @return array
	 */
	public function get_outcome() {
		return $this->outcome;
	}

	/**
	 * Returns the paid flag associated with this charge
	 *
	 * @return bool|null
	 */
	public function get_paid() {
		return $this->paid;
	}

	/**
	 * Returns the paydown object associated with this charge
	 *
	 * @return array
	 */
	public function get_paydown() {
		return $this->paydown;
	}

	/**
	 * Returns the payment intent id associated with this charge
	 *
	 * @return string|null
	 */
	public function get_payment_intent() {
		return $this->payment_intent;
	}

	/**
	 * Returns the refunded flag associated with this charge
	 *
	 * @return bool|null
	 */
	public function get_refunded() {
		return $this->refunded;
	}

	/**
	 * Returns the refund object associated with this charge
	 *
	 * @return array
	 */
	public function get_refunds() {
		return $this->refunds;
	}

	/**
	 * Returns the status associated with this charge
	 *
	 * @return string|null
	 */
	public function get_status() {
		return $this->status;
	}


	/**
	 * Defines which data will be serialized to JSON
	 */
	public function jsonSerialize(): array {
		return [
			'id'                     => $this->get_id(),
			'amount'                 => $this->get_amount(),
			'created'                => $this->get_created()->getTimestamp(),
			'payment_method_details' => $this->get_payment_method_details(),
			'payment_method'         => $this->get_payment_method(),
			'amount_captured'        => $this->get_amount_captured(),
			'amount_refunded'        => $this->get_amount_refunded(),
			'application_fee_amount' => $this->get_application_fee_amount(),
			'balance_transaction'    => $this->get_balance_transaction(),
			'billing_details'        => $this->get_billing_details(),
			'currency'               => $this->get_currency(),
			'dispute'                => $this->get_dispute(),
			'disputed'               => $this->get_disputed(),
			'order'                  => $this->get_order(),
			'outcome'                => $this->get_outcome(),
			'paid'                   => $this->get_paid(),
			'paydown'                => $this->get_paydown(),
			'payment_intent'         => $this->get_payment_intent(),
			'captured'               => $this->is_captured(),
			'refunded'               => $this->get_refunded(),
			'refunds'                => $this->get_refunds(),
			'status'                 => $this->get_status(),
		];
	}
}
