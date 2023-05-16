<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use Exception;
use WCPay\Payment\State\Initial_State;
use WCPay\Payment\State\Payment_State;
use WCPay\Payment\Vars;
use WCPay\Payment\Payment_Method\Payment_Method;
use WC_Order;
use WC_Payments;
use WCPay\Payment\Strategy\Strategy;
use WCPay\Payment\Storage\Storage_Interface;
use WCPay\Payment\Payment_Method\Payment_Method_Factory;

/**
 * Represents a payment from its inception until eternity.
 */
class Payment {
	use Vars;

	/**
	 * Holds the state of the payment.
	 *
	 * @var Payment_State
	 */
	protected $state;

	/**
	 * Payment storage, used to store the payment.
	 *
	 * @var Storage_Interface
	 */
	protected $storage;

	/**
	 * The factory for payment methods.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

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
	 * @see WCPay\Payment\Flags
	 */
	protected $flags;

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
	 * Holds the order, which is/will be paid.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Instantiates the payment object.
	 *
	 * @param Storage_Interface      $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 * @param Payment_State          $state                  The state of the payment (optional).
	 */
	public function __construct(
		Storage_Interface $storage,
		Payment_Method_Factory $payment_method_factory,
		Payment_State $state = null
	) {
		// Dependencies.
		$this->storage                = $storage;
		$this->payment_method_factory = $payment_method_factory;

		// State.
		$this->state = $state ?? new Initial_State( $this, WC_Payments::get_customer_service() );
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
	 * Retrieves variables from the payment.
	 *
	 * @param string $key Key of the value.
	 * @return mixed|null
	 */
	protected function get_var( $key ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null;
	}

	/**
	 * Stores variables related to the process.
	 *
	 * Those variables will be stored with the payment, so using
	 * simple types is strongly recommended.
	 *
	 * Example: Instead of storing a `$user` object here, store
	 * just `$user_id`, and keep the full object in cache.
	 *
	 * @param string $key   Name of the value.
	 * @param mixed  $value Value to set.
	 */
	protected function set_var( $key, $value ) {
		// Store the change, and log it.
		$this->vars[ $key ] = $value;
	}

	/**
	 * Loads the payment from an array.
	 *
	 * @param array $data The pre-existing payment data.
	 */
	public function load_data( array $data ) {
		// Load the state.
		if ( isset( $data['state'] ) && ! empty( $data['state'] ) ) {
			$this->state = new $data['state']( $this );
		}

		// Scalar props.
		foreach ( [ 'id', 'flags', 'vars' ] as $key ) {
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
			'payment_method' => $payment_method,
			'state'          => get_class( $this->state ),
			'vars'           => $this->vars,
		];
	}

	/**
	 * Stores the payment's data without order association.
	 */
	public function save_without_order() {
		$this->storage->save( $this );
	}

	/**
	 * Stores the payment to the associated order.
	 *
	 * @throws Exception In case there is no order to save the payment to.
	 */
	public function save_to_order() {
		$order = $this->get_order();
		if ( ! $order ) {
			throw new Exception( 'Payment has no order' );
		}

		$this->storage->save_to_order( $this, $order );
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
	 * Sets the order, used for the payment.
	 * Orders are required for payments, but set through the order payment factory.
	 *
	 * @see Payment_Factory::load_or_create_payment()
	 * @param WC_Order $order The order for the payment.
	 */
	public function set_order( WC_Order $order ) {
		$this->order = $order;
	}

	/**
	 * Returns the order, associated with the payment.
	 *
	 * @return WC_Order The order for the payment.
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Changes the payment state.
	 *
	 * @param Payment_State $state The new state.
	 */
	public function switch_state( Payment_State $state ) {
		$this->state = $state;
	}

	/**
	 * Returns the current state of the payment.
	 *
	 * @return Payment_State
	 */
	public function get_state() {
		return $this->state;
	}

	/**
	 * Prepares all required payment details.
	 *
	 * @throws Exception In case the payment has already been prepared.
	 */
	public function prepare() {
		return $this->state->prepare();
	}

	/**
	 * Verifies that the payment should be processed.
	 *
	 * @param Payment_Method $payment_method         Payment method to use for the payment.
	 * @param string         $fraud_prevention_token Verification token to prevent fraud.
	 * @throws Exception In case the payment has already been verified or is not ready for verification.
	 */
	public function verify( Payment_Method $payment_method = null, string $fraud_prevention_token ) {
		return $this->state->verify( $payment_method, $fraud_prevention_token );
	}

	/**
	 * Processes the payment.
	 *
	 * @param  Strategy $strategy Which strategy to use to confirm the payment.
	 * @throws Exception In case the payment has already been processed.
	 */
	public function process( Strategy $strategy ) {
		return $this->state->process( $strategy );
	}

	/**
	 * Loads the intent after authentication.
	 *
	 * @param string $intent_id The provided intent ID.
	 */
	public function load_intent_after_authentication( string $intent_id ) {
		return $this->state->load_intent_after_authentication( $intent_id );
	}

	/**
	 * Loads the intent after authentication.
	 */
	public function complete() {
		return $this->state->complete();
	}

	/**
	 * Either retrieves the existing intent, or creates a new one.
	 *
	 * @param string[] $payment_method_types The allowed payment methods.
	 * @param string   $fingerprint          Anti-fraud fingerprint.
	 * @return array
	 */
	public function get_or_create_intent( array $payment_method_types, string $fingerprint ) {
		return $this->state->get_or_create_intent( $payment_method_types, $fingerprint );
	}

	/**
	 * Updates an intent once an order is available.
	 */
	public function update_intent_with_order() {
		return $this->state->update_intent_with_order();
	}

	/**
	 * Loads an intent into the payment process once it has been confirmed,
	 * and transitions to the correct follow-up state.
	 *
	 * @param string $intent_id The ID of the intent to load.
	 */
	public function load_intent_after_confirmation( string $intent_id ) {
		return $this->state->load_intent_after_confirmation( $intent_id );
	}

	/**
	 * Returns the response from the payment process.
	 *
	 * Typically this is an array with `result` and `redirect`,
	 * but might vary based on the specific state.
	 *
	 * @return array
	 */
	public function get_response() {
		return $this->state->get_response();
	}
}
