<?php
/**
 * Class file for WCPay\Core\Server\Request\Update_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;
use WCPay\Payment_Methods\CC_Payment_Method;
use WCPay\Payment_Methods\Link_Payment_Method;

/**
 * Request class for creating intents.
 */
class Update_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'amount' ];
	const REQUIRED_PARAMS  = [ 'amount', 'currency' ];
	const DEFAULT_PARAMS   = [
		'receipt_email' => '',
		'metadata'      => [],
	];

	/**
	 * Intent id.
	 *
	 * @var string|null $intent_id
	 */
	private $intent_id = null;

	/**
	 * Set intent id.
	 *
	 * @param string $intent_id Intent id.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_intent_id( string $intent_id ) {
		$this->validate_stripe_id( $intent_id, [ 'pi' ] );

		// Prevent mutation of intent id. It can be only set once.
		if ( null === $this->intent_id ) {
			$this->intent_id = $intent_id;
		}
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		if ( null === $this->intent_id ) {
			throw new Invalid_Request_Parameter_Exception( __( 'Intent ID is not set.', 'woocommerce-payments' ), 'wcpay_core_request_intent_not_set' );
		}
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->intent_id;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * If the payment method should be saved to the store, this enables future usage.
	 */
	public function setup_future_usage() {
		$this->set_param( 'setup_future_usage', 'off_session' );
	}

	/**
	 * Customer setter.
	 *
	 * @param string $customer_id ID of the customer making the payment.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_customer( string $customer_id ) {
		$this->validate_stripe_id( $customer_id, 'cus' );
		$this->set_param( 'customer', $customer_id );
	}

	/**
	 * Stores the amount for the intent.
	 *
	 * @param int $amount The amount in ToDo units.
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_amount( int $amount ) {
		$this->validate_is_larger_then( $amount, 0 );
		$this->set_param( 'amount', $amount );
	}

	/**
	 * Currency code setter.
	 *
	 * @param  string $currency_code Currency to charge in.
	 * @throws Invalid_Request_Parameter_Exception When the currency code is invalid.
	 */
	public function set_currency_code( string $currency_code ) {
		$this->validate_currency_code( $currency_code );
		$this->set_param( 'currency', $currency_code );
	}

	/**
	 * Set selected UPE payment method type.
	 *
	 * @param string $selected_upe_payment_type Selected UPE payment method.
	 * @param array  $enabled_payment_methods Enabled payment methods.
	 *
	 * @return void
	 */
	public function set_selected_upe_payment_method_type( string $selected_upe_payment_type, array $enabled_payment_methods ) {
		if ( '' !== $selected_upe_payment_type ) {
			// Only update the payment_method_types if we have a reference to the payment type the customer selected.
			$payment_methods = [ $selected_upe_payment_type ];

			if ( CC_Payment_Method::PAYMENT_METHOD_STRIPE_ID === $selected_upe_payment_type ) {
				$is_link_enabled = in_array(
					Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID,
					$enabled_payment_methods,
					true
				);
				if ( $is_link_enabled ) {
					$payment_methods[] = Link_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
				}
			}
			$this->set_param( 'payment_method_types', $payment_methods );
		}
	}

	/**
	 * Set payment country.
	 *
	 * @param string $payment_country Set payment country.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function set_payment_country( string $payment_country ) {
		if ( $payment_country && ! WC_Payments::mode()->is_dev() ) {
			// Do not update on dev mode, Stripe tests cards don't return the appropriate country.
			$this->set_param( 'payment_country', $payment_country );
		}
	}

	/**
	 * Metadata setter.
	 *
	 * @param  array $metadata Meta data values to be sent along with payment intent creation.
	 */
	public function set_metadata( $metadata ) {
		// The description is based on the order number here.
		$description = $this->get_intent_description( $metadata['order_number'] ?? 0 );
		$this->set_param( 'description', $description );

		// Combine the metadata with the fingerprint.
		$metadata = array_merge( $metadata, $this->get_fingerprint_metadata() );
		$this->set_param( 'metadata', $metadata );
	}

	/**
	 * Level 3 data setter.
	 *
	 * @param array $level3 Level 3 data.
	 */
	public function set_level3( $level3 ) {
		if ( empty( $level3 ) || ! is_array( $level3 ) ) {
			return;
		}

		$this->set_param( 'level3', $this->fix_level3_data( $level3 ) );
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return WC_Payments::get_payments_api_client()->deserialize_intention_object_from_array( $response );
	}
}
