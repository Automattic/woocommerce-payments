<?php
/**
 * Submit Dispute Evidence ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Service\DisputeService;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/submit-dispute-evidence` ability.
 *
 * Two-phase: with `submit=false` (the default) evidence is staged as a draft
 * and can be revised; with `submit=true` the evidence is sent to the card
 * network and CANNOT be changed afterward. Not idempotent — each submit is a
 * fresh submission.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WCPay\Internal\Service\DisputeService::submit_evidence()
 */
class SubmitDisputeEvidence extends AbstractWCPayAbility implements AbilityDefinition {

	/**
	 * Canonical Stripe dispute-evidence fields (all string-valued; document
	 * fields carry an uploaded file ID).
	 *
	 * @var string[]
	 */
	private const EVIDENCE_FIELDS = [
		'product_description',
		'customer_communication',
		'customer_signature',
		'customer_purchase_ip',
		'receipt',
		'refund_policy',
		'duplicate_charge_documentation',
		'shipping_documentation',
		'service_documentation',
		'cancellation_policy',
		'cancellation_rebuttal',
		'access_activity_log',
		'uncategorized_file',
		'uncategorized_text',
		'shipping_carrier',
		'shipping_date',
		'shipping_tracking_number',
		'shipping_address',
	];

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/submit-dispute-evidence';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		$evidence_properties = [];
		foreach ( self::EVIDENCE_FIELDS as $field ) {
			$evidence_properties[ $field ] = [ 'type' => 'string' ];
		}

		return [
			'label'               => __( 'Submit dispute evidence', 'woocommerce-payments' ),
			'description'         => __( 'Stage or submit evidence for a dispute. With submit=false (default) the evidence is saved as a draft you can revise; with submit=true it is sent to the card network and cannot be changed.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'dispute_id' ],
				'properties'           => [
					'dispute_id' => [
						'type'        => 'string',
						'pattern'     => '^dp_',
						'description' => __( 'Dispute ID (dp_…).', 'woocommerce-payments' ),
					],
					'evidence'   => [
						'type'                 => 'object',
						'description'          => __( 'Evidence fields. Document fields take an uploaded file ID.', 'woocommerce-payments' ),
						'properties'           => $evidence_properties,
						'additionalProperties' => false,
					],
					'submit'     => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to submit to the card network (irreversible). Default false stages a draft.', 'woocommerce-payments' ),
					],
					'metadata'   => [
						'type'        => 'object',
						'description' => __( 'Optional metadata to attach to the dispute.', 'woocommerce-payments' ),
					],
				],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'      => false,
					'destructive'   => false,
					'idempotent'    => false,
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
	 * Execute the submit-dispute-evidence ability.
	 *
	 * @param array<string,mixed> $input Evidence parameters.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || ! isset( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) || '' === $input['dispute_id'] ) {
			return new \WP_Error(
				'wcpay_missing_dispute_id',
				__( 'A dispute_id is required to submit evidence.', 'woocommerce-payments' )
			);
		}

		$dispute_id = $input['dispute_id'];
		$evidence   = ( isset( $input['evidence'] ) && is_array( $input['evidence'] ) ) ? $input['evidence'] : [];
		$submit     = ! empty( $input['submit'] );
		$metadata   = ( isset( $input['metadata'] ) && is_array( $input['metadata'] ) ) ? $input['metadata'] : [];

		$container = \wcpay_get_container();
		if ( ! $container->has( DisputeService::class ) ) {
			return new \WP_Error( 'wcpay_not_initialized', __( 'WooPayments is not initialized.', 'woocommerce-payments' ) );
		}

		return $container->get( DisputeService::class )->submit_evidence( $dispute_id, $evidence, $submit, $metadata );
	}
}
