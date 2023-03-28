<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process;

use Exception;
use WCPay\Payment_Process\Storage\Payment_Storage;
use WCPay\Payment_Process\Payment_Method\Payment_Method;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Step;
use WCPay\Payment_Process\Step\Abstract_Step;

/**
 * Main class, representing payments.
 */
class Payment {
	use Payment_Vars;

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
	 * Used for the standard payment flow (non-UPE).
	 */
	const STANDARD_FLOW = 'STANDARD_FLOW';

	/**
	 * Used to check the status of an intent, after SCA authentication.
	 */
	const POST_CHECKOUT_REDIRECT_FLOW = 'POST_CHECKOUT_REDIRECT_FLOW';

	/**
	 * UPE flows:
	 *
	 * 1. The intent is created through AJAX in order to display fields on checkout.
	 *    - This does not happen without a customer present (ex. in-person, merchant-initiated, etc.).
	 * 2. Within `process_payment`, the intent is not ready yet, gets updated.
	 *    - If it's an off-site payment, or a saved PM is used, there is no intent available.
	 *    - Intent is not ready yet, but it's a good opportunity to update it, and use checkout data.
	 * 3. After checkout, there is a redirect, which can finally act on success/failure.
	 */
	const UPE_PREPARE_INTENT_FLOW   = 'UPE_PREPARE_INTENT_FLOW';
	const UPE_PROCESS_PAYMENT_FLOW  = 'UPE_PROCESS_PAYMENT_FLOW';
	const UPE_PROCESS_REDIRECT_FLOW = 'UPE_PROCESS_REDIRECT_FLOW';

	/**
	 * Possible payment stati.
	 *
	 * @todo: Those will need a better description once well determined.
	 */
	const STATUS_PENDING     = 'PENDING';
	const STATUS_SUCCESSFUL  = 'SUCCESSFUL';
	const STATUS_INTERRUPTED = 'INTERRUPTED';
	const STATUS_FAILED      = 'FAILED';

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
	 * Stores the type of payment flow, which triggers the process.
	 *
	 * @var string
	 */
	protected $flow;

	/**
	 * Holds all variables, related to the payment.
	 *
	 * @var array
	 */
	protected $vars = [];

	/**
	 * Holds the response, which should be provided at the end of `process()`.
	 *
	 * @var mixed
	 */
	protected $response;

	/**
	 * Holds the status of the payment.
	 *
	 * @var string
	 */
	protected $status;

	/**
	 * Holds the logger object.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * The current payment processing stage.
	 *
	 * @var string
	 */
	protected $current_stage = 'initialization';

	// Constants, used for `current_stage`.
	const STAGE_PREPARE  = 'preparation';
	const STAGE_ACTION   = 'action';
	const STAGE_COMPLETE = 'completion stage';

	/**
	 * The current step, which is being executed.
	 *
	 * @var Step\Abstract_Step
	 */
	protected $current_step;

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
		// Dependencies.
		$this->payment_storage        = $storage;
		$this->payment_method_factory = $payment_method_factory;

		// Sub-object.
		$this->logger = new Logger();

		// Defaults.
		$this->status = static::STATUS_PENDING;
	}

	/**
	 * Loads the payment from an array.
	 *
	 * @param array $data The pre-existing payment data.
	 */
	public function load_data( array $data ) {
		// Scalar props.
		foreach ( [ 'id', 'flags', 'vars', 'logs', 'status' ] as $key ) {
			if ( isset( $data[ $key ] ) && ! empty( $data[ $key ] ) ) {
				$this->$key = $data[ $key ];
			}
		}

		// Some props need objects.
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
			'id'             => $this->id,
			'flags'          => $this->flags,
			'status'         => $this->status,
			'payment_method' => $payment_method,
			'vars'           => $this->vars,
			'logs'           => $this->logger->get_logs(),
		];
	}

	/**
	 * Saves the payment data in storage.
	 */
	public function save() {
		$this->payment_storage->store( $this );
	}

	/**
	 * Deletes the payment from storage.
	 */
	public function delete() {
		$this->payment_storage->delete( $this );
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
	 * Sets the payment flow, indicating where the process is invoked.
	 *
	 * @param string $flow The flow value, matching a class constant.
	 * @throws Exception If the flow does not exist.
	 */
	public function set_flow( string $flow ) {
		if ( ! defined( get_class( $this ) . '::' . $flow ) ) {
			throw new Exception( 'Payment flows must be defined as constants of the Payment class.' );
		}

		$this->flow = $flow;
	}

	/**
	 * Checks if the payment is a part of a specific flow.
	 *
	 * @param string $flow The flow value.
	 * @return bool
	 */
	public function is_flow( string $flow ) {
		return $flow === $this->flow;
	}

	/**
	 * Retrieves the status of the payment.
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
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
	 * @param Payment_Method $payment_method The used payment method. Optional.
	 */
	public function set_payment_method( Payment_Method $payment_method = null ) {
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
	protected function get_all_available_steps() {
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
				Step\Metadata_Step::class, // Prepare.
				Step\Customer_Details_Step::class, // Prepare & act.
				Step\Bump_Transaction_Limiter_Step::class, // Act & Complete.
				Step\Verify_Fraud_Token_Step::class, // Action.
				Step\Load_Intent_After_Authentication_Step::class, // Action.
				// Step\Check_Session_Against_Processing_Order_Step::class, // Act & Complete.
				Step\Check_Attached_Intent_Success_Step::class, // Action.
				Step\Create_UPE_Intent_Step::class, // Action.
				Step\Redirect_UPE_Payment_Step::class, // Action.
				Step\Update_UPE_Intent_Step::class, // Action.
				Step\Complete_Without_Payment_Step::class, // Action.
				Step\Verify_Minimum_Amount_Step::class, // Action.
				Step\Standard_Payment_Step::class, // Action.
				Step\Setup_Payment_Step::class, // Action.
				Step\Update_Saved_Payment_Method_Step::class, // Complete.
				Step\Save_Payment_Method_Step::class, // Complete.
				Step\Store_Metadata_Step::class, // Complete.
				Step\Update_Order_Step::class, // Complete.
				Step\Add_Token_To_Order_Step::class, // Complete.
			]
		);
	}

	/**
	 * Generates a list of all steps, applicable for the current payment.
	 *
	 * This method is only executed once for a given payment in the context
	 * of a given flow. If the payment is stored, and loaded, it will not
	 * be executed again, as the list of steps will already have been determined.
	 *
	 * @return Abstract_Step[]
	 * @throws \Exception If there is no flow set for the payment.
	 */
	protected function get_steps() {
		// The flow is required, make sure it's set.
		if ( ! $this->flow ) {
			throw new \Exception( 'Processing payments requires a flow to be set' );
		}

		$steps = [];
		foreach ( $this->get_all_available_steps() as $class_name ) {
			// Ignore steps, which do not use the base class.
			if ( ! is_subclass_of( $class_name, Abstract_Step::class ) ) {
				continue;
			}

			// Instantiate the step and check if the step is applicable to the process.
			$step = new $class_name();
			if ( ! $step->is_applicable( $this ) ) {
				continue;
			}

			$steps[] = $step;
		}

		return $steps;
	}

	/**
	 * Processes the payment, once all external set-up is done.
	 *
	 * @return mixed The result of the successful action call.
	 */
	public function process() {
		// Clear any previous responses.
		$this->response = null;

		// Preload all steps, applicable to the process.
		$steps = $this->get_steps();

		// Allow all steps to collect data.
		$this->current_stage = static::STAGE_PREPARE;
		foreach ( $steps as $step ) {
			/**
			 * Stage 1: Collect data.
			 *
			 * This allows each step to collect the necessary data,
			 * and ensure its there before actions start getting performed.
			 */
			$this->run_step( $step, 'collect_data' );
		}

		/**
		 * Stage 2: Act.
		 *
		 * Allow every step to try and process the payment.
		 * Once a single step has called `$payment->complete()`, the
		 * loop will be broken, and no further *actions* will be executed.
		 *
		 * This was `Complete_Without_Payment_Step` can complete the processing,
		 * and other steps (ex. intent creation) will be skipped. Order is important.
		 */
		$this->current_stage = static::STAGE_ACTION;
		foreach ( $steps as $step ) {
			$this->run_step( $step, 'action' );

			// Once there's a response, there should be no further action.
			if ( ! is_null( $this->response ) ) {
				break;
			}
		}

		/**
		 * Stage 3: Complete all steps.
		 *
		 * This allows each step to go ahead and save the necessary data
		 * like payment tokens, meta, update subscriptions and etc.
		 */
		$this->current_stage = static::STAGE_COMPLETE;
		foreach ( $steps as $step ) {
			$this->run_step( $step, 'complete' );
		}

		// Save the payment process as well as all changes.
		$this->save();

		return $this->response;
	}

	/**
	 * Runs the stage-specific method of a particular step.
	 *
	 * @param Abstract_Step $step   The current step.
	 * @param string        $method The method to run.
	 */
	protected function run_step( Abstract_Step $step, string $method ) {
		// Allow logs.
		$this->current_step = $step;

		$this->logger->enter_step( get_class( $step ) . '::' . $method );

		// Call the method.
		call_user_func( [ $step, $method ], $this );

		$this->logger->finish_step( get_class( $step ) . '::' . $method );
	}

	/**
	 * Allows the payment to be completed, ending the main part of the processing.
	 *
	 * Completion steps will still be performed after this call.
	 *
	 * @param mixed  $response The response, which will be provided by `process()` above.
	 * @param string $status   The updated status of the payment (Optional).
	 */
	public function complete( $response, $status = self::STATUS_SUCCESSFUL ) {
		$this->response = $response;
		$this->status   = $status;
	}

	/**
	 * Retrieves variables, set in previous steps.
	 *
	 * @param string $key Key of the value.
	 * @return mixed|null
	 */
	protected function get_var( $key ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
	}

	/**
	 * Allows any step to store variables, related to the process.
	 *
	 * Those variables will be stored with the payment, so using
	 * simple types is strongly recommended.
	 *
	 * Example: Instead of storing a `$user` object here, store
	 * just `$user_id`, and keep the full object in the step.
	 *
	 * @param string $key   Name of the value.
	 * @param mixed  $value Value to set.
	 */
	protected function set_var( $key, $value ) {
		// Prepare the previous value for logs.
		$previous = isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;

		// Store the change, and log it.
		$this->vars[ $key ] = $value;
		$this->logger->var_changed( $key, $previous, $value, $this->current_stage, $this->current_step );
	}
}
