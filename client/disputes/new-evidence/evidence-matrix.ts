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
 * @param reason - The dispute reason code
 * @param productType - The product type
 * @param status - Optional status for status-dependent reasons (e.g., duplicateStatus or refundStatus)
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
