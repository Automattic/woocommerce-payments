<?php
/**
 * Class WC_Payments_API_Charge_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_API_Charge unit tests.
 */
class WC_Payments_API_Charge_Test extends WCPAY_UnitTestCase {
	public function test_payments_api_charge_model_serializes_correctly() {
		$created  = new DateTime();
		$expected = [
			'id'                     => 'ch_mock',
			'amount'                 => 1500,
			'created'                => $created->getTimestamp(),
			'payment_method_details' => [
				'type' => 'card',
				'card' => [
					'brand'       => 'visa',
					'checks'      => [
						'address_line1_check'       => null,
						'address_postal_code_check' => null,
						'cvc_check'                 => null,
					],
					'country'     => 'US',
					'exp_month'   => 1,
					'exp_year'    => 2022,
					'fingerprint' => 'mock',
					'funding'     => 'credit',
					'last4'       => '4242',
					'networks'    => [
						'available' => [],
						'preferred' => null,
					],
					'wallet'      => null,
				],
			],
			'payment_method'         => 'pm_mock',
			'amount_captured'        => 1500,
			'amount_refunded'        => 0,
			'application_fee_amount' => 113,
			'balance_transaction'    => [
				'amount'   => 1500,
				'currency' => 'usd',
				'fee'      => 113,
			],
			'billing_details'        => [],
			'currency'               => 'usd',
			'dispute'                => [],
			'disputed'               => null,
			'order'                  => [
				'number' => 123,
				'url'    => 'https://example.com/order/123',
			],
			'outcome'                => [
				'risk_level' => 'normal',
			],
			'paid'                   => true,
			'paydown'                => [],
			'payment_intent'         => 'pi_mock',
			'captured'               => true,
			'refunded'               => false,
			'refunds'                => [
				'data' => [],
			],
			'status'                 => 'succeeded',
		];

		$charge = new WC_Payments_API_Charge(
			$expected['id'],
			$expected['amount'],
			$created,
			$expected['payment_method_details'],
			$expected['payment_method'],
			$expected['amount_captured'],
			$expected['amount_refunded'],
			$expected['application_fee_amount'],
			$expected['balance_transaction'],
			$expected['billing_details'],
			$expected['currency'],
			$expected['dispute'],
			$expected['disputed'],
			$expected['order'],
			$expected['outcome'],
			$expected['paid'],
			$expected['paydown'],
			$expected['payment_intent'],
			$expected['refunded'],
			$expected['refunds'],
			$expected['status']
		);

		$charge->set_captured( $expected['captured'] );

		$this->assertEquals( $expected, $charge->jsonSerialize() );
	}
}
