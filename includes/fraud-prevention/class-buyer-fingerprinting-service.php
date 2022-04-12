<?php
/**
 * Class Buyer_Fingerprinting_Service
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Geolocation;

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
	 * Returns singleton instance.
	 *
	 * @return Buyer_Fingerprinting_Service
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
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
		return hash( 'sha512', $data, false );
	}

	/**
	 * Returns fraud prevention data for an order.
	 *
	 * @return string[] An array of hashed data for an order.
	 */
	public function get_hashed_data_for_customer(): array {
		return [
			'fraud_prevention_data_shopper_ip_hash' => $this->hash_data_for_fraud_prevention( WC_Geolocation::get_ip_address() ),
			'fraud_prevention_data_shopper_ua_hash' => $this->hash_data_for_fraud_prevention( strtolower( wc_get_user_agent() ) ),
		];
	}
}
