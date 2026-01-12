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
	REFUND_RECEIPT_DOCUMENTATION: 'uncategorized_file',
	DUPLICATE_CHARGE_DOCUMENTATION: 'duplicate_charge_documentation',
	CANCELLATION_POLICY: 'cancellation_policy',
	ACCESS_ACTIVITY_LOG: 'access_activity_log',
	SERVICE_DOCUMENTATION: 'service_documentation',
	SHIPPING_DOCUMENTATION: 'shipping_documentation',
} as const;
