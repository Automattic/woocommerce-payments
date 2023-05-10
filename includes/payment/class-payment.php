<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use WCPay\Payment\State\Initial_State;
use WCPay\Payment\State\Payment_State;
use WCPay\Payment\Vars;
use WCPay\Payment_Process\Payment_Method\Payment_Method;
use WC_Order;
use WC_Payments;
use WCPay\Payment\Strategy\Strategy;

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
	 * Instantiates the payment object.
	 *
	 * @param Payment_State $state The state of the payment (optional).
	 */
	public function __construct( Payment_State $state = null ) {
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
	 * Changes the payment state.
	 *
	 * @param Payment_State $state The new state.
	 */
	public function switch_state( Payment_State $state ) {
		printf( "Switching state from %s to %s\n", get_class( $this->state ), get_class( $state ) );
		$this->state = $state;
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
	 * @throws Exception In case the payment has already been verified or is not ready for verification.
	 */
	public function verify() {
		return $this->state->verify();
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
	 * Holds the order, which is/will be paid.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Sets the order, used for the payment.
	 * Orders are required for payments, but set through the order payment factory.
	 *
	 * @see Order_Payment_Factory::load_or_create_order_payment()
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
}
