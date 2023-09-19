<?php
/**
 * Class PaymentMethodService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;
use WCPay\Internal\Payment\PaymentMethod\SavedPaymentMethod;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for loading and managing payment methods.
 */
class PaymentMethodService {
	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Service constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy for accessing the core tokens service.
	 */
	public function __construct( LegacyProxy $legacy_proxy ) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Generates a payment method object, based on the data, exported by `get_data()`.
	 *
	 * @param array $data Raw payment method data.
	 * @return PaymentMethodInterface
	 */
	public function get_from_data( array $data ): PaymentMethodInterface {
		$id = $data['id'];

		if ( 'new' === $data['type'] ) {
			return new NewPaymentMethod( $id );
		} else {
			$token = $this->legacy_proxy->call_static( 'WC_Payment_Tokens', 'get', $id );
			return new SavedPaymentMethod( $token );
		}
	}
}
