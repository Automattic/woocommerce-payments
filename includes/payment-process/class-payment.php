<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Payment_Process\Payment_Method\Payment_Method;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Step\Metadata_Step;
use WCPay\Payment_Process\Step\Abstract_Step;
use WCPay\Payment_Process\Step\Add_Token_To_Order;
use WCPay\Payment_Process\Step\Complete_Without_Payment_Step;
use WCPay\Payment_Process\Step\Customer_Details_Step;
use WCPay\Payment_Process\Step\Store_Metadata_step;

/**
 * Main class, representing payments.
 */
abstract class Payment {
	/**
	 * Indicates if the payment is merchant-initiated.
	 * If the flag is not present, it means that it's a standard
	 * payment, initiated by a customer action on the site.
	 */
	const MERCHANT_INITIATED = 1;

	/**
	 * Indicates if manual capture should be used for the payment.
	 * If the flag is not present, it's automatic capture.
	 */
	const MANUAL_CAPTURE = 2;

	/**
	 * Indicates if this payment will be recurring.
	 * If not, it is just a single payment.
	 *
	 * @todo: Check if this flag is only required for the initial payment.
	 */
	const RECURRING = 4;

	/**
	 * Indicates whether the payment is related to changing
	 * the payment method for a subscription.
	 */
	const CHANGING_SUBSCRIPTION_PAYMENT_METHOD = 8;

	/**
	 * Whether the payment method should be saved upon payment success.
	 */
	const SAVE_PAYMENT_METHOD_TO_STORE = 16;

	/**
	 * Indicates whether the payment method should be saved to the platform.
	 */
	const SAVE_PAYMENT_METHOD_TO_PLATFORM = 32;

	/**
	 * Payment storage, used to store the payment.
	 *
	 * @var Payment_Storage
	 */
	protected $payment_storage;

	/**
	 * Holds the ID of the payment.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Holds all flags, related to the type of payment.
	 *
	 * @var int
	 */
	protected $flags;

	/**
	 * The factory for payment methods.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Holds the payment method.
	 *
	 * @var Payment_Method
	 */
	protected $payment_method;

	/**
	 * Holds all variables, related to the payment.
	 *
	 * @var array
	 */
	protected $vars = [];

	/**
	 * Holds the response, which should be provided at the end.
	 *
	 * @var mixed
	 */
	protected $response;

	/**
	 * Instantiates the class.
	 *
	 * @param Payment_Storage        $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 */
	public function __construct(
		Payment_Storage $storage,
		Payment_Method_Factory $payment_method_factory
	) {
		$this->payment_storage        = $storage;
		$this->payment_method_factory = $payment_method_factory;
	}

	/**
	 * Loads the payment from an array.
	 *
	 * @param array $data The pre-existing payment data.
	 */
	public function load_data( array $data ) {
		if ( isset( $data['flags'] ) ) {
			// $this->flags = $data['flags'];
		}

		if ( isset( $data['payment_method'] ) && ! empty( $data['payment_method'] ) ) {
			$this->payment_method = $this->payment_method_factory->from_storage( $data['payment_method'] );
		}
	}

	/**
	 * Returns the payment data, ready to store.
	 *
	 * @return array An array with everything important.
	 */
	public function get_data() {
		$payment_method = isset( $this->payment_method )
			? $this->payment_method->get_data()
			: null;

		return [
			'flags'          => $this->flags,
			'payment_method' => $payment_method,
		];
	}

	/**
	 * Saves the payment data in storage.
	 */
	public function save() {
		$this->payment_storage->store( $this );
	}

	/**
	 * Allows the ID of the object to be stored.
	 *
	 * @param mixed $id The ID of the payment, used for storage.
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Returns the ID of the payment if any.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Sets a new flag for the payment.
	 *
	 * @param int $flag One of constants of the class.
	 */
	public function set_flag( $flag ) {
		$this->flags = $this->flags | $flag;
	}

	/**
	 * Unsets/removes a specific flag.
	 *
	 * @param int $flag One of constants of the class.
	 */
	public function unset_flag( $flag ) {
		if ( $this->is( $flag ) ) {
			$this->flags = $this->flags - $flag;
		}
	}

	/**
	 * Checks if a specific payment-related flag is present.
	 *
	 * @param int $flag One of constants of the class.
	 */
	public function is( $flag ) {
		return ( $this->flags & $flag ) > 0;
	}

	/**
	 * Changes the payment method, used for the payment.
	 *
	 * @param Payment_Method $payment_method The used payment method.
	 */
	public function set_payment_method( Payment_Method $payment_method ) {
		$this->payment_method = $payment_method;
	}

	/**
	 * Returns the used payment method.
	 *
	 * @return Payment_Method
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns all possible steps, needed for the payment.
	 *
	 * @return string[] An array of class names.
	 */
	protected function get_available_steps() {
		/**
		 * Allows the list of payment steps, and their order to be modified.
		 *
		 * This filter only contains the names of classes for available steps.
		 * Those will be later instantiated, and the process will check whether
		 * the step is applicable or not, hence no further context for this filter.
		 *
		 * @param string[] $steps An array of class names.
		 */
		return apply_filters(
			'wcpay_payment_available_steps',
			[
				Metadata_Step::class, // Prepare.
				Customer_Details_Step::class, // Prepare & act.
				Complete_Without_Payment_Step::class,
				Store_Metadata_Step::class, // Complete.
				Add_Token_To_Order::class, // Complete.
			]
		);
	}

	public function process() {
		// Clear any previous responses.
		$this->response = null;

		$steps = [];

		// Prepare all steps first.
		foreach ( $this->get_available_steps() as $class_name ) {
			if ( ! is_subclass_of( $class_name, Abstract_Step::class ) ) {
				// Ignore steps, which do not use the base class.
				continue;
			}

			$step = new $class_name();

			// Check if the step is applicable to the process.
			if ( ! $step->is_applicable( $this ) ) {
				continue;
			}

			// Let the step collect data.
			$step->collect_data( $this );

			$steps[] = $step;
		}

		// Preparation step done, time to act.
		foreach ( $steps as $step ) {
			$step->action( $this );
			$this->save();

			// Once there's a response, there should be no further action.
			if ( ! is_null( $this->response ) ) {
				break;
			}
		}

		// Action is done, time to cleanup.
		foreach ( $steps as $step ) {
			$step->complete( $this );
			$this->save();
		}

		return $this->response;
	}

	public function set_var( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	public function get_var( $key ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
	}

	/**
	 * Allows the payment to be completed, ending the main part of the processing.
	 *
	 * Completion steps will still be performed after this call.
	 */
	public function complete( $response ) {
		$this->response = $response;
	}
}
