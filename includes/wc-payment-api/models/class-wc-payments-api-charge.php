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
class WC_Payments_API_Charge {
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
	 * Object that describes the impact of this charge on the account balance
	 *
	 * @var array
	 */
	private $balance_transaction;

	/**
	 * Fee amount
	 *
	 * @var int|null
	 */
	private $application_fee_amount;

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
	 * Customer id
	 *
	 * @var string|null
	 */
	private $customer;

	/**
	 * Flag indicated whether the charge has been disputed or not
	 *
	 * @var bool
	 */
	private $disputed;

	/**
	 * Dispute data
	 *
	 * @var array|null
	 */
	private $dispute;

	/**
	 * Order data
	 *
	 * @var array|null
	 */
	private $order;

	/**
	 * Details of whether and why the payment was accepted
	 *
	 * @var array|null
	 */
	private $outcome;

	/**
	 * Flag indicated whether the charge has been refunded or not
	 *
	 * @var bool
	 */
	private $refunded;

	/**
	 * Refund data
	 *
	 * @var array|null
	 */
	private $refunds;

	/**
	 * Paydown data
	 *
	 * @var array|null
	 */
	private $paydown;

	/**
	 * Payment intent id
	 *
	 * @var string|null
	 */
	private $payment_intent;

	/**
	 * Payment method id
	 *
	 * @var string|null
	 */
	private $payment_method;

	/**
	 * Payment method details object
	 *
	 * @var array
	 */
	private $payment_method_details;

	/**
	 * WC_Payments_API_Charge constructor.
	 *
	 * @param string       $id                     - ID of the charge.
	 * @param integer      $amount                 - Amount charged.
	 * @param DateTime     $created                - Time charge created.
	 * @param array        $balance_transaction    - Object that describes the impact of this charge on the account balance.
	 * @param integer|null $application_fee_amount - Fee amount.
	 * @param array        $billing_details        - Billing information associated with the payment method at the time of the transaction.
	 * @param string|null  $currency               - Charge currency.
	 * @param string|null  $customer               - Customer id.
	 * @param bool         $disputed               - Flag indicated whether the charge has been disputed or not.
	 * @param array|null   $dispute                - Dispute data.
	 * @param array|null   $order                  - Order data.
	 * @param array|null   $outcome                - Details of whether and why the payment was accepted.
	 * @param bool         $refunded               - Flag indicated whether the charge has been refunded or not.
	 * @param array|null   $refunds                - Refund data.
	 * @param array|null   $paydown                - Paydown data.
	 * @param string|null  $payment_intent         - Payment intent id.
	 * @param string|null  $payment_method         - Payment method id.
	 * @param array        $payment_method_details - Payment method details object.
	 */
	public function __construct(
		$id,
		$amount,
		DateTime $created,
		$balance_transaction = null,
		$application_fee_amount = null,
		$billing_details = [],
		$currency = null,
		$customer = null,
		$disputed = false,
		$dispute = null,
		$order = null,
		$outcome = null,
		$refunded = false,
		$refunds = null,
		$paydown = null,
		$payment_intent = null,
		$payment_method = null,
		$payment_method_details = []
	) {
		$this->id                     = $id;
		$this->amount                 = $amount;
		$this->created                = $created;
		$this->balance_transaction    = $balance_transaction;
		$this->application_fee_amount = $application_fee_amount;
		$this->billing_details        = $billing_details;
		$this->currency               = strtoupper( $currency );
		$this->customer               = $customer;
		$this->disputed               = $disputed;
		$this->dispute                = $dispute;
		$this->order                  = $order;
		$this->outcome                = $outcome;
		$this->refunded               = $refunded;
		$this->refunds                = $refunds;
		$this->paydown                = $paydown;
		$this->payment_intent         = $payment_intent;
		$this->payment_method         = $payment_method;
		$this->payment_method_details = $payment_method_details;

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
	 * Returns the balance transaction object associated with this charge
	 *
	 * @return integer|null
	 */
	public function get_balance_transaction() {
		return $this->balance_transaction;
	}

	/**
	 * Returns the application fee amount of this charge
	 *
	 * @return integer|null
	 */
	public function get_application_fee_amount() {
		return $this->application_fee_amount;
	}

	/**
	 * Returns the billing details object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_billing_details() {
		return $this->billing_details;
	}

	/**
	 * Returns the currency of this charge
	 *
	 * @return string|null
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * Returns the customer object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * Returns the boolean indicating if the charge has been disputed
	 *
	 * @return bool
	 */
	public function get_disputed() {
		return $this->disputed;
	}

	/**
	 * Returns the dispute object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_dispute() {
		return $this->dispute;
	}

	/**
	 * Returns the order object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Returns the outcome object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_outcome() {
		return $this->outcome;
	}

	/**
	 * Returns the boolean indicating if the charge has been refunded
	 *
	 * @return bool
	 */
	public function get_refunded() {
		return $this->refunded;
	}

	/**
	 * Returns the refund object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_refunds() {
		return $this->refunds;
	}

	/**
	 * Returns the paydown object associated with this charge
	 *
	 * @return array|null
	 */
	public function get_paydown() {
		return $this->paydown;
	}

	/**
	 * Returns the payment intent ID associated with this charge
	 *
	 * @return string
	 */
	public function get_payment_intent() {
		return $this->payment_intent;
	}

	/**
	 * Returns the payment method ID associated with this charge
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns the payment method details associated with this charge
	 *
	 * @return array
	 */
	public function get_payment_method_details() {
		return $this->payment_method_details;
	}
}
