<?php
/**
 * Intention helpers.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WC_Helper_Intention.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Intention {
	/**
	 * Create a charge.
	 *
	 * @param array $data Data to override defaults.
	 *
	 * @return WC_Payments_API_Charge
	 */
	public static function create_charge( $data = [] ) {
		$charge_data = wp_parse_args(
			$data,
			[
				'id'                     => 'ch_mock',
				'created'                => new DateTime( '2022-05-20 19:05:38' ),
				'amount'                 => 5000,
				'payment_method_details' => [
					'type' => 'card',
					'card' => [
						'network' => 'visa',
						'funding' => 'credit',
					],
				],
			]
		);

		return new WC_Payments_API_Charge(
			$charge_data['id'],
			$charge_data['amount'],
			$charge_data['created'],
			null,
			null,
			[],
			null,
			null,
			false,
			null,
			null,
			null,
			false,
			null,
			null,
			null,
			null,
			$charge_data['payment_method_details']
		);
	}

	/**
	 * Create a payment intent.
	 *
	 * @param array $data Data to override defaults.
	 *
	 * @return WC_Payments_API_Intention
	 */
	public static function create_intention( $data = [] ) {
		$intent_data = wp_parse_args(
			$data,
			[
				'id'                 => 'pi_mock',
				'amount'             => 5000,
				'currency'           => 'usd',
				'customer_id'        => 'cus_mock',
				'payment_method_id'  => 'pm_mock',
				'status'             => 'succeeded',
				'client_secret'      => 'cs_mock',
				'charge'             => [],
				'created'            => new DateTime( '2022-05-20 19:05:38' ),
				'next_action'        => [],
				'last_payment_error' => [],
				'metadata'           => [],
			]
		);

		$intention = new WC_Payments_API_Intention(
			$intent_data['id'],
			$intent_data['amount'],
			$intent_data['currency'],
			$intent_data['customer_id'],
			$intent_data['payment_method_id'],
			$intent_data['created'],
			$intent_data['status'],
			$intent_data['client_secret'],
			self::create_charge( $intent_data['charge'] ),
			$intent_data['next_action'],
			$intent_data['last_payment_error'],
			$intent_data['metadata']
		);

		return $intention;
	}
}
