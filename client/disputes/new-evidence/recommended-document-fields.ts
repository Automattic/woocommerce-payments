/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

interface RecommendedDocument {
	key: string;
	label: string;
	order: number;
}

/**
 * Get recommended document fields based on dispute reason
 *
 * @param {string} reason - The dispute reason
 * @return {Array<{key: string, label: string}>} Array of recommended document fields
 */
const getRecommendedDocumentFields = (
	reason: string
): Array< { key: string; label: string } > => {
	// Define fields with their order
	const orderedFields = [
		// Default fields that apply to all dispute types
		{
			key: 'receipt',
			label: __( 'Order receipt', 'woocommerce-payments' ),
			order: 10,
		},
		{
			key: 'customer_communication',
			label: __( 'Customer communication', 'woocommerce-payments' ),
			order: 20,
		},
		{
			key: 'customer_signature',
			label: __( "Customer's signature", 'woocommerce-payments' ),
			order: 30,
		},
		{
			key: 'uncategorized_file',
			label: __( 'Other documents', 'woocommerce-payments' ),
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
				key: 'refund_policy',
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				order: 40,
			},
		],
		duplicate: [
			{
				key: 'duplicate_charge_documentation',
				label: __(
					'Documentation for the duplicate charge',
					'woocommerce-payments'
				),
				order: 40,
			},
		],
		subscription_canceled: [
			{
				key: 'cancellation_policy',
				label: __(
					'Copy of the cancellation policy',
					'woocommerce-payments'
				),
				order: 40,
			},
			{
				key: 'access_activity_log',
				label: __( 'Access and activity logs', 'woocommerce-payments' ),
				order: 50,
			},
		],
		fraudulent: [
			{
				key: 'refund_policy',
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				order: 40,
			},
		],
		product_not_received: [
			{
				key: 'refund_policy',
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				order: 40,
			},
		],
		product_unacceptable: [
			{
				key: 'service_documentation',
				label: __( 'Item condition', 'woocommerce-payments' ),
				order: 40,
			},
			{
				key: 'refund_policy',
				label: __( 'Store refund policy', 'woocommerce-payments' ),
				order: 50,
			},
		],
		unrecognized: [
			{
				key: 'access_activity_log',
				label: __( 'Access and activity logs', 'woocommerce-payments' ),
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
		.map( ( { key, label } ) => ( { key, label } ) );
};

export default getRecommendedDocumentFields;
