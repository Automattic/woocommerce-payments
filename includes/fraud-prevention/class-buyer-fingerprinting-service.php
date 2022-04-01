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
	 * Fraud prevention service instance.
	 *
	 * @var Fraud_Prevention_Service
	 */
	private $fraud_prevention_service;


	/**
	 * Buyer_Fingerprinting_Service constructor.
	 *
	 * @param Fraud_Prevention_Service $fraud_prevention_service Fraud Prevention instance.
	 */
	public function __construct( Fraud_Prevention_Service $fraud_prevention_service ) {
		$this->fraud_prevention_service = $fraud_prevention_service;
	}

	/**
	 * Returns singleton instance.
	 *
	 * @param null $fraud_prevention_service Fraud Prevention Service instance.
	 * @return Buyer_Fingerprinting_Service
	 */
	public static function get_instance( $fraud_prevention_service = null ): self {
		if ( null === self::$fraud_prevention_service ) {
			self::$fraud_prevention_service = new self( $fraud_prevention_service ?? Fraud_Prevention_Service::get_instance() );
		}

		return self::$fraud_prevention_service;
	}

	/**
	 * Hashes customer data for the fraud prevention.
	 *
	 * @param string $data The data you want to hash.
	 *
	 * @return string Hashed data.
	 */
	public function hash_data_for_fraud_prevention( $data ) {
		return hash( 'sha512', $data );
	}

	/**
	 * Returns fraud prevention data for an order.
	 *
	 * @param string $order_id The WC order id.
	 *
	 * @return string[] An array of hashed data for an order.
	 */
	public function get_hashed_data_for_order( $order_id ) {
		$order = wc_get_order( $order_id );

		return [
			'shopper_ip_hash'        => self::hash_data_for_fraud_prevention( $order->get_customer_ip_address() ),
			'shopper_useragent_hash' => self::hash_data_for_fraud_prevention( strtolower( $order->get_customer_user_agent() ) ),
		];
	}
}
