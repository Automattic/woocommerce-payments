<?php
/**
 * Class Buyer_Fingerprinting_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * Buyer_Fingerprinting_Service_Test unit tests.
 */
class Buyer_Fingerprinting_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * Fraud_Prevention_Service mock object.
	 *
	 * @var Fraud_Prevention_Service
	 */
	private $fraud_prevention_service_mock;

	/**
	 * The service under test.
	 *
	 * @var Buyer_Fingerprinting_Service
	 */
	private $buyer_fingerprinting_service;

	public function set_up() {
		parent::set_up();

		$this->fraud_prevention_service_mock = $this->createMock( Fraud_Prevention_Service::class );

		Buyer_Fingerprinting_Service::set_instance( null );
		$this->buyer_fingerprinting_service = Buyer_Fingerprinting_Service::get_instance( $this->fraud_prevention_service_mock );
	}

	public function test_it_hashes_using_sha512() {
		$result                 = $this->buyer_fingerprinting_service->hash_data_for_fraud_prevention( 'test_string' );
		$expected_hashed_string = hash( 'sha512', 'test_string' );

		$this->assertSame( $result, $expected_hashed_string );
	}

	public function test_it_hashes_order_info() {
		$order_hashes          = $this->buyer_fingerprinting_service->get_hashed_data_for_customer();
		$expected_hashed_array = [
			'fraud_prevention_data_shopper_ip_hash' => hash( 'sha512', '127.0.0.1', false ),
			'fraud_prevention_data_shopper_ua_hash' => hash( 'sha512', '', false ),
		];

		$this->assertSame( $order_hashes, $expected_hashed_array );
	}
}
