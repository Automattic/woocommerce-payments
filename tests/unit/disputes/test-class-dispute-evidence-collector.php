<?php
/**
 * Class Dispute_Evidence_Collector_Test.
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Disputes\Dispute_Evidence_Collector;

/**
 * Dispute_Evidence_Collector unit tests.
 */
class Dispute_Evidence_Collector_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_API_Client&MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_Order_Service&MockObject
	 */
	private $mock_order_service;

	/**
	 * @var Dispute_Evidence_Collector
	 */
	private $collector;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client    = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );

		$this->collector = new Dispute_Evidence_Collector(
			$this->mock_api_client,
			$this->mock_order_service
		);
	}

	public function test_collect_returns_expected_shape() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_dispute' )
			->with( 'dp_test_123' )
			->willReturn( $this->make_dispute_fixture() );

		$dto = $this->collector->collect( 'dp_test_123' );

		$this->assertArrayHasKey( 'dispute', $dto );
		$this->assertArrayHasKey( 'charge', $dto );
		$this->assertArrayHasKey( 'order', $dto );
		$this->assertArrayHasKey( 'radar', $dto );
		$this->assertArrayHasKey( 'customer_history', $dto );

		$this->assertSame( 'dp_test_123', $dto['dispute']['id'] );
		$this->assertSame( 'fraudulent', $dto['dispute']['reason'] );
		$this->assertSame( 14200, $dto['dispute']['amount'] );
		$this->assertSame( 'usd', $dto['dispute']['currency'] );
		$this->assertSame( 'needs_response', $dto['dispute']['status'] );
		$this->assertSame( 1715030400, $dto['dispute']['due_by'] );

		$this->assertSame( 'ch_test_123', $dto['charge']['id'] );
		$this->assertSame( 'visa', $dto['charge']['card']['brand'] );
		$this->assertSame( '4242', $dto['charge']['card']['last4'] );
		$this->assertSame( 'credit', $dto['charge']['card']['funding'] );
	}

	public function test_dto_never_contains_pan_or_cvc() {
		$dispute = $this->make_dispute_fixture();

		// Sabotage: try to sneak PII in as if the upstream payload had it.
		$dispute['charge']['payment_method_details']['card']['number']      = '4242424242424242';
		$dispute['charge']['payment_method_details']['card']['cvc']         = '123';
		$dispute['charge']['payment_method_details']['card']['exp_month']   = 4;
		$dispute['charge']['payment_method_details']['card']['exp_year']    = 2030;
		$dispute['charge']['payment_method_details']['card']['fingerprint'] = 'fp_secret';
		$dispute['charge']['payment_method_details']['card']['iin']         = '424242';

		$this->mock_api_client->method( 'get_dispute' )->willReturn( $dispute );

		$dto  = $this->collector->collect( 'dp_test_123' );
		$flat = wp_json_encode( $dto );

		$this->assertIsString( $flat );
		$this->assertStringNotContainsString( '4242424242424242', $flat );
		$this->assertStringNotContainsString( '"cvc"', $flat );
		$this->assertStringNotContainsString( '"number"', $flat );
		$this->assertStringNotContainsString( '"exp_month"', $flat );
		$this->assertStringNotContainsString( '"exp_year"', $flat );
		$this->assertStringNotContainsString( '"fingerprint"', $flat );
		$this->assertStringNotContainsString( 'fp_secret', $flat );
	}

	public function test_collect_returns_null_order_when_dispute_has_no_order() {
		$dispute          = $this->make_dispute_fixture();
		$dispute['order'] = []; // No WC order attached (this is what get_dispute() yields when the charge isn't tied to a WC order).
		$this->mock_api_client->method( 'get_dispute' )->willReturn( $dispute );

		$dto = $this->collector->collect( 'dp_test_123' );

		$this->assertNull( $dto['order'] );
		$this->assertSame( [], $dto['customer_history'] );
	}

	public function test_radar_fields_extracted() {
		$this->mock_api_client->method( 'get_dispute' )->willReturn( $this->make_dispute_fixture() );

		$dto = $this->collector->collect( 'dp_test_123' );

		$this->assertSame( 'elevated', $dto['radar']['risk_level'] );
		$this->assertSame( 75, $dto['radar']['risk_score'] );
		$this->assertSame( 'approved_by_network', $dto['radar']['network_status'] );
		$this->assertSame( 'Payment complete.', $dto['radar']['seller_message'] );
	}

	/**
	 * Builds a representative enriched dispute fixture matching the shape produced by
	 * WC_Payments_API_Client::get_dispute() — i.e. the raw Stripe dispute plus a top-level
	 * 'order' key populated by add_order_info_to_charge_object().
	 *
	 * @return array
	 */
	private function make_dispute_fixture(): array {
		return [
			'id'               => 'dp_test_123',
			'reason'           => 'fraudulent',
			'amount'           => 14200,
			'currency'         => 'usd',
			'status'           => 'needs_response',
			'evidence_details' => [
				'due_by' => 1715030400,
			],
			'charge'           => [
				'id'                     => 'ch_test_123',
				'amount'                 => 14200,
				'currency'               => 'usd',
				'created'                => 1712880000,
				'billing_details'        => [
					'name'    => 'Jane Doe',
					'email'   => 'jane@example.com',
					'address' => [
						'line1'       => '123 Main St',
						'line2'       => '',
						'city'        => 'Brooklyn',
						'state'       => 'NY',
						'postal_code' => '10001',
						'country'     => 'US',
					],
				],
				'shipping'               => [
					'name'    => 'Jane Doe',
					'address' => [
						'line1'       => '123 Main St',
						'city'        => 'Brooklyn',
						'state'       => 'NY',
						'postal_code' => '10001',
						'country'     => 'US',
					],
				],
				'payment_method_details' => [
					'card' => [
						'brand'   => 'visa',
						'last4'   => '4242',
						'funding' => 'credit',
					],
				],
				'outcome'                => [
					'risk_level'     => 'elevated',
					'risk_score'     => 75,
					'network_status' => 'approved_by_network',
					'seller_message' => 'Payment complete.',
					'rule'           => [
						'id' => 'rule_abc',
					],
				],
			],
			// get_dispute() adds top-level 'order' via add_order_info_to_charge_object();
			// this empty array mirrors the "no linked WC order" case.
			'order'            => [],
		];
	}
}
