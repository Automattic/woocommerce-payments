/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RecommendedDocument } from './types';
import { DOCUMENT_FIELD_KEYS } from './document-field-keys';

/**
 * Evidence matrix that maps [reason][productType] to recommended document fields.
 *
 * This provides a scalable way to define evidence suggestions for different
 * combinations of dispute reasons and product types.
 *
 * Each entry contains only the fields specific to that combination.
 * Base fields (Customer communication) are automatically merged in by
 * getRecommendedDocumentFields() when retrieving matrix entries.
 */
type EvidenceMatrix = {
	[ reason: string ]: {
		[ productType: string ]: Array< RecommendedDocument >;
	};
};

/**
 * Get evidence matrix entries for duplicate disputes.
 *
 * Duplicate disputes depend on both product type AND duplicate status.
 * Keys are formatted as: `${productType}__${duplicateStatus}`
 */
const getDuplicateMatrix = (): {
	[ key: string ]: Array< RecommendedDocument >;
} => ( {
	// Booking/Reservation - It was a duplicate (Scenario A)
	booking_reservation__is_duplicate: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_RECEIPT_DOCUMENTATION,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A confirmation that the refund was processed.',
				'woocommerce-payments'
			),
			order: 15,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
				'woocommerce-payments'
			),
			order: 20,
		},
	],
	// Booking/Reservation - It was not a duplicate (Scenario B)
	booking_reservation__is_not_duplicate: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
			label: __( 'Any additional receipts', 'woocommerce-payments' ),
			description: __(
				'Receipt(s) for any other order(s) from this customer.',
				'woocommerce-payments'
			),
			order: 12,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
} );

/**
 * Get evidence matrix entries for subscription_canceled disputes.
 *
 * For 'multiple' product type, subscription logs are not included
 * since multiple products may have different subscription states.
 *
 * For 'other' product type, simplified fields are shown per specs.
 */
const getSubscriptionCanceledMatrix = (): {
	[ productType: string ]: Array< RecommendedDocument >;
} => ( {
	// Other product type - simplified fields per specs
	other: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Proof of Purchase', 'woocommerce-payments' ),
			description: __(
				'Invoice and payment confirmation.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Order details', 'woocommerce-payments' ),
			description: __(
				'Description and terms of the product or service.',
				'woocommerce-payments'
			),
			order: 30,
		},
	],
	// Multiple product type - no subscription logs
	multiple: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Store refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
				'woocommerce-payments'
			),
			order: 40,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
				'woocommerce-payments'
			),
			order: 50,
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
} );

/**
 * Get evidence matrix entries for fraudulent disputes.
 */
const getFraudulentMatrix = (): {
	[ productType: string ]: Array< RecommendedDocument >;
} => ( {
	booking_reservation: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __(
				'Prior undisputed transaction history',
				'woocommerce-payments'
			),
			description: __(
				'Proof of past undisputed transactions from the same customer, with matching billing and device details.',
				'woocommerce-payments'
			),
			order: 10,
		},
	],
} );

/**
 * The complete evidence matrix mapping reason codes to product types to fields.
 *
 * Usage:
 *   const fields = evidenceMatrix['fraudulent']?.['booking_reservation'];
 *
 * This matrix is only used when the feature flag is enabled.
 * When no matrix entry exists, the function falls back to the existing logic.
 */
export const evidenceMatrix: EvidenceMatrix = {
	fraudulent: getFraudulentMatrix(),
	subscription_canceled: getSubscriptionCanceledMatrix(),
	duplicate: getDuplicateMatrix(),
};

/**
 * Get recommended document fields from the evidence matrix.
 *
 * For most reasons, lookup is by [reason][productType].
 * For 'duplicate' reason, lookup uses composite key: [reason][productType__status]
 *
 * @param reason - The dispute reason code
 * @param productType - The product type
 * @param status - Optional status for status-dependent reasons (e.g., duplicateStatus)
 * @return Array of recommended document fields, or undefined if no matrix entry exists
 */
export const getMatrixFields = (
	reason: string,
	productType: string,
	status?: string
): Array< RecommendedDocument > | undefined => {
	// For duplicate disputes, use composite key with status
	if ( reason === 'duplicate' && status ) {
		const compositeKey = `${ productType }__${ status }`;
		return evidenceMatrix[ reason ]?.[ compositeKey ];
	}

	// Return the matrix entry for the specific productType, or undefined if not found
	return evidenceMatrix[ reason ]?.[ productType ];
};
