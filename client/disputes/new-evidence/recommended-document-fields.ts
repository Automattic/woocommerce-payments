/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RecommendedDocument } from './types';

/**
 * Document field keys used across different dispute types.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const DOCUMENT_FIELD_KEYS = {
	RECEIPT: 'receipt',
	CUSTOMER_COMMUNICATION: 'customer_communication',
	CUSTOMER_SIGNATURE: 'customer_signature',
	UNCATEGORIZED_FILE: 'uncategorized_file',
	REFUND_POLICY: 'refund_policy',
	DUPLICATE_CHARGE_DOCUMENTATION: 'duplicate_charge_documentation',
	CANCELLATION_POLICY: 'cancellation_policy',
	ACCESS_ACTIVITY_LOG: 'access_activity_log',
	SERVICE_DOCUMENTATION: 'service_documentation',
	SHIPPING_DOCUMENTATION: 'shipping_documentation',
} as const;

/**
 * Get recommended document fields based on dispute reason
 *
 * @param {string} reason - The dispute reason
 * @return {Array<{key: string, label: string}>} Array of recommended document fields
 */
const getRecommendedDocumentFields = (
	reason: string
): Array< RecommendedDocument > => {
	// Define fields with their order
	const orderedFields = [
		// Default fields that apply to all dispute types
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Customer communication', 'woocommerce-payments' ),
			description: __(
				'Any correspondence with the customer regarding this purchase.',
				'woocommerce-payments'
			),
			order: 20,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			label: __( "Customer's signature", 'woocommerce-payments' ),
			description: __(
				"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
				'woocommerce-payments'
			),
			order: 30,
		},
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100, // Always last
		},
	];

	// Reason-specific fields with their order
	const reasonSpecificFields: Record<
		string,
		Array< RecommendedDocument >
	> = {
		credit_not_processed: [
			{
				key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's refund policy.",
					'woocommerce-payments'
				),
				order: 40,
			},
		],
		duplicate: [
			{
				key: DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				label: __(
					'Documentation for the duplicate charge',
					'woocommerce-payments'
				),
				description: __(
					'A screenshot of the duplicate charge.',
					'woocommerce-payments'
				),
				order: 40,
			},
		],
		subscription_canceled: [
			{
				key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				label: __( 'Cancellation policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's cancellation policy.",
					'woocommerce-payments'
				),
				order: 40,
			},
			{
				key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				label: __(
					'Proof of active subscription',
					'woocommerce-payments'
				),
				description: __(
					'Such as billing history, subscription status, or cancellation logs.',
					'woocommerce-payments'
				),
				order: 50,
			},
		],
		fraudulent: [
			{
				key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's refund policy.",
					'woocommerce-payments'
				),
				order: 40,
			},
		],
		product_not_received: [
			{
				key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's refund policy.",
					'woocommerce-payments'
				),
				order: 40,
			},
		],
		product_unacceptable: [
			{
				key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				label: __( 'Item condition', 'woocommerce-payments' ),
				description: __(
					'A screenshot of the item condition.',
					'woocommerce-payments'
				),
				order: 40,
			},
			{
				key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's refund policy.",
					'woocommerce-payments'
				),
				order: 50,
			},
		],
		unrecognized: [
			{
				key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				label: __(
					'Proof of active subscription',
					'woocommerce-payments'
				),
				description: __(
					'Such as billing history, subscription status, or cancellation logs.',
					'woocommerce-payments'
				),
				order: 40,
			},
		],
	};

	// Combine default fields with reason-specific fields
	const allFields = [
		...orderedFields,
		...( reasonSpecificFields[ reason ] || [] ),
	];

	// Sort fields by order and remove the order property
	return allFields
		.sort( ( a, b ) => a.order - b.order )
		.map( ( { key, label, description } ) => ( {
			key,
			label,
			description,
			order: 0,
		} ) );
};

const getRecommendedShippingDocumentFields = (): Array<
	RecommendedDocument
> => {
	return [
		{
			key: DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
			label: __( 'Proof of shipping', 'woocommerce-payments' ),
			description: __(
				'A copy of the shipment receipt or label.',
				'woocommerce-payments'
			),
			order: 0,
		},
	];
};

export { getRecommendedDocumentFields, getRecommendedShippingDocumentFields };
