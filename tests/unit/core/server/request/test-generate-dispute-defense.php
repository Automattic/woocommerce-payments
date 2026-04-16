<?php
/**
 * Class Generate_Dispute_Defense_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Generate_Dispute_Defense;

/**
 * WCPay\Core\Server\Request\Generate_Dispute_Defense unit tests.
 */
class Generate_Dispute_Defense_Test extends WCPAY_UnitTestCase {

	public function test_get_method_and_api() {
		$request = Generate_Dispute_Defense::create();
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DISPUTE_DEFENSE_API, $request->get_api() );
		$this->assertSame( 'dispute_defense/draft', $request->get_api() );
	}

	public function test_set_dispute_id_rejects_invalid_prefix() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request = Generate_Dispute_Defense::create();
		$request->set_dispute_id( 'ch_not_a_dispute' );
	}

	public function test_set_dispute_id_rejects_empty_string() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request = Generate_Dispute_Defense::create();
		$request->set_dispute_id( '' );
	}

	public function test_set_reason_rejects_uppercase_fraudulent() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request = Generate_Dispute_Defense::create();
		$request->set_reason( 'FRAUDULENT' );
	}

	public function test_set_reason_rejects_non_fraudulent() {
		$request = Generate_Dispute_Defense::create();
		try {
			$request->set_reason( 'duplicate' );
			$this->fail( 'Expected Invalid_Request_Parameter_Exception' );
		} catch ( Invalid_Request_Parameter_Exception $e ) {
			$this->assertSame( 'wcpay_dispute_defender_unsupported_reason', $e->get_error_code() );
		}
	}

	public function test_happy_path_accepts_valid_input() {
		$request = Generate_Dispute_Defense::create();
		$request->set_dispute_id( 'dp_test_123' );
		$request->set_reason( 'fraudulent' );
		$request->set_evidence_context( [ 'order_id' => 42 ] );

		$params = $request->get_params();
		$this->assertSame( 'dp_test_123', $params['dispute_id'] );
		$this->assertSame( 'fraudulent', $params['reason'] );
		$this->assertSame( [ 'order_id' => 42 ], $params['evidence_context'] );
	}
}
