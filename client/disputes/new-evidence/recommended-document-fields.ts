/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RecommendedDocument } from './types';
import { getMatrixFields } from './evidence-matrix';
import { DOCUMENT_FIELD_KEYS } from './document-field-keys';

// Re-export for backward compatibility
export { DOCUMENT_FIELD_KEYS };

/**
 * Get recommended document fields based on dispute reason
 *
 * @param {string} reason - The dispute reason
 * @param {string} refundStatus - The refund status (for credit_not_processed disputes)
 * @param {string} duplicateStatus - The duplicate status (for duplicate disputes)
 * @param {string} productType - The product type (for subscription_canceled disputes)
 * @return {Array<{key: string, label: string}>} Array of recommended document fields
 */
const getRecommendedDocumentFields = (
	reason: string,
	refundStatus?: string,
	duplicateStatus?: string,
	productType?: string
): Array< RecommendedDocument > => {
	// Feature flag gated: Check evidence matrix for reason + product type combinations
	const isFeatureFlagEnabled =
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ||
		false;

	if ( isFeatureFlagEnabled ) {
		// For duplicate disputes, use duplicateStatus for composite key lookup.
		// Use 'default' as placeholder to attempt matrix lookup (will fall back if no entry exists).
		const status = reason === 'duplicate' ? duplicateStatus : undefined;
		const effectiveProductType =
			productType || ( reason === 'duplicate' ? 'default' : undefined );

		if ( effectiveProductType ) {
			const matrixFields = getMatrixFields(
				reason,
				effectiveProductType,
				status
			);
			if ( matrixFields ) {
				// Base field that applies to all matrix entries
				const baseField: RecommendedDocument = {
					key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
					label: __(
						'Customer communication',
						'woocommerce-payments'
					),
					description: __(
						'Any correspondence with the customer regarding this purchase.',
						'woocommerce-payments'
					),
					order: 20,
				};

				// Merge base field with matrix fields, avoiding duplicates
				const hasCustomerCommunication = matrixFields.some(
					( field ) =>
						field.key === DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION
				);

				const allFields = hasCustomerCommunication
					? matrixFields
					: [ ...matrixFields, baseField ];

				// Sort by order and return
				return allFields
					.sort( ( a, b ) => a.order - b.order )
					.map( ( { key, label, description } ) => ( {
						key,
						label,
						description,
						order: 0,
					} ) );
			}
		}
	}

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
		credit_not_processed:
			refundStatus === 'refund_was_not_owed'
				? [
						// For refund_was_not_owed: Order receipt, Customer communication, Store refund policy, Other documents
						{
							key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
							label: __(
								'Store refund policy',
								'woocommerce-payments'
							),
							description: __(
								"A screenshot of your store's refund policy.",
								'woocommerce-payments'
							),
							order: 40,
						},
				  ]
				: [
						// For refund_has_been_issued: Current selection (Order receipt, Customer communication, Customer signature, Store refund policy, Item condition, Other documents)
						{
							key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
							label: __(
								"Customer's signature",
								'woocommerce-payments'
							),
							description: __(
								"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
								'woocommerce-payments'
							),
							order: 30,
						},
						{
							key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
							label: __(
								'Store refund policy',
								'woocommerce-payments'
							),
							description: __(
								"A screenshot of your store's refund policy.",
								'woocommerce-payments'
							),
							order: 40,
						},
						{
							key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
							label: __(
								'Item condition',
								'woocommerce-payments'
							),
							description: __(
								'A screenshot of the item condition.',
								'woocommerce-payments'
							),
							order: 50,
						},
				  ],
		// Fallback for duplicate disputes when feature flag is OFF
		duplicate:
			duplicateStatus === 'is_duplicate'
				? [
						{
							key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
							label: __(
								'Proof of active subscription',
								'woocommerce-payments'
							),
							description: __(
								'Any documents showing the billing history, subscription status, or cancellation logs, for example.',
								'woocommerce-payments'
							),
							order: 30,
						},
						{
							key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
							label: __(
								'Store refund policy',
								'woocommerce-payments'
							),
							description: __(
								"A screenshot of your store's refund policy.",
								'woocommerce-payments'
							),
							order: 40,
						},
						{
							key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
							label: __(
								'Terms of service',
								'woocommerce-payments'
							),
							description: __(
								"A screenshot of your store's terms of service.",
								'woocommerce-payments'
							),
							order: 50,
						},
				  ]
				: [
						{
							key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
							label: __(
								'Store refund policy',
								'woocommerce-payments'
							),
							description: __(
								"A screenshot of your store's refund policy.",
								'woocommerce-payments'
							),
							order: 30,
						},
				  ],
		// Fallback for subscription_canceled when feature flag is OFF
		subscription_canceled: [
			{
				key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				label: __(
					'Proof of active subscription',
					'woocommerce-payments'
				),
				description: __(
					'Any documents showing the billing history, subscription status, or cancellation logs, for example.',
					'woocommerce-payments'
				),
				order: 30,
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
		],
		fraudulent: [
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
				key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				label: __( "Customer's signature", 'woocommerce-payments' ),
				description: __(
					"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
					'woocommerce-payments'
				),
				order: 30,
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
		],
		product_unacceptable: [
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
				key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				label: __( "Customer's signature", 'woocommerce-payments' ),
				description: __(
					"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
					'woocommerce-payments'
				),
				order: 30,
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
				order: 40,
			},
		],
		general: [
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
			{
				key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's refund policy.",
					'woocommerce-payments'
				),
				order: 50,
			},
			{
				key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				label: __( 'Terms of service', 'woocommerce-payments' ),
				description: __(
					"A screenshot of your store's terms of service.",
					'woocommerce-payments'
				),
				order: 60,
			},
		],
	};

	// Combine default fields with reason-specific fields
	const allFields = [
		...orderedFields,
		...( reasonSpecificFields[ reason ] || reasonSpecificFields.general ),
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
				'A receipt from the shipping carrier or a tracking number, for example.',
				'woocommerce-payments'
			),
			order: 0,
		},
	];
};

export { getRecommendedDocumentFields, getRecommendedShippingDocumentFields };
