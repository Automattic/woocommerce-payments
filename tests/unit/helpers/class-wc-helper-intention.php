<?php
/**
 * Intention helpers.
 *
 * @package WooCommerce/Tests
 */

use WCPay\Constants\Intent_Status;

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
				'payment_method'         => 'pm_mock',
				'amount_captured'        => 5000,
				'amount_refunded'        => 0,
				'application_fee_amount' => 0,
				'balance_transaction'    => [
					'id'            => 'txn_mock',
					'amount'        => 5000,
					'available_on'  => 1703808000,
					'created'       => new DateTime( '2022-05-20 19:05:38' ),
					'currency'      => 'usd',
					'exchange_rate' => null,
					'fee'           => 82,
				],
				'billing_details'        => [],
				'currency'               => 'usd',
				'dispute'                => [],
				'disputed'               => false,
				'order'                  => [],
				'outcome'                => [],
				'paid'                   => true,
				'paydown'                => [],
				'payment_intent'         => 'pi_mock',
				'refunded'               => false,
				'refunds'                => [],
				'status'                 => 'succeeded',
			]
		);

		return new WC_Payments_API_Charge(
			$charge_data['id'],
			$charge_data['amount'],
			$charge_data['created'],
			$charge_data['payment_method_details'],
			$charge_data['payment_method'],
			$charge_data['amount_captured'],
			$charge_data['amount_refunded'],
			$charge_data['application_fee_amount'],
			$charge_data['balance_transaction'],
			$charge_data['billing_details'],
			$charge_data['currency'],
			$charge_data['dispute'],
			$charge_data['disputed'],
			$charge_data['order'],
			$charge_data['outcome'],
			$charge_data['paid'],
			$charge_data['paydown'],
			$charge_data['payment_intent'],
			$charge_data['refunded'],
			$charge_data['refunds'],
			$charge_data['status']
		);
	}

	/**
	 * Create a payment intent.
	 *
	 * @param array $data Data to override defaults.
	 * @param bool  $has_charge Whether or not the intention has a charge.
	 *
	 * @return WC_Payments_API_Payment_Intention
	 */
	public static function create_intention( $data = [], $has_charge = true ) {
		$intent_data = wp_parse_args(
			$data,
			[
				'id'                     => 'pi_mock',
				'amount'                 => 5000,
				'currency'               => 'usd',
				'customer_id'            => 'cus_mock',
				'payment_method_id'      => 'pm_mock',
				'status'                 => Intent_Status::SUCCEEDED,
				'client_secret'          => 'cs_mock',
				'charge'                 => [],
				'created'                => new DateTime( '2022-05-20 19:05:38' ),
				'next_action'            => [],
				'last_payment_error'     => [],
				'metadata'               => [],
				'processing'             => [],
				'payment_method_types'   => [],
				'payment_method_options' => [],
			]
		);

		$charge_data = wp_parse_args(
			$intent_data['charge'],
			[
				'payment_intent' => $intent_data['id'],
			]
		);

		$intention = new WC_Payments_API_Payment_Intention(
			$intent_data['id'],
			$intent_data['amount'],
			$intent_data['currency'],
			$intent_data['customer_id'],
			$intent_data['payment_method_id'],
			$intent_data['created'],
			$intent_data['status'],
			$intent_data['client_secret'],
			$has_charge ? self::create_charge( $charge_data ) : null,
			$intent_data['next_action'],
			$intent_data['last_payment_error'],
			$intent_data['metadata'],
			$intent_data['processing'],
			$intent_data['payment_method_types'],
			$intent_data['payment_method_options']
		);

		return $intention;
	}

	/**
	 * Create a setup intent.
	 *
	 * @param array $data Data to override defaults.
	 *
	 * @return WC_Payments_API_Setup_Intention
	 */
	public static function create_setup_intention( $data = [] ): WC_Payments_API_Setup_Intention {
		$intent_data = wp_parse_args(
			$data,
			[
				'id'                     => 'seti_mock',
				'customer_id'            => 'cus_mock',
				'payment_method_id'      => 'pm_mock',
				'status'                 => Intent_Status::SUCCEEDED,
				'client_secret'          => 'cs_mock',
				'created'                => new DateTime( '2022-05-20 19:05:38' ),
				'next_action'            => [],
				'last_setup_error'       => [],
				'metadata'               => [],
				'payment_method_types'   => [],
				'payment_method_options' => [
					'card' => [
						'request_three_d_secure' => 'automatic',
					],
				],
			]
		);

		$intention = new WC_Payments_API_Setup_Intention(
			$intent_data['id'],
			$intent_data['customer_id'],
			$intent_data['payment_method_id'],
			$intent_data['created'],
			$intent_data['status'],
			$intent_data['client_secret'],
			$intent_data['next_action'],
			$intent_data['last_setup_error'],
			$intent_data['metadata'],
			$intent_data['payment_method_types'],
			$intent_data['payment_method_options']
		);

		return $intention;
	}
}
