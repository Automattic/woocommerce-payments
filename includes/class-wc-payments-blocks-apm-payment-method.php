<?php
/**
 * Class WC_Payments_Blocks_APM_Payment_Method
 *
 * Generic block payment method for WooPayments split APM gateways.
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Generic payment method registration for APM gateways in WooCommerce Blocks.
 *
 * This class allows split APM gateways (e.g., Affirm, Apple Pay, iDEAL) to be
 * registered with the WooCommerce Blocks payment method registry, preventing
 * the "incompatible extensions" warning in the block editor.
 */
class WC_Payments_Blocks_APM_Payment_Method extends AbstractPaymentMethodType {
	/**
	 * The Stripe payment method ID (e.g., 'affirm', 'apple_pay').
	 *
	 * @var string
	 */
	private $payment_method_id;

	/**
	 * The gateway instance for this payment method.
	 *
	 * @var WC_Payment_Gateway_WCPay|false
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param string $payment_method_id The Stripe payment method ID.
	 */
	public function __construct( string $payment_method_id ) {
		$this->payment_method_id = $payment_method_id;
		// Set name in constructor so it's available when register() calls get_name().
		// The name follows the WCPay gateway ID pattern.
		$this->name              = WC_Payment_Gateway_WCPay::GATEWAY_ID . '_' . $payment_method_id;
	}

	/**
	 * Initializes the payment method.
	 */
	public function initialize() {
		$this->gateway = WC_Payments::get_payment_gateway_by_id( $this->payment_method_id );
	}

	/**
	 * Checks whether the gateway is active.
	 *
	 * @return boolean True when active.
	 */
	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	/**
	 * Returns the script handles required for this payment method.
	 *
	 * APM gateways share scripts with the main WooPayments gateway,
	 * which registers the WCPAY_BLOCKS_CHECKOUT script.
	 *
	 * @return string[] A list of script handles.
	 */
	public function get_payment_method_script_handles() {
		// Share scripts with main gateway - already registered by WC_Payments_Blocks_Payment_Method.
		return [ 'WCPAY_BLOCKS_CHECKOUT' ];
	}

	/**
	 * Returns the payment method data to be exposed in JavaScript.
	 *
	 * @return array An associative array containing payment method configuration.
	 */
	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return [];
		}

		return [
			'title'       => $this->gateway->get_option( 'title', '' ),
			'description' => $this->gateway->get_option( 'description', '' ),
			'is_admin'    => is_admin(),
		];
	}
}
