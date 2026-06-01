<?php
/**
 * Refund Charge ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Service\RefundService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/refund-charge` ability.
 *
 * Creates a full or partial refund on a Stripe charge. Destructive (moves
 * money back to the cardholder). Idempotent ONLY when the caller supplies a
 * stable `idempotency_key`; without one the platform generates a fresh key and
 * a duplicate call creates a second refund.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WCPay\Internal\Service\RefundService::refund_charge()
 */
class RefundCharge extends AbstractWCPayAbility implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/refund-charge';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Refund a charge', 'woocommerce-payments' ),
			'description'         => __( 'Create a full or partial refund on a Stripe charge. Supply a stable idempotency_key to make retries safe — identical keys return the original refund. Amount is in minor units (e.g. cents).', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'charge_id' ],
				'properties'           => [
					'charge_id'       => [
						'type'        => 'string',
						'pattern'     => '^(ch_|py_)',
						'description' => __( 'Stripe charge ID to refund (ch_… or py_…).', 'woocommerce-payments' ),
					],
					'amount'          => [
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Amount to refund in minor units (e.g. cents). Omit for a full refund.', 'woocommerce-payments' ),
					],
					'reason'          => [
						'type'        => 'string',
						'enum'        => [ 'duplicate', 'fraudulent', 'requested_by_customer' ],
						'description' => __( 'Optional refund reason.', 'woocommerce-payments' ),
					],
					'idempotency_key' => [
						'type'        => 'string',
						'description' => __( 'Caller-supplied key so duplicate retries dedupe to the original refund.', 'woocommerce-payments' ),
					],
				],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'      => false,
					'destructive'   => true,
					'idempotent'    => true,
					'reversibility' => 0.2,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => false,
				],
			],
		];
	}

	/**
	 * Execute the refund-charge ability.
	 *
	 * @param array<string,mixed> $input Refund parameters.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || ! isset( $input['charge_id'] ) || ! is_string( $input['charge_id'] ) || '' === $input['charge_id'] ) {
			return new \WP_Error(
				'wcpay_missing_charge_id',
				__( 'A charge_id is required to create a refund.', 'woocommerce-payments' )
			);
		}

		$charge_id       = $input['charge_id'];
		$amount          = isset( $input['amount'] ) ? (int) $input['amount'] : null;
		$reason          = isset( $input['reason'] ) && is_string( $input['reason'] ) ? $input['reason'] : null;
		$idempotency_key = isset( $input['idempotency_key'] ) && is_string( $input['idempotency_key'] ) ? $input['idempotency_key'] : null;

		$container = \wcpay_get_container();
		if ( ! $container->has( RefundService::class ) ) {
			return new \WP_Error( 'wcpay_not_initialized', __( 'WooPayments is not initialized.', 'woocommerce-payments' ) );
		}

		return $container->get( RefundService::class )->refund_charge( $charge_id, $amount, $reason, $idempotency_key );
	}
}
