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
 * fresh submission. Annotated `destructive=true`: a policy layer must reason
 * about the most-dangerous mode, and the `submit=true` path is irreversible
 * (matching accept-dispute).
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WCPay\Internal\Service\DisputeService::submit_evidence()
 */
class SubmitDisputeEvidence extends AbstractWCPayAbility implements AbilityDefinition {

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
		// Free-text evidence fields: an agent can fill these directly.
		$evidence_text_fields = [
			'product_description'      => __( 'Description of the product or service and how it was presented to the customer.', 'woocommerce-payments' ),
			'customer_purchase_ip'     => __( 'IP address the customer used for the purchase.', 'woocommerce-payments' ),
			'cancellation_rebuttal'    => __( 'Explanation of why the customer was not entitled to cancel.', 'woocommerce-payments' ),
			'access_activity_log'      => __( 'Activity log showing the customer accessed or downloaded a digital product.', 'woocommerce-payments' ),
			'uncategorized_text'       => __( 'Any additional explanation that does not fit another field.', 'woocommerce-payments' ),
			'shipping_carrier'         => __( 'Delivery service that shipped the product (e.g. USPS, FedEx, UPS, DHL).', 'woocommerce-payments' ),
			'shipping_date'            => __( 'Date the product was shipped.', 'woocommerce-payments' ),
			'shipping_tracking_number' => __( 'Tracking number for a shipped physical product.', 'woocommerce-payments' ),
			'shipping_address'         => __( 'Address the product was shipped to.', 'woocommerce-payments' ),
		];

		// Document evidence fields: each takes the ID of a file already uploaded
		// (e.g. via woocommerce-payments/upload-dispute-evidence-file), not raw bytes.
		$evidence_document_fields = [
			'customer_communication'         => __( 'Correspondence with the customer relevant to the dispute (e.g. emails).', 'woocommerce-payments' ),
			'customer_signature'             => __( 'Proof the customer signed for the product or agreed to terms.', 'woocommerce-payments' ),
			'receipt'                        => __( 'Receipt or message sent to the customer for the charge.', 'woocommerce-payments' ),
			'refund_policy'                  => __( 'Your refund policy as shown to the customer.', 'woocommerce-payments' ),
			'duplicate_charge_documentation' => __( 'Documentation for the prior charge a duplicate dispute refers to.', 'woocommerce-payments' ),
			'shipping_documentation'         => __( 'Proof the product was delivered (e.g. signed delivery confirmation).', 'woocommerce-payments' ),
			'service_documentation'          => __( 'Proof the service was provided to the customer.', 'woocommerce-payments' ),
			'cancellation_policy'            => __( 'Your cancellation policy as shown to the customer.', 'woocommerce-payments' ),
			'uncategorized_file'             => __( 'Any additional supporting document.', 'woocommerce-payments' ),
		];

		$evidence_properties = [];
		foreach ( $evidence_text_fields as $field => $field_description ) {
			$evidence_properties[ $field ] = [
				'type'        => 'string',
				'description' => $field_description,
			];
		}
		foreach ( $evidence_document_fields as $field => $field_description ) {
			$evidence_properties[ $field ] = [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: description of what the document should show. */
					__( '%s Provide the ID of a file uploaded via woocommerce-payments/upload-dispute-evidence-file, not raw file contents.', 'woocommerce-payments' ),
					$field_description
				),
			];
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
						'description' => __( 'Stripe dispute ID (typically `du_…` or legacy `dp_…`). Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.', 'woocommerce-payments' ),
					],
					'evidence'   => [
						'type'                 => 'object',
						'description'          => __( 'Evidence fields. Text fields take free text; document fields take the ID of a file uploaded via woocommerce-payments/upload-dispute-evidence-file (not raw file contents).', 'woocommerce-payments' ),
						'properties'           => $evidence_properties,
						'additionalProperties' => false,
					],
					'submit'     => [
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to submit to the card network (irreversible). Default false stages a draft.', 'woocommerce-payments' ),
					],
					'metadata'   => [
						'type'                 => 'object',
						'description'          => __( 'Optional metadata to attach to the dispute. Maximum 10 keys; keys up to 40 characters; values up to 500 characters.', 'woocommerce-payments' ),
						'additionalProperties' => [ 'type' => 'string' ],
						'maxProperties'        => 10,
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
		$submit     = true === ( $input['submit'] ?? false );
		$metadata   = ( isset( $input['metadata'] ) && is_array( $input['metadata'] ) ) ? $input['metadata'] : [];

		if ( count( $metadata ) > 10 ) {
			return new \WP_Error( 'wcpay_too_many_metadata_keys', __( 'Metadata may not contain more than 10 keys.', 'woocommerce-payments' ) );
		}

		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) || strlen( $key ) > 40 ) {
				return new \WP_Error( 'wcpay_invalid_metadata_key', __( 'Metadata keys must be strings of 40 characters or fewer.', 'woocommerce-payments' ) );
			}
			if ( ! is_string( $value ) || strlen( $value ) > 500 ) {
				return new \WP_Error( 'wcpay_invalid_metadata_value', __( 'Metadata values must be strings of 500 characters or fewer.', 'woocommerce-payments' ) );
			}
		}

		return self::get_dispute_service()->submit_evidence( $dispute_id, $evidence, $submit, $metadata );
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
