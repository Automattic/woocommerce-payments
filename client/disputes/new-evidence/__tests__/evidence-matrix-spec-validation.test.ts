/**
 * Spec-Driven Evidence Matrix Validation Tests
 *
 * This file validates the implementation against the specification document:
 * "Reason Code x Product Type Logic"
 *
 * The tests ensure that for each implemented Reason × Product Type combination:
 * 1. UI shows the correct document fields (getRecommendedDocumentFields)
 * 2. Cover letter includes the correct attachments (generateAttachments)
 *
 * IMPLEMENTATION STATUS (from evidence-matrix.ts):
 * ✅ Implemented combinations:
 *   - fraudulent × booking_reservation
 *   - fraudulent × physical_product
 *   - fraudulent × digital_product_or_service
 *   - product_not_received × booking_reservation
 *   - product_not_received × physical_product
 *   - product_not_received × digital_product_or_service
 *   - product_unacceptable × booking_reservation
 *   - product_unacceptable × physical_product
 *   - product_unacceptable × digital_product_or_service
 *   - subscription_canceled × booking_reservation
 *   - subscription_canceled × physical_product
 *   - subscription_canceled × digital_product_or_service
 *   - subscription_canceled × other
 *   - subscription_canceled × multiple
 *   - duplicate × booking_reservation (is_duplicate scenario)
 *   - duplicate × booking_reservation (is_not_duplicate scenario)
 *   - duplicate × physical_product (is_duplicate scenario)
 *   - duplicate × physical_product (is_not_duplicate scenario)
 *   - duplicate × digital_product_or_service (is_duplicate scenario)
 *   - duplicate × digital_product_or_service (is_not_duplicate scenario)
 *   - credit_not_processed × booking_reservation (refund_has_been_issued scenario)
 *   - credit_not_processed × booking_reservation (refund_was_not_owed scenario)
 *   - credit_not_processed × physical_product (refund_has_been_issued scenario)
 *   - credit_not_processed × physical_product (refund_was_not_owed scenario)
 *   - credit_not_processed × digital_product_or_service (refund_has_been_issued scenario)
 *   - credit_not_processed × digital_product_or_service (refund_was_not_owed scenario)
 *   - fraudulent × offline_service
 *   - product_not_received × offline_service
 *   - product_unacceptable × offline_service
 *   - subscription_canceled × offline_service
 *   - credit_not_processed × offline_service (refund_has_been_issued scenario)
 *   - credit_not_processed × offline_service (refund_was_not_owed scenario)
 *   - duplicate × offline_service (is_duplicate scenario)
 *   - duplicate × offline_service (is_not_duplicate scenario)
 *   - fraudulent × event
 *   - product_not_received × event
 *   - product_unacceptable × event
 *   - subscription_canceled × event
 *   - credit_not_processed × event (refund_has_been_issued scenario)
 *   - credit_not_processed × event (refund_was_not_owed scenario)
 *   - duplicate × event (is_duplicate scenario)
 *   - duplicate × event (is_not_duplicate scenario)
 *
 * ⏳ Not yet implemented (in backlog):
 *   - Remaining combinations with other
 *   - general (all product types)
 */

/**
 * Internal dependencies
 */
import { getRecommendedDocumentFields } from '../recommended-document-fields';
import { generateAttachments } from '../cover-letter-generator';
import { getMatrixFields } from '../evidence-matrix';
import { DOCUMENT_FIELD_KEYS } from '../document-field-keys';
import type { DisputeReason } from 'wcpay/types/disputes';

// Mock wcpaySettings with feature flag enabled
declare const global: {
	wcpaySettings: {
		featureFlags: {
			isDisputeAdditionalEvidenceTypesEnabled: boolean;
		};
	};
};

/**
 * Specification for each Reason × Product Type combination.
 *
 * This is derived from the "Reason Code x Product Type Logic" specification document.
 * Each entry defines:
 * - shouldInclude: Document keys that MUST be present
 * - shouldExclude: Document keys that MUST NOT be present
 * - labels: Expected labels for UI display
 */
interface CombinationSpec {
	reason: DisputeReason;
	productType: string;
	status?: string; // For duplicate disputes
	refundStatus?: string; // For credit_not_processed disputes
	description: string;
	uiFields: {
		shouldInclude: string[];
		shouldExclude?: string[];
		expectedLabels: Record< string, string >;
	};
	coverLetterAttachments: {
		shouldInclude: string[];
		shouldExclude?: string[];
	};
}

/**
 * Implemented combinations specification.
 *
 * Based on evidence-matrix.ts and the specification document.
 */
const implementedCombinations: CombinationSpec[] = [
	// ============================================
	// FRAUDULENT × PHYSICAL_PRODUCT
	// ============================================
	{
		reason: 'fraudulent',
		productType: 'physical_product',
		description:
			'Fraudulent dispute for physical product - needs receipt, prior history, signature, refund policy',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION, // Shown separately in shipping step
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Prior undisputed transaction history',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ]:
					"Customer's signature",
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Prior undisputed transaction history',
				"Customer's signature",
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// FRAUDULENT × BOOKING/RESERVATION
	// ============================================
	{
		reason: 'fraudulent',
		productType: 'booking_reservation',
		description:
			'Fraudulent dispute for booking/reservation - minimal evidence needed',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE, // Not for booking/reservation
				DOCUMENT_FIELD_KEYS.RECEIPT, // Not in spec for this combination
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION, // Not for booking/reservation
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Prior undisputed transaction history',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Prior undisputed transaction history',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT NOT RECEIVED × BOOKING/RESERVATION
	// ============================================
	{
		reason: 'product_not_received',
		productType: 'booking_reservation',
		description:
			'Product not received for booking/reservation - needs reservation confirmation',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE, // Not for booking/reservation
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION, // Not for booking/reservation
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Reservation or booking confirmation',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation confirmation',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Reservation or booking confirmation',
				'Cancellation confirmation',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT NOT RECEIVED × PHYSICAL PRODUCT
	// ============================================
	{
		reason: 'product_not_received',
		productType: 'physical_product',
		description:
			'Product not received for physical product - needs receipt, signature, refund policy. Shipping page also shows proof of delivery (customer_signature).',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION, // Shown separately in shipping step
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION, // Not for physical products
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL, // Not in spec for physical product
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ]:
					"Customer's signature",
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Customer communication',
				"Customer's signature",
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// PRODUCT UNACCEPTABLE × PHYSICAL PRODUCT
	// ============================================
	{
		reason: 'product_unacceptable',
		productType: 'physical_product',
		description:
			"Product unacceptable for physical product - needs receipt, signature, refund policy, item's condition",
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION, // Shown separately in shipping step
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG, // Not for physical product product_unacceptable
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ]:
					"Customer's signature",
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					"Item's condition",
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				"Item's condition",
				'Customer communication',
				"Customer's signature",
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// PRODUCT UNACCEPTABLE × BOOKING/RESERVATION
	// ============================================
	{
		reason: 'product_unacceptable',
		productType: 'booking_reservation',
		description:
			'Product unacceptable for booking/reservation - needs event/booking documentation',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE, // Not for booking/reservation
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Event or booking documentation',
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Event or booking documentation',
				'Order receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × BOOKING/RESERVATION
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'booking_reservation',
		description:
			'Subscription canceled for booking/reservation - needs cancellation logs',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.REFUND_POLICY, // Not in spec for booking/reservation
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Cancellation logs',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × OTHER
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'other',
		description:
			'Subscription canceled for "other" product type - simplified fields',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL, // Not in spec for "other"
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Terms of service',
				'Other documents',
			],
			// NOTE: Cover letter generator doesn't yet filter Cancellation logs by product type
			// This is a known limitation - the cover letter shows all possible attachments
			// for the dispute reason, not filtered by product type
			shouldExclude: [],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × MULTIPLE
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'multiple',
		description:
			'Subscription canceled for multiple products - no subscription logs (products may have different states)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL, // Not for "multiple"
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund policy',
				'Terms of service',
				'Other documents',
			],
			// NOTE: Cover letter generator doesn't yet filter Cancellation logs by product type
			// This is a known limitation - the cover letter shows all possible attachments
			// for the dispute reason, not filtered by product type
			shouldExclude: [],
		},
	},

	// ============================================
	// DUPLICATE × BOOKING/RESERVATION (IS DUPLICATE - Scenario A)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'booking_reservation',
		status: 'is_duplicate',
		description:
			'Duplicate dispute for booking/reservation - it WAS a duplicate (refund issued)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				// Note: REFUND_RECEIPT_DOCUMENTATION uses duplicate_charge_documentation key
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// DUPLICATE × BOOKING/RESERVATION (IS NOT DUPLICATE - Scenario B)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'booking_reservation',
		status: 'is_not_duplicate',
		description:
			'Duplicate dispute for booking/reservation - it was NOT a duplicate (both charges valid)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Any additional receipts',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Any additional receipts',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × BOOKING/RESERVATION (REFUND HAS BEEN ISSUED - Scenario A)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'booking_reservation',
		refundStatus: 'refund_has_been_issued',
		description:
			'Credit not processed for booking/reservation - refund has been issued (Scenario A)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Refund receipt',
				'Cancellation logs',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × BOOKING/RESERVATION (REFUND WAS NOT OWED - Scenario B)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'booking_reservation',
		refundStatus: 'refund_was_not_owed',
		description:
			'Credit not processed for booking/reservation - refund was not owed (Scenario B)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT, // Not in detailed spec for Scenario B
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE, // Not for booking/reservation
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION, // Not in spec for this scenario
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]:
					'Proof of acceptance',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of acceptance',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × PHYSICAL PRODUCT
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'physical_product',
		description:
			'Subscription canceled for physical product - needs cancellation logs, refund policy',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE, // Not for subscription_canceled
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Cancellation logs',
				'Refund policy',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// DUPLICATE × PHYSICAL PRODUCT (IS DUPLICATE - Scenario A)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'physical_product',
		status: 'is_duplicate',
		description:
			'Duplicate dispute for physical product - it WAS a duplicate (refund issued)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.REFUND_RECEIPT_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_RECEIPT_DOCUMENTATION ]:
					'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Proof of active subscription',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Proof of active subscription',
				'Refund policy',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// DUPLICATE × PHYSICAL PRODUCT (IS NOT DUPLICATE - Scenario B)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'physical_product',
		status: 'is_not_duplicate',
		description:
			'Duplicate dispute for physical product - it was NOT a duplicate (both charges valid)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Any additional receipts',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Any additional receipts',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// FRAUDULENT × DIGITAL PRODUCT/SERVICE
	// ============================================
	{
		reason: 'fraudulent',
		productType: 'digital_product_or_service',
		description:
			'Fraudulent dispute for digital product/service - needs login/usage records, prior history, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Login or usage records',
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Prior undisputed transaction history',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Login or usage records',
				'Prior undisputed transaction history',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT NOT RECEIVED × DIGITAL PRODUCT/SERVICE
	// ============================================
	{
		reason: 'product_not_received',
		productType: 'digital_product_or_service',
		description:
			'Product not received for digital product/service - needs receipt, login/usage records, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Login or usage records',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Login or usage records',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT UNACCEPTABLE × DIGITAL PRODUCT/SERVICE
	// ============================================
	{
		reason: 'product_unacceptable',
		productType: 'digital_product_or_service',
		description:
			'Product unacceptable for digital product/service - needs proof of delivered service, receipt, login/usage records',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Proof of delivered service',
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Login or usage records',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of delivered service',
				'Order receipt',
				'Login or usage records',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × DIGITAL PRODUCT/SERVICE
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'digital_product_or_service',
		description:
			'Subscription canceled for digital product/service - needs cancellation logs, login/usage records, terms of service',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Login or usage records',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Cancellation logs',
				'Login or usage records',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × DIGITAL PRODUCT/SERVICE (REFUND HAS BEEN ISSUED - Scenario A)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'digital_product_or_service',
		refundStatus: 'refund_has_been_issued',
		description:
			'Credit not processed for digital product/service - refund has been issued (Scenario A)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Refund receipt',
				'Cancellation logs',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × DIGITAL PRODUCT/SERVICE (REFUND WAS NOT OWED - Scenario B)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'digital_product_or_service',
		refundStatus: 'refund_was_not_owed',
		description:
			'Credit not processed for digital product/service - refund was not owed (Scenario B)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]:
					'Proof of acceptance',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of acceptance',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// DUPLICATE × DIGITAL PRODUCT/SERVICE (IS DUPLICATE - Scenario A)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'digital_product_or_service',
		status: 'is_duplicate',
		description:
			'Duplicate dispute for digital product/service - it WAS a duplicate (refund issued)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// DUPLICATE × DIGITAL PRODUCT/SERVICE (IS NOT DUPLICATE - Scenario B)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'digital_product_or_service',
		status: 'is_not_duplicate',
		description:
			'Duplicate dispute for digital product/service - it was NOT a duplicate (both charges valid)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Any additional receipts',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Any additional receipts',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// FRAUDULENT × OFFLINE SERVICE
	// ============================================
	{
		reason: 'fraudulent',
		productType: 'offline_service',
		description:
			'Fraudulent dispute for offline service - needs prior history, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Prior undisputed transaction history',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Prior undisputed transaction history',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT NOT RECEIVED × OFFLINE SERVICE
	// ============================================
	{
		reason: 'product_not_received',
		productType: 'offline_service',
		description:
			'Product not received for offline service - needs receipt, proof of service completion, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Proof of service completion',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Proof of service completion',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT UNACCEPTABLE × OFFLINE SERVICE
	// ============================================
	{
		reason: 'product_unacceptable',
		productType: 'offline_service',
		description:
			'Product unacceptable for offline service - needs proof of delivered service, receipt, refund policy',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Proof of delivered service',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of delivered service',
				'Order receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × OFFLINE SERVICE
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'offline_service',
		description:
			'Subscription canceled for offline service - needs cancellation logs, terms of service',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.REFUND_POLICY ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Cancellation logs',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × OFFLINE SERVICE (REFUND HAS BEEN ISSUED - Scenario A)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'offline_service',
		refundStatus: 'refund_has_been_issued',
		description:
			'Credit not processed for offline service - refund has been issued (Scenario A)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Refund receipt',
				'Cancellation logs',
				'Other documents',
			],
			shouldExclude: [
				'Order receipt',
				"Customer's signature",
				'Customer communication',
			],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × OFFLINE SERVICE (REFUND WAS NOT OWED - Scenario B)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'offline_service',
		refundStatus: 'refund_was_not_owed',
		description:
			'Credit not processed for offline service - refund was not owed (Scenario B)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]:
					'Proof of acceptance',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of acceptance',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature", 'Customer communication' ],
		},
	},

	// ============================================
	// DUPLICATE × OFFLINE SERVICE (IS DUPLICATE - Scenario A)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'offline_service',
		status: 'is_duplicate',
		description:
			'Duplicate dispute for offline service - it WAS a duplicate (refund issued)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Refund receipt',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// DUPLICATE × OFFLINE SERVICE (IS NOT DUPLICATE - Scenario B)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'offline_service',
		status: 'is_not_duplicate',
		description:
			'Duplicate dispute for offline service - it was NOT a duplicate (both charges valid)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Any additional receipts',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Any additional receipts',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// FRAUDULENT × EVENT
	// ============================================
	{
		reason: 'fraudulent',
		productType: 'event',
		description:
			'Fraudulent dispute for event - needs prior history, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.ACCESS_ACTIVITY_LOG ]:
					'Prior undisputed transaction history',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Prior undisputed transaction history',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT NOT RECEIVED × EVENT
	// ============================================
	{
		reason: 'product_not_received',
		productType: 'event',
		description:
			'Product not received for event - needs receipt, attendance confirmation, other documents',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Attendance confirmation',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Attendance confirmation',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// PRODUCT UNACCEPTABLE × EVENT
	// ============================================
	{
		reason: 'product_unacceptable',
		productType: 'event',
		description:
			'Product unacceptable for event - needs event or booking documentation, receipt, refund policy',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Event or booking documentation',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Event or booking documentation',
				'Order receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// SUBSCRIPTION CANCELED × EVENT
	// ============================================
	{
		reason: 'subscription_canceled',
		productType: 'event',
		description:
			'Subscription canceled for event - needs cancellation logs, terms of service',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.REFUND_POLICY ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_POLICY ]: 'Terms of service',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Cancellation logs',
				'Terms of service',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × EVENT (REFUND HAS BEEN ISSUED - Scenario A)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'event',
		refundStatus: 'refund_has_been_issued',
		description:
			'Credit not processed for event - refund has been issued (Scenario A)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ]:
					'Cancellation logs',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Refund receipt',
				'Cancellation logs',
				'Other documents',
			],
			shouldExclude: [
				'Order receipt',
				"Customer's signature",
				'Customer communication',
			],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × EVENT (REFUND WAS NOT OWED - Scenario B)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'event',
		refundStatus: 'refund_was_not_owed',
		description:
			'Credit not processed for event - refund was not owed (Scenario B)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]:
					'Proof of acceptance',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of acceptance',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature", 'Customer communication' ],
		},
	},

	// ============================================
	// DUPLICATE × EVENT (IS DUPLICATE - Scenario A)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'event',
		status: 'is_duplicate',
		description:
			'Duplicate dispute for event - it WAS a duplicate (refund issued)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Refund receipt',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// DUPLICATE × EVENT (IS NOT DUPLICATE - Scenario B)
	// ============================================
	{
		reason: 'duplicate',
		productType: 'event',
		status: 'is_not_duplicate',
		description:
			'Duplicate dispute for event - it was NOT a duplicate (both charges valid)',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.DUPLICATE_CHARGE_DOCUMENTATION ]:
					'Any additional receipts',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Any additional receipts',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [ "Customer's signature" ],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × PHYSICAL PRODUCT (REFUND HAS BEEN ISSUED - Scenario A)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'physical_product',
		refundStatus: 'refund_has_been_issued',
		description:
			'Credit not processed for physical product - refund has been issued (Scenario A) - 7 fields',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.REFUND_RECEIPT_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
			],
			shouldExclude: [ DOCUMENT_FIELD_KEYS.CANCELLATION_REBUTTAL ],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.RECEIPT ]: 'Order receipt',
				[ DOCUMENT_FIELD_KEYS.REFUND_RECEIPT_DOCUMENTATION ]:
					'Refund receipt',
				[ DOCUMENT_FIELD_KEYS.SHIPPING_DOCUMENTATION ]:
					'Return tracking',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE ]:
					"Customer's signature",
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]: 'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Order receipt',
				'Refund receipt',
				'Return tracking',
				'Customer communication',
				"Customer's signature",
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},

	// ============================================
	// CREDIT NOT PROCESSED × PHYSICAL PRODUCT (REFUND WAS NOT OWED - Scenario B)
	// ============================================
	{
		reason: 'credit_not_processed',
		productType: 'physical_product',
		refundStatus: 'refund_was_not_owed',
		description:
			'Credit not processed for physical product - refund was not owed (Scenario B) - 4 fields',
		uiFields: {
			shouldInclude: [
				DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE,
				DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION,
				DOCUMENT_FIELD_KEYS.REFUND_POLICY,
				DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION,
			],
			shouldExclude: [
				DOCUMENT_FIELD_KEYS.RECEIPT,
				DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE,
			],
			expectedLabels: {
				[ DOCUMENT_FIELD_KEYS.UNCATEGORIZED_FILE ]:
					'Proof of acceptance',
				[ DOCUMENT_FIELD_KEYS.CUSTOMER_COMMUNICATION ]:
					'Customer communication',
				[ DOCUMENT_FIELD_KEYS.REFUND_POLICY ]: 'Refund policy',
				[ DOCUMENT_FIELD_KEYS.SERVICE_DOCUMENTATION ]:
					'Other documents',
			},
		},
		coverLetterAttachments: {
			shouldInclude: [
				'Proof of acceptance',
				'Customer communication',
				'Refund policy',
				'Other documents',
			],
			shouldExclude: [],
		},
	},
];

describe( 'Evidence Matrix Specification Validation', () => {
	beforeEach( () => {
		// Enable feature flag for all tests
		global.wcpaySettings = {
			featureFlags: {
				isDisputeAdditionalEvidenceTypesEnabled: true,
			},
		};
	} );

	describe( 'UI Document Fields (getRecommendedDocumentFields)', () => {
		describe.each( implementedCombinations )(
			'$reason × $productType ($description)',
			( spec ) => {
				it( 'should include all required document fields', () => {
					const fields = getRecommendedDocumentFields(
						spec.reason,
						spec.refundStatus, // refundStatus
						spec.status, // duplicateStatus
						spec.productType
					);

					const fieldKeys = fields.map( ( f ) => f.key );

					spec.uiFields.shouldInclude.forEach( ( requiredKey ) => {
						expect( fieldKeys ).toContain( requiredKey );
					} );
				} );

				it( 'should exclude non-applicable document fields', () => {
					if (
						! spec.uiFields.shouldExclude ||
						spec.uiFields.shouldExclude.length === 0
					) {
						return;
					}

					const fields = getRecommendedDocumentFields(
						spec.reason,
						spec.refundStatus,
						spec.status,
						spec.productType
					);

					const fieldKeys = fields.map( ( f ) => f.key );

					spec.uiFields.shouldExclude.forEach( ( excludedKey ) => {
						expect( fieldKeys ).not.toContain( excludedKey );
					} );
				} );

				it( 'should have correct labels for document fields', () => {
					const fields = getRecommendedDocumentFields(
						spec.reason,
						spec.refundStatus,
						spec.status,
						spec.productType
					);

					Object.entries( spec.uiFields.expectedLabels ).forEach(
						( [ key, expectedLabel ] ) => {
							const field = fields.find( ( f ) => f.key === key );
							expect( field ).toBeDefined();
							expect( field?.label ).toBe( expectedLabel );
						}
					);
				} );
			}
		);
	} );

	describe( 'Evidence Matrix (getMatrixFields)', () => {
		describe.each( implementedCombinations )(
			'$reason × $productType ($description)',
			( spec ) => {
				it( 'should return matrix fields for implemented combination', () => {
					const fields = getMatrixFields(
						spec.reason,
						spec.productType,
						spec.status || spec.refundStatus
					);

					expect( fields ).toBeDefined();
					expect( Array.isArray( fields ) ).toBe( true );
					if ( fields ) {
						expect( fields.length ).toBeGreaterThan( 0 );
					}
				} );
			}
		);

		describe( 'Non-implemented combinations should return undefined', () => {
			const notImplemented = [
				{ reason: 'credit_not_processed', productType: 'other' },
				{ reason: 'general', productType: 'booking_reservation' },
			];

			it.each( notImplemented )(
				'should return undefined for $reason × $productType',
				( { reason, productType } ) => {
					const fields = getMatrixFields( reason, productType );
					expect( fields ).toBeUndefined();
				}
			);
		} );
	} );

	describe( 'Cover Letter Attachments (generateAttachments)', () => {
		// Mock dispute for testing
		const createMockDispute = (
			reason: string,
			evidence: Record< string, string > = {}
		) => ( {
			id: 'dp_test',
			reason,
			status: 'needs_response' as const,
			amount: 1000,
			currency: 'usd',
			created: Date.now(),
			evidence_details: {
				due_by: Date.now() + 86400000,
				has_evidence: false,
				past_due: false,
				submission_count: 0,
			},
			evidence: {
				...evidence,
			},
			order: {
				number: '123',
				url: 'https://example.com/order/123',
				customer_url: '',
			},
			balance_transactions: [],
			charge: 'ch_test',
		} );

		describe.each(
			implementedCombinations.filter(
				( spec ) => spec.coverLetterAttachments.shouldInclude.length > 0
			)
		)( '$reason × $productType ($description)', ( spec ) => {
			it( 'should include expected attachments in cover letter when evidence is provided', () => {
				// Create evidence object with all keys set
				const evidence: Record< string, string > = {};
				spec.uiFields.shouldInclude.forEach( ( key ) => {
					evidence[ key ] = `${ key }_url`;
				} );

				const dispute = createMockDispute( spec.reason, evidence );
				const attachments = generateAttachments(
					dispute as any,
					spec.status,
					spec.productType,
					spec.refundStatus
				);

				spec.coverLetterAttachments.shouldInclude.forEach(
					( expectedLabel ) => {
						expect( attachments ).toContain( expectedLabel );
					}
				);
			} );

			it( 'should exclude non-applicable attachments from cover letter', () => {
				if (
					! spec.coverLetterAttachments.shouldExclude ||
					spec.coverLetterAttachments.shouldExclude.length === 0
				) {
					return;
				}

				// Create evidence object with all possible keys set
				const evidence: Record< string, string > = {};
				Object.values( DOCUMENT_FIELD_KEYS ).forEach( ( key ) => {
					evidence[ key ] = `${ key }_url`;
				} );

				const dispute = createMockDispute( spec.reason, evidence );
				const attachments = generateAttachments(
					dispute as any,
					spec.status,
					spec.productType,
					spec.refundStatus
				);

				spec.coverLetterAttachments.shouldExclude.forEach(
					( excludedLabel ) => {
						expect( attachments ).not.toContain( excludedLabel );
					}
				);
			} );
		} );
	} );

	describe( "Customer's Signature Product Type Filtering", () => {
		/**
		 * Per specification: Customer's signature should ONLY appear for physical_product.
		 * This is a critical regression test for the bug where Customer's signature
		 * persisted when switching from Physical Product to Booking/Reservation.
		 *
		 * Note: UI field filtering only works for product types with matrix entries.
		 * Product types without matrix entries fall back to legacy logic which
		 * includes Customer's Signature. This is expected until those combinations
		 * are implemented in the evidence matrix.
		 */

		// Product types with implemented matrix entries for product_unacceptable
		const implementedProductTypesWithoutSignature = [
			'booking_reservation',
			'digital_product_or_service',
			'offline_service',
			'event',
		];

		// All product types that shouldn't have signature (for cover letter test)
		const allProductTypesWithoutSignature = [
			'booking_reservation',
			'digital_product_or_service',
			'offline_service',
			'event',
			'other',
			'multiple',
		];

		describe( 'UI Fields (implemented matrix entries only)', () => {
			it.each( implementedProductTypesWithoutSignature )(
				'should NOT include Customer Signature field for %s product type',
				( productType ) => {
					// Test with product_unacceptable which has signature for physical_product
					const fields = getRecommendedDocumentFields(
						'product_unacceptable',
						undefined,
						undefined,
						productType
					);

					const fieldKeys = fields.map( ( f ) => f.key );
					expect( fieldKeys ).not.toContain(
						DOCUMENT_FIELD_KEYS.CUSTOMER_SIGNATURE
					);
				}
			);
		} );

		describe( 'Cover Letter Attachments (all product types)', () => {
			/**
			 * Cover letter correctly filters Customer's Signature by product type
			 * via onlyForProductTypes filter, regardless of matrix implementation status.
			 */
			it.each( allProductTypesWithoutSignature )(
				'should NOT include Customer Signature in cover letter for %s product type',
				( productType ) => {
					const dispute = {
						id: 'dp_test',
						reason: 'product_unacceptable',
						status: 'needs_response' as const,
						amount: 1000,
						currency: 'usd',
						created: Date.now(),
						evidence_details: {
							due_by: Date.now() + 86400000,
							has_evidence: false,
							past_due: false,
							submission_count: 0,
						},
						evidence: {
							customer_signature: 'signature_url',
							receipt: 'receipt_url',
						},
						order: {
							number: '123',
							url: 'https://example.com/order/123',
							customer_url: '',
						},
						balance_transactions: [],
						charge: 'ch_test',
					};

					const attachments = generateAttachments(
						dispute as any,
						undefined,
						productType
					);

					expect( attachments ).not.toContain(
						"Customer's signature"
					);
				}
			);
		} );
	} );
} );

/**
 * Summary of Not Yet Implemented Combinations
 *
 * The following combinations are defined in the specification but not yet implemented.
 * These should be tracked in Linear and added to implementedCombinations as they are completed.
 *
 * Fraudulent:
 * - other (needs: Prior history, Other)
 *
 * Product Not Received:
 * - other (needs: Order receipt, Service completion records, Other)
 *
 * Product Unacceptable:
 * - other (needs: Order receipt, Terms of service, Other)
 *
 * Credit Not Processed (remaining product types):
 * - other (all scenarios)
 *
 * Duplicate (remaining product types - Scenario A & B):
 * - other
 *
 * General/Other:
 * - All product types (needs: Order receipt, Customer communication, Refund policy, Terms of service, Other)
 */
