/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { RecommendedDocument } from './types';
import type { EvidenceFieldStatus } from './types';
import { DOCUMENT_FIELD_KEYS } from './document-field-keys';
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';

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
	// Physical Product - It was a duplicate (Scenario A)
	physical_product__is_duplicate: [
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
				'A confirmation that a refund was issued.',
				'woocommerce-payments'
			),
			order: 15,
		},
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Proof of active subscription', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the billing history, subscription status, or cancellation logs, for example.',
				'woocommerce-payments'
			),
			order: 22,
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			order: 100,
		},
	],
	// Physical Product - It was not a duplicate (Scenario B)
	physical_product__is_not_duplicate: [
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
				'A confirmation that a refund was issued.',
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
	// Digital Product/Service - It was a duplicate (Scenario A)
	digital_product_or_service__is_duplicate: [
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
				'A confirmation that a refund was issued.',
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
	// Digital Product/Service - It was not a duplicate (Scenario B)
	digital_product_or_service__is_not_duplicate: [
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
	// Offline Service - It was a duplicate (Scenario A)
	offline_service__is_duplicate: [
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
				'A confirmation that a refund was issued.',
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
	// Offline Service - It was not a duplicate (Scenario B)
	offline_service__is_not_duplicate: [
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
	// Event - It was a duplicate (Scenario A)
	event__is_duplicate: [
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
				'A confirmation that a refund was issued.',
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
	// Event - It was not a duplicate (Scenario B)
	event__is_not_duplicate: [
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
	// Other - It was a duplicate (Scenario A)
	other__is_duplicate: [
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
				'A confirmation that a refund was issued.',
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
	// Other - It was not a duplicate (Scenario B)
	other__is_not_duplicate: [
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
	// Physical Product product type
	physical_product: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
				'woocommerce-payments'
			),
			order: 30,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
				'woocommerce-payments'
			),
			order: 35,
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
	// Booking/Reservation product type
	booking_reservation: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			order: 100,
		},
	],
	// Offline Service product type
	offline_service: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			order: 100,
		},
	],
	// Event product type
	event: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			order: 100,
		},
	],
	// Digital Product/Service product type
	digital_product_or_service: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 15,
		},
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Login or usage records', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the login history, usage activity, or access logs for the digital product or service.',
				'woocommerce-payments'
			),
			order: 22,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			order: 100,
		},
	],
	// Other product type - per specs
	other: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
			label: __( 'Refund policy', 'woocommerce-payments' ),
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
 * Get evidence matrix entries for product_not_received disputes.
 */
const getProductNotReceivedMatrix = (): {
	[ productType: string ]: Array< RecommendedDocument >;
} => ( {
	// Physical Product product type
	physical_product: [
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			label: __( "Customer's signature", 'woocommerce-payments' ),
			description: __(
				"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
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
			order: 100,
		},
	],
	// Digital Product/Service product type
	digital_product_or_service: [
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
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Login or usage records', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the login history, usage activity, or access logs for the digital product or service.',
				'woocommerce-payments'
			),
			order: 15,
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
	// Booking/Reservation product type
	booking_reservation: [
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
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __(
				'Reservation or booking confirmation',
				'woocommerce-payments'
			),
			description: __(
				'Any documents showing the service completion, attendance or reservation confirmation.',
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation confirmation', 'woocommerce-payments' ),
			description: __(
				'Documents showing the product or service was canceled, such as cancellation logs, confirmation emails, or account records.',
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
			order: 100,
		},
	],
	// Offline Service product type
	offline_service: [
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
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Proof of service completion', 'woocommerce-payments' ),
			description: __(
				'Screenshots or documents showing the service was completed and delivered to the customer.',
				'woocommerce-payments'
			),
			order: 15,
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
	// Event product type
	event: [
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
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Attendance confirmation', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the service completion, attendance or reservation confirmation.',
				'woocommerce-payments'
			),
			order: 15,
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
	// Other product type
	other: [
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
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Service completion records', 'woocommerce-payments' ),
			description: __(
				'Screenshots or documents showing the service was completed and delivered to the customer.',
				'woocommerce-payments'
			),
			order: 15,
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
 * Get evidence matrix entries for product_unacceptable disputes.
 */
const getProductUnacceptableMatrix = (): {
	[ productType: string ]: Array< RecommendedDocument >;
} => ( {
	// Physical Product product type
	physical_product: [
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			label: __( "Customer's signature", 'woocommerce-payments' ),
			description: __(
				"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
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
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( "Item's condition", 'woocommerce-payments' ),
			description: __(
				"Photos showing the item's condition prior to shipping.",
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
			order: 100,
		},
	],
	// Digital Product/Service product type
	digital_product_or_service: [
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Proof of delivered service', 'woocommerce-payments' ),
			description: __(
				'Screenshots or documents showing the digital product or service was delivered and accessible to the customer.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
				'woocommerce-payments'
			),
			order: 12,
		},
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Login or usage records', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the login history, usage activity, or access logs for the digital product or service.',
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
	// Booking/Reservation product type
	booking_reservation: [
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __(
				'Event or booking documentation',
				'woocommerce-payments'
			),
			description: __(
				'Screenshots or documents showing the event or reservation details (date, location, description, and terms) and confirmation it occurred or remained valid as described.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
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
	// Offline Service product type
	offline_service: [
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Proof of delivered service', 'woocommerce-payments' ),
			description: __(
				'Screenshots or documents showing the service was completed and delivered to the customer.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
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
	// Event product type
	event: [
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __(
				'Event or booking documentation',
				'woocommerce-payments'
			),
			description: __(
				'Screenshots or documents showing the event or reservation details (date, location, description, and terms) and confirmation it occurred or remained valid as described.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Order receipt', 'woocommerce-payments' ),
			description: __(
				"A copy of the customer's receipt, which can be found in the receipt history for this transaction.",
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
	// Other product type
	other: [
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
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
			label: __( 'Terms of service', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's terms of service.",
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
 * Get evidence matrix entries for credit_not_processed disputes.
 *
 * Credit not processed disputes depend on both product type AND refund status.
 * Keys are formatted as: `${productType}__${refundStatus}`
 */
const getCreditNotProcessedMatrix = (): {
	[ key: string ]: Array< RecommendedDocument >;
} => ( {
	// Physical Product - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is included explicitly with its proper label.
	// This prevents the auto-merge from adding a duplicate "Customer communication" field.
	physical_product__refund_has_been_issued: [
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
				'A confirmation that a merchant is waiting for a return prior to refund.',
				'woocommerce-payments'
			),
			order: 12,
		},
		{
			key: DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
			label: __( 'Return tracking', 'woocommerce-payments' ),
			description: __(
				'A confirmation that a merchant is waiting for a return prior to refund (if applicable).',
				'woocommerce-payments'
			),
			order: 15,
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
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
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
			order: 100,
		},
	],
	// Physical Product - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is included with its proper label.
	// SERVICE_DOCUMENTATION is used for "Other documents" since UNCATEGORIZED_FILE
	// is already used for "Proof of acceptance".
	physical_product__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
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
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Digital Product/Service - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is repurposed as "Other documents" to prevent
	// the base "Customer communication" field from being auto-merged.
	digital_product_or_service__refund_has_been_issued: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A copy of the refund receipt, which can be found in the receipt history for this transaction.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 20,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Digital Product/Service - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is used here as "Other documents" because
	// UNCATEGORIZED_FILE is already used for "Proof of acceptance".
	// Including CUSTOMER_COMMUNICATION in the matrix also prevents the base
	// "Customer communication" field from being auto-merged.
	digital_product_or_service__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
				'woocommerce-payments'
			),
			order: 10,
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Booking/Reservation - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is repurposed as "Other documents" to prevent
	// the base "Customer communication" field from being auto-merged.
	booking_reservation__refund_has_been_issued: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A copy of the refund receipt, which can be found in the receipt history for this transaction.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 20,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Booking/Reservation - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is used here as "Other documents" because
	// UNCATEGORIZED_FILE is already used for "Proof of acceptance".
	// Including CUSTOMER_COMMUNICATION in the matrix also prevents the base
	// "Customer communication" field from being auto-merged.
	booking_reservation__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
				'woocommerce-payments'
			),
			order: 10,
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Offline Service - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is repurposed as "Other documents" to prevent
	// the base "Customer communication" field from being auto-merged.
	offline_service__refund_has_been_issued: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A copy of the refund receipt, which can be found in the receipt history for this transaction.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 20,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Offline Service - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is used here as "Other documents" because
	// UNCATEGORIZED_FILE is already used for "Proof of acceptance".
	// Including CUSTOMER_COMMUNICATION in the matrix also prevents the base
	// "Customer communication" field from being auto-merged.
	offline_service__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
				'woocommerce-payments'
			),
			order: 10,
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Event - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is repurposed as "Other documents" to prevent
	// the base "Customer communication" field from being auto-merged.
	event__refund_has_been_issued: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A copy of the refund receipt, which can be found in the receipt history for this transaction.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
			label: __( 'Cancellation logs', 'woocommerce-payments' ),
			description: __(
				'Records showing no cancellation attempt or request was made before the charge, such as account activity, subscription status, or communication history.',
				'woocommerce-payments'
			),
			order: 20,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Event - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is used here as "Other documents" because
	// UNCATEGORIZED_FILE is already used for "Proof of acceptance".
	// Including CUSTOMER_COMMUNICATION in the matrix also prevents the base
	// "Customer communication" field from being auto-merged.
	event__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
				'woocommerce-payments'
			),
			order: 10,
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Other - Refund has been issued (Scenario A)
	// Note: CUSTOMER_COMMUNICATION is repurposed as "Other documents" to prevent
	// the base "Customer communication" field from being auto-merged.
	// Uses SHIPPING_DOCUMENTATION for "Return tracking" per spec.
	other__refund_has_been_issued: [
		{
			key: DOCUMENT_FIELD_KEYS.RECEIPT,
			label: __( 'Refund receipt', 'woocommerce-payments' ),
			description: __(
				'A copy of the refund receipt, which can be found in the receipt history for this transaction.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
			label: __( 'Return tracking', 'woocommerce-payments' ),
			description: __(
				'A confirmation that a merchant is waiting for a return prior to refund (if applicable).',
				'woocommerce-payments'
			),
			order: 15,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			label: __( 'Other documents', 'woocommerce-payments' ),
			description: __(
				'Any other relevant documents that will support your case.',
				'woocommerce-payments'
			),
			order: 100,
		},
	],
	// Other - Refund was not owed (Scenario B)
	// Note: CUSTOMER_COMMUNICATION is used here as "Other documents" because
	// UNCATEGORIZED_FILE is already used for "Proof of acceptance".
	// Including CUSTOMER_COMMUNICATION in the matrix also prevents the base
	// "Customer communication" field from being auto-merged.
	other__refund_was_not_owed: [
		{
			key: DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			label: __( 'Proof of acceptance', 'woocommerce-payments' ),
			description: __(
				'Screenshot or document showing where the customer agreed to or acknowledged the refund policy during checkout or on the receipt.',
				'woocommerce-payments'
			),
			order: 10,
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
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
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
	// Physical Product product type
	physical_product: [
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
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __(
				'Prior undisputed transaction history',
				'woocommerce-payments'
			),
			description: __(
				'Proof of past undisputed transactions from the same customer, with matching billing and device details.',
				'woocommerce-payments'
			),
			order: 15,
		},
		{
			key: DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			label: __( "Customer's signature", 'woocommerce-payments' ),
			description: __(
				"Any relevant documents showing the customer's signature, such as signed proof of delivery.",
				'woocommerce-payments'
			),
			order: 25,
		},
		{
			key: DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			label: __( 'Refund policy', 'woocommerce-payments' ),
			description: __(
				"A screenshot of your store's refund policy.",
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
			order: 100,
		},
	],
	// Digital Product/Service product type
	// Note: SERVICE_DOCUMENTATION is repurposed as "Prior undisputed transaction history"
	// because ACCESS_ACTIVITY_LOG is already used for "Login or usage records".
	digital_product_or_service: [
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
			label: __( 'Login or usage records', 'woocommerce-payments' ),
			description: __(
				'Any documents showing the login history, usage activity, or access logs for the digital product or service.',
				'woocommerce-payments'
			),
			order: 10,
		},
		{
			key: DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			label: __(
				'Prior undisputed transaction history',
				'woocommerce-payments'
			),
			description: __(
				'Proof of past undisputed transactions from the same customer, with matching billing and device details.',
				'woocommerce-payments'
			),
			order: 15,
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
	// Booking/Reservation product type
	booking_reservation: [
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
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
	// Offline Service product type
	offline_service: [
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
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
	// Event product type
	event: [
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
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
	// Other product type
	other: [
		{
			key: DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
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
	product_not_received: getProductNotReceivedMatrix(),
	subscription_canceled: getSubscriptionCanceledMatrix(),
	product_unacceptable: getProductUnacceptableMatrix(),
	duplicate: getDuplicateMatrix(),
	credit_not_processed: getCreditNotProcessedMatrix(),
};

/**
 * Get recommended document fields from the evidence matrix.
 *
 * For most reasons, lookup is by [reason][productType].
 * For 'duplicate' and 'credit_not_processed' reasons, lookup uses composite key: [reason][productType__status]
 *
 * @param reason      - The dispute reason code
 * @param productType - The product type
 * @param status      - Optional status for status-dependent reasons (e.g., duplicateStatus or refundStatus)
 * @return Array of recommended document fields, or undefined if no matrix entry exists
 */
export const getMatrixFields = (
	reason: string,
	productType: string,
	status?: string
): Array< RecommendedDocument > | undefined => {
	// For duplicate and credit_not_processed disputes, use composite key with status
	if (
		( reason === 'duplicate' || reason === 'credit_not_processed' ) &&
		status
	) {
		const compositeKey = `${ productType }__${ status }`;
		return evidenceMatrix[ reason ]?.[ compositeKey ];
	}

	// Return the matrix entry for the specific productType, or undefined if not found
	return evidenceMatrix[ reason ]?.[ productType ];
};

const emptyByProductType = (): Record< ProductType, string[] > => ( {
	physical_product: [],
	digital_product_or_service: [],
	offline_service: [],
	event: [],
	booking_reservation: [],
	multiple: [],
	other: [],
} );

/**
 * Fields whose presence on a dispute correlates with a higher win rate,
 * per (reason, product type). Consumed by the Dispute Outcome View to flag
 * missing high-impact evidence.
 *
 * Keys are raw Stripe `dispute.evidence` field names (text and document
 * fields alike). Cells with an empty array have no data-backed signal
 * and produce no `expected_missing` markers in the tri-state renderer.
 *
 * Auto-populated and unreliable fields are intentionally excluded:
 *   - `customer_purchase_ip` (Stripe + WooPayments auto-fill on every save)
 *   - `customer_name`, `customer_email_address`, `billing_address` (Stripe auto-fill)
 *   - `product_description` (hybrid auto+merchant; placeholder string by
 *     default — Q6 lift signal is denominator artifact)
 *   - `uncategorized_file`, `uncategorized_text` (catch-alls; not actionable
 *     guidance on their own)
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const DISPUTE_HIGH_IMPACT_FIELDS: Record<
	DisputeReason,
	Record< ProductType, string[] >
> = {
	credit_not_processed: {
		// `customer_signature` (signed delivery proof) is scoped to
		// physical_product because these disputes commonly take the shape
		// "I returned the product and never got my refund": proving
		// delivery corroborates the merchant's defence. The field is
		// intentionally absent from non-physical cells (no shipping
		// proof to attach).
		physical_product: [
			'customer_signature',
			'customer_communication',
			'receipt',
		],
		digital_product_or_service: [
			'customer_communication',
			'receipt',
			'refund_refusal_explanation',
		],
		offline_service: [
			'customer_communication',
			'receipt',
			'refund_refusal_explanation',
		],
		event: [
			'customer_communication',
			'receipt',
			'refund_refusal_explanation',
		],
		booking_reservation: [
			'customer_communication',
			'receipt',
			'refund_refusal_explanation',
		],
		// `multiple` mirrors `physical_product` as a defensible default
		// for multi-product orders that may include a physical item.
		multiple: [ 'customer_signature', 'customer_communication', 'receipt' ],
		other: [
			'customer_communication',
			'receipt',
			'refund_refusal_explanation',
		],
	},
	duplicate: {
		physical_product: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'shipping_documentation',
			'receipt',
		],
		digital_product_or_service: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'receipt',
		],
		offline_service: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'receipt',
		],
		event: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'receipt',
		],
		booking_reservation: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'receipt',
		],
		multiple: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'shipping_documentation',
			'receipt',
		],
		other: [
			'duplicate_charge_explanation',
			'duplicate_charge_documentation',
			'receipt',
		],
	},
	fraudulent: {
		// Same lift-based picks across all product types for fraudulent.
		physical_product: [ 'service_date', 'customer_communication' ],
		digital_product_or_service: [
			'service_date',
			'customer_communication',
		],
		offline_service: [ 'service_date', 'customer_communication' ],
		event: [ 'service_date', 'customer_communication' ],
		booking_reservation: [ 'service_date', 'customer_communication' ],
		multiple: [ 'service_date', 'customer_communication' ],
		other: [ 'service_date', 'customer_communication' ],
	},
	general: {
		physical_product: [ 'receipt', 'customer_communication' ],
		digital_product_or_service: [ 'receipt', 'customer_communication' ],
		offline_service: [ 'receipt', 'customer_communication' ],
		event: [ 'receipt', 'customer_communication' ],
		booking_reservation: [ 'receipt', 'customer_communication' ],
		multiple: [ 'receipt', 'customer_communication' ],
		other: [ 'receipt', 'customer_communication' ],
	},
	product_not_received: {
		physical_product: [
			'shipping_address',
			'shipping_tracking_number',
			'shipping_documentation',
			'shipping_carrier',
			'shipping_date',
		],
		digital_product_or_service: [
			'receipt',
			'customer_communication',
			'access_activity_log',
		],
		offline_service: [ 'receipt', 'customer_communication' ],
		event: [ 'receipt', 'customer_communication' ],
		booking_reservation: [ 'receipt', 'customer_communication' ],
		multiple: [
			'shipping_address',
			'shipping_tracking_number',
			'shipping_documentation',
			'shipping_carrier',
			'shipping_date',
		],
		other: [ 'receipt', 'customer_communication' ],
	},
	product_unacceptable: {
		physical_product: [
			'customer_communication',
			'refund_refusal_explanation',
			'shipping_documentation',
		],
		digital_product_or_service: [
			'access_activity_log',
			'customer_communication',
			'refund_refusal_explanation',
		],
		offline_service: [
			'customer_communication',
			'refund_refusal_explanation',
		],
		// Recommendations for these cells are topical-only (refund_policy,
		// event/booking documentation). They surface as `optional_missing`
		// via the existing matrix; no `expected_missing` markers without
		// data-backed lift.
		event: [],
		booking_reservation: [],
		multiple: [
			'customer_communication',
			'refund_refusal_explanation',
			'shipping_documentation',
		],
		other: [],
	},
	subscription_canceled: {
		physical_product: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		digital_product_or_service: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		offline_service: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		event: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		booking_reservation: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		multiple: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
		other: [
			'cancellation_policy_disclosure',
			'cancellation_policy',
			'cancellation_rebuttal',
		],
	},
	// No data-backed signal yet: tri-state renders no `expected_missing`
	// rows for any product type under these reasons.
	bank_cannot_process: emptyByProductType(),
	check_returned: emptyByProductType(),
	customer_initiated: emptyByProductType(),
	debit_not_authorized: emptyByProductType(),
	incorrect_account_details: emptyByProductType(),
	insufficient_funds: emptyByProductType(),
	noncompliant: emptyByProductType(),
	unrecognized: emptyByProductType(),
};

/**
 * Fields that are topically relevant to a dispute reason × product type
 * but lack a defensible win-rate lift signal — and that the wizard's
 * `evidenceMatrix` deliberately omits for that cell. Surfaced by the
 * Dispute Outcome View tri-state renderer as `optional_missing` (muted),
 * never as `expected_missing` (red).
 *
 * This map exists because the wizard matrix and the Outcome View serve
 * different surfaces (pre-response challenge UI vs. post-resolution
 * coaching). Topical recommendations from the post-resolution surface
 * should not bleed into the pre-response wizard, so they live here
 * instead of in `evidenceMatrix`.
 *
 * When a Q-refresh promotes a topical field to a defensible lift signal
 * (≥ +3pp), move the entry from `DISPUTE_TOPICAL_FIELDS` to
 * `DISPUTE_HIGH_IMPACT_FIELDS`. Same shape; pure data move.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const DISPUTE_TOPICAL_FIELDS: Record<
	DisputeReason,
	Record< ProductType, string[] >
> = {
	subscription_canceled: {
		// physical_product, multiple: refund_policy is already in the
		// wizard matrix for these cells; no need to duplicate.
		physical_product: [],
		digital_product_or_service: [ 'refund_policy' ],
		offline_service: [ 'refund_policy' ],
		event: [ 'refund_policy' ],
		booking_reservation: [ 'refund_policy' ],
		multiple: [],
		other: [ 'refund_policy' ],
	},
	product_unacceptable: {
		// physical, digital, offline, event, booking: refund_policy is
		// already in the wizard matrix for these cells.
		physical_product: [],
		digital_product_or_service: [],
		offline_service: [],
		event: [],
		booking_reservation: [],
		multiple: [],
		other: [ 'refund_policy' ],
	},
	// All other reasons: topicals (where Catherine recommends them) are
	// already covered by the wizard matrix for the relevant product types,
	// so no entries are needed here.
	bank_cannot_process: emptyByProductType(),
	check_returned: emptyByProductType(),
	credit_not_processed: emptyByProductType(),
	customer_initiated: emptyByProductType(),
	debit_not_authorized: emptyByProductType(),
	duplicate: emptyByProductType(),
	fraudulent: emptyByProductType(),
	general: emptyByProductType(),
	incorrect_account_details: emptyByProductType(),
	insufficient_funds: emptyByProductType(),
	noncompliant: emptyByProductType(),
	product_not_received: emptyByProductType(),
	unrecognized: emptyByProductType(),
};

/**
 * Human-readable labels for raw Stripe `dispute.evidence` keys that are not
 * present in the evidence matrix (which is scoped to document uploads).
 *
 * Used by `getExpectedFieldStatus` when a high-impact field has no
 * corresponding matrix entry from which to borrow a label.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
const FALLBACK_EVIDENCE_FIELD_LABELS: Record< string, string > = {
	billing_address: __( 'Billing address', 'woocommerce-payments' ),
	cancellation_policy_disclosure: __(
		'Cancellation policy disclosure',
		'woocommerce-payments'
	),
	cancellation_rebuttal: __( 'Cancellation logs', 'woocommerce-payments' ),
	// Base fields are auto-merged into wizard cells at runtime by
	// `getRecommendedDocumentFields`, not stored in `evidenceMatrix`. The
	// outcome-view tri-state helper reads `evidenceMatrix` directly, so it
	// would otherwise render the raw key. Match the labels the wizard uses.
	customer_communication: __(
		'Customer communication',
		'woocommerce-payments'
	),
	duplicate_charge_explanation: __(
		'Duplicate charge explanation',
		'woocommerce-payments'
	),
	product_description: __( 'Product description', 'woocommerce-payments' ),
	receipt: __( 'Order receipt', 'woocommerce-payments' ),
	refund_policy: __( 'Refund policy', 'woocommerce-payments' ),
	refund_refusal_explanation: __(
		'Refund refusal explanation',
		'woocommerce-payments'
	),
	service_date: __( 'Service date', 'woocommerce-payments' ),
	shipping_address: __( 'Shipping address', 'woocommerce-payments' ),
	shipping_carrier: __( 'Shipping carrier', 'woocommerce-payments' ),
	shipping_date: __( 'Shipping date', 'woocommerce-payments' ),
	shipping_tracking_number: __(
		'Shipping tracking number',
		'woocommerce-payments'
	),
};

/**
 * Find a label for `key` in the wizard matrix, scoped to the cells that
 * apply to the given product type. Composite-key reasons (`duplicate`,
 * `credit_not_processed`) store cells keyed `${productType}__${status}`;
 * we match any cell whose key equals `productType` or starts with
 * `${productType}__`.
 *
 * If the productType-specific cell does not list the key, the caller
 * (`resolveFieldLabel`) falls through to `FALLBACK_EVIDENCE_FIELD_LABELS`.
 */
const findMatrixLabel = (
	reason: string,
	productType: string,
	key: string
): string | undefined => {
	const productTypeEntries = evidenceMatrix[ reason ];
	if ( ! productTypeEntries ) {
		return undefined;
	}

	const productTypePrefix = `${ productType }__`;
	for ( const [ matrixKey, docs ] of Object.entries( productTypeEntries ) ) {
		if (
			matrixKey === productType ||
			matrixKey.startsWith( productTypePrefix )
		) {
			const match = docs.find( ( doc ) => doc.key === key );
			if ( match ) {
				return match.label;
			}
		}
	}
	return undefined;
};

const resolveFieldLabel = (
	reason: string,
	productType: string,
	key: string
): string =>
	findMatrixLabel( reason, productType, key ) ??
	FALLBACK_EVIDENCE_FIELD_LABELS[ key ] ??
	key;

const hasMeaningfulValue = ( value: unknown ): boolean => {
	if ( value === undefined || value === null ) {
		return false;
	}
	if ( typeof value === 'string' ) {
		return value.trim().length > 0;
	}
	if ( typeof value === 'object' ) {
		return Object.values( value as Record< string, unknown > ).some(
			hasMeaningfulValue
		);
	}
	return Boolean( value );
};

const isFieldProvided = (
	evidence: Record< string, unknown >,
	key: string
): boolean => hasMeaningfulValue( evidence[ key ] );

/**
 * Collect every matrix key that applies to the given product type for the
 * reason. Composite-key reasons (`duplicate`, `credit_not_processed`) store
 * cells keyed `${productType}__${status}`; we union every cell whose key
 * starts with `${productType}__` so the optional-missing pool covers all
 * status branches the resolved dispute might have come from.
 */
const collectMatrixKeys = (
	reason: string,
	productType: string
): Set< string > => {
	const keys = new Set< string >();
	const productTypeEntries = evidenceMatrix[ reason ];
	if ( ! productTypeEntries ) {
		return keys;
	}

	const productTypePrefix = `${ productType }__`;
	for ( const [ matrixKey, docs ] of Object.entries( productTypeEntries ) ) {
		if (
			matrixKey !== productType &&
			! matrixKey.startsWith( productTypePrefix )
		) {
			continue;
		}
		for ( const doc of docs ) {
			keys.add( doc.key );
		}
	}
	return keys;
};

/**
 * Determine the tri-state status of evidence fields for a (reason, product
 * type) pair.
 *
 * The helper returns one entry per key in the union of three sources:
 *   - `DISPUTE_HIGH_IMPACT_FIELDS[reason][productType]` (red ✗ when missing)
 *   - `DISPUTE_TOPICAL_FIELDS[reason][productType]` (muted — when missing)
 *   - Every field in `evidenceMatrix[reason]` whose cell applies to
 *     `productType`, including composite `${productType}__${status}`
 *     cells (muted — when missing)
 *
 * States:
 *   - `provided`:         `evidence[key]` is a non-empty string (after
 *                         trimming) or an object containing at least one
 *                         non-empty leaf value
 *   - `expected_missing`: key is in the high-impact list for this cell and empty
 *   - `optional_missing`: key is topical or matrix-only, and empty
 *
 * Cells with an empty high-impact list produce no `expected_missing` rows.
 * Unrecognised reason or product type strings return an empty array.
 */
export const getExpectedFieldStatus = (
	reason: string,
	productType: string,
	evidence: Record< string, unknown >
): EvidenceFieldStatus[] => {
	// Each cell is curated to be duplicate-free; emitting in order without
	// an extra dedupe pass relies on that. A duplicate in the seed data
	// would surface as a duplicate row, which is preferable to silently
	// masking a data bug.
	const highImpactKeys =
		DISPUTE_HIGH_IMPACT_FIELDS[ reason as DisputeReason ]?.[
			productType as ProductType
		] ?? [];

	const topicalKeys =
		DISPUTE_TOPICAL_FIELDS[ reason as DisputeReason ]?.[
			productType as ProductType
		] ?? [];

	const matrixKeys = collectMatrixKeys( reason, productType );

	const result: EvidenceFieldStatus[] = [];
	const emitted = new Set< string >();

	for ( const key of highImpactKeys ) {
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'expected_missing',
		} );
		emitted.add( key );
	}

	for ( const key of topicalKeys ) {
		if ( emitted.has( key ) ) {
			continue;
		}
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'optional_missing',
		} );
		emitted.add( key );
	}

	for ( const key of matrixKeys ) {
		if ( emitted.has( key ) ) {
			continue;
		}
		result.push( {
			key,
			label: resolveFieldLabel( reason, productType, key ),
			state: isFieldProvided( evidence, key )
				? 'provided'
				: 'optional_missing',
		} );
		emitted.add( key );
	}

	return result;
};
