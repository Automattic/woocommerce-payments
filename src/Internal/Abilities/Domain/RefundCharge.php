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
 * money back to the cardholder). Idempotent because `idempotency_key` is
 * REQUIRED: an identical key returns the original refund, so a blind retry
 * cannot create a second refund. (Without a key the platform would generate a
 * fresh one and a duplicate call would double-refund — hence the requirement.)
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
				'required'             => [ 'charge_id', 'idempotency_key' ],
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
						'description' => __( 'Required. A stable, caller-unique key so duplicate retries dedupe to the original refund instead of creating a second one. Reuse the exact same key when retrying the same logical refund.', 'woocommerce-payments' ),
					],
				],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
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

		if ( ! isset( $input['idempotency_key'] ) || ! is_string( $input['idempotency_key'] ) || '' === $input['idempotency_key'] ) {
			return new \WP_Error(
				'wcpay_missing_idempotency_key',
				__( 'An idempotency_key is required so refund retries are safe.', 'woocommerce-payments' )
			);
		}

		$charge_id       = $input['charge_id'];
		$amount          = isset( $input['amount'] ) ? (int) $input['amount'] : null;
		$reason          = isset( $input['reason'] ) && is_string( $input['reason'] ) ? $input['reason'] : null;
		$idempotency_key = isset( $input['idempotency_key'] ) && is_string( $input['idempotency_key'] ) ? $input['idempotency_key'] : null;

		return self::get_refund_service()->refund_charge( $charge_id, $amount, $reason, $idempotency_key );
	}

	/**
	 * Resolve the shared refund service from the container.
	 *
	 * @return RefundService
	 */
	private static function get_refund_service(): RefundService {
		return \wcpay_get_container()->get( RefundService::class );
	}
}
