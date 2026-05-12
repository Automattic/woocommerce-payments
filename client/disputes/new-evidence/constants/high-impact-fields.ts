/**
 * Internal dependencies
 */
import type { DisputeReason, ProductType } from 'wcpay/types/disputes';

/**
 * Default empty cell for reasons with no per-product-type entries. Returns a
 * fresh object so cells don't share mutable references.
 */
export const emptyByProductType = (): Record< ProductType, string[] > => ( {
	physical_product: [],
	digital_product_or_service: [],
	offline_service: [],
	event: [],
	booking_reservation: [],
	multiple: [],
	other: [],
} );

/**
 * Fields whose presence correlates with a higher win rate, per (reason,
 * product type). Surfaced as `expected_missing` (red) when absent. Empty
 * cells produce no markers.
 *
 * Excluded by design:
 *   - `customer_purchase_ip`, `customer_name`, `customer_email_address`,
 *     `billing_address` (auto-populated)
 *   - `product_description` (hybrid; placeholder default skews lift signal)
 *   - `uncategorized_file`, `uncategorized_text` (catch-alls; not actionable)
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
