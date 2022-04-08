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
	 * Singleton instance.
	 *
	 * @var Buyer_Fingerprinting_Service
	 */
	private static $instance;

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
		if ( null === self::$instance ) {
			self::$instance = new self( $fraud_prevention_service ?? Fraud_Prevention_Service::get_instance() );
		}

		return self::$instance;
	}

	/**
	 * Sets a instance to be used in request cycle.
	 * Introduced primarily for supporting unit tests.
	 *
	 * @param Buyer_Fingerprinting_Service|null $instance Instance of self.
	 */
	public static function set_instance( self $instance = null ) {
		self::$instance = $instance;
	}

	/**
	 * Hashes customer data for the fraud prevention.
	 *
	 * @param string $data The data you want to hash.
	 *
	 * @return string Hashed data.
	 */
	public function hash_data_for_fraud_prevention( string $data ): string {
		return hash( 'sha512', $data );
	}

	/**
	 * Returns fraud prevention data for an order.
	 *
	 * @param int $order_id The WC order id.
	 *
	 * @return string[] An array of hashed data for an order.
	 */
	public function get_hashed_data_for_order( int $order_id ): array {
		$order = wc_get_order( $order_id );

		return [
			'shopper_ip_hash'        => self::hash_data_for_fraud_prevention( $order->get_customer_ip_address() ),
			'shopper_useragent_hash' => self::hash_data_for_fraud_prevention( strtolower( $order->get_customer_user_agent() ) ),
		];
	}
}
