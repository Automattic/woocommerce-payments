/** @format */

export const ProtectionLevel = {
	BASIC: 'basic',
	ADVANCED: 'advanced',
	STANDARD: 'standard',
	HIGH: 'high',
};

export const Outcomes = {
	BLOCK: 'block',
	REVIEW: 'review',
	ALLOW: 'allow',
};

export const Rules = {
	RULE_AVS_VERIFICATION: 'avs_verification',
	RULE_ADDRESS_MISMATCH: 'address_mismatch',
	RULE_INTERNATIONAL_IP_ADDRESS: 'international_ip_address',
	RULE_IP_ADDRESS_MISMATCH: 'ip_address_mismatch',
	RULE_ORDER_ITEMS_THRESHOLD: 'order_items_threshold',
	RULE_PURCHASE_PRICE_THRESHOLD: 'purchase_price_threshold',
};

export const Checks = {
	CHECK_AVS_MISMATCH: 'avs_mismatch',
	CHECK_BILLING_SHIPPING_ADDRESS_SAME: 'billing_shipping_address_same',
	CHECK_IP_COUNTRY: 'ip_country',
	CHECK_IP_BILLING_COUNTRY_SAME: 'ip_billing_country_same',
	CHECK_ITEM_COUNT: 'item_count',
	CHECK_ORDER_TOTAL: 'order_total',
};

export const CheckOperators = {
	LIST_OPERATOR_AND: 'and',
	LIST_OPERATOR_OR: 'or',
	OPERATOR_EQUALS: 'equals',
	OPERATOR_NOT_EQUALS: 'not_equals',
	OPERATOR_GTE: 'greater_or_equal',
	OPERATOR_GT: 'greater_than',
	OPERATOR_LTE: 'less_or_equal',
	OPERATOR_LT: 'less_than',
	OPERATOR_IN: 'in',
	OPERATOR_NOT_IN: 'not_in',
};
