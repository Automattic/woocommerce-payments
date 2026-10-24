<?php
/**
 * Accept Dispute ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Service\DisputeService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/accept-dispute` ability.
 *
 * Accepting a dispute concedes it: the funds are refunded to the cardholder
 * and forfeited permanently. Irreversible and destructive. mcp.public is
 * false — never auto-invocable; the caller's policy layer must gate this
 * behind explicit operator approval.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WCPay\Internal\Service\DisputeService::accept()
 */
class AcceptDispute extends AbstractWCPayAbility implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/accept-dispute';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Accept a dispute', 'woocommerce-payments' ),
			'description'         => __( 'Accept (concede) a dispute. The disputed funds are forfeited to the cardholder permanently. This cannot be undone.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'dispute_id' ],
				'properties'           => [
					'dispute_id' => [
						'type'        => 'string',
						'description' => __( 'Stripe dispute ID (typically `du_…` or legacy `dp_…`). Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.', 'woocommerce-payments' ),
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
					'idempotent'  => false,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => false,
				],
			],
		];
	}

	/**
	 * Execute the accept-dispute ability.
	 *
	 * @param array<string,mixed> $input Must contain `dispute_id`.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || ! isset( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) || '' === $input['dispute_id'] ) {
			return new \WP_Error(
				'wcpay_missing_dispute_id',
				__( 'A dispute_id is required to accept a dispute.', 'woocommerce-payments' )
			);
		}

		return self::get_dispute_service()->accept( $input['dispute_id'] );
	}

	/**
	 * Resolve the shared dispute service from the container.
	 *
	 * @return DisputeService
	 */
	private static function get_dispute_service(): DisputeService {
		return \wcpay_get_container()->get( DisputeService::class );
	}
}
