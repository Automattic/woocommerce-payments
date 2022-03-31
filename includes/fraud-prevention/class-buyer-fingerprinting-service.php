<?php
/**
 * Class Buyer_Fingerprinting_Service
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

/**
 * Class Buyer_Fingerprinting_Service
 */
class Buyer_Fingerprinting_Service {
	/**
	 * Hashes customer data for the fraud prevention.
	 *
	 * @param string $data The data you want to hash.
	 *
	 * @return string Hashed data.
	 */
	public static function hash_data_for_fraud_prevention( $data ) {
		return hash( 'sha512', $data );
	}

	/**
	 * Checks if fraud prevention is enabled for this store.
	 *
	 * @return bool True if store has fraud prevention enabled.
	 */
	public static function is_fraud_prevention_enabled_for_store() {
		return Fraud_Prevention_Service::get_instance()->is_enabled();
	}

	/**
	 * Returns fraud prevention data for an order.
	 *
	 * @param string $order_id The WC order id.
	 *
	 * @return [string] An array of hashed data for an order.
	 */
	public static function get_hashed_data_for_order( $order_id ) {
		$order        = wc_get_order( $order_id );
		$email_domain = explode( '@', $order->get_billing_email(), 2 )[1];

		return [
			'shopper_ip_hash'        => self::hash_data_for_fraud_prevention( $order->get_customer_ip_address() ),
			'shopper_useragent_hash' => self::hash_data_for_fraud_prevention( strtolower( $order->get_customer_user_agent() ) ),
			'shopper_email_hash'     => self::hash_data_for_fraud_prevention( strtolower( $order->get_billing_email() ) ),
			'shopper_email_domain'   => self::hash_data_for_fraud_prevention( strtolower( $email_domain ) ),
		];
	}
}
