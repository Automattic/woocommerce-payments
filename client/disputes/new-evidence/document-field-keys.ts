/**
 * Document field keys used across different dispute types.
 * These keys map to Stripe API evidence fields.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const DOCUMENT_FIELD_KEYS = {
	RECEIPT: 'receipt',
	CUSTOMER_COMMUNICATION: 'customer_communication',
	CUSTOMER_SIGNATURE: 'customer_signature',
	UNCATEGORIZED_FILE: 'uncategorized_file',
	REFUND_POLICY: 'refund_policy',
	// For duplicate disputes where it was a duplicate, we use duplicate_charge_documentation
	// to store the refund receipt since it's documentation related to the duplicate charge.
	REFUND_RECEIPT_DOCUMENTATION: 'duplicate_charge_documentation',
	DUPLICATE_CHARGE_DOCUMENTATION: 'duplicate_charge_documentation',
	CANCELLATION_POLICY: 'cancellation_policy',
	CANCELLATION_REBUTTAL: 'cancellation_rebuttal',
	ACCESS_ACTIVITY_LOG: 'access_activity_log',
	SERVICE_DOCUMENTATION: 'service_documentation',
	SHIPPING_DOCUMENTATION: 'shipping_documentation',
} as const;
