/** @format */

export const ProtectionLevel = {
	BASIC: 'basic',
	STANDARD: 'standard',
	HIGH: 'high',
	ADVANCED: 'advanced',
};

export const Outcomes = {
	BLOCK: 'block',
	REVIEW: 'review',
	ALLOW: 'allow',
};

export const Rules = {
	RULE_ADDRESS_MISMATCH: 'address_mismatch',
	RULE_INTERNATIONAL_IP_ADDRESS: 'international_ip_address',
	RULE_INTERNATIONAL_BILLING_ADDRESS: 'international_billing_address',
	RULE_ORDER_ITEMS_THRESHOLD: 'order_items_threshold',
	RULE_PURCHASE_PRICE_THRESHOLD: 'purchase_price_threshold',
};

export const Checks = {
	CHECK_BILLING_SHIPPING_ADDRESS_SAME: 'billing_shipping_address_same',
	CHECK_IP_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY:
		'ip_country_same_with_account_country',
	CHECK_BILLING_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY:
		'billing_country_same_with_account_country',
	CHECK_ORDERS_SINCE_H: 'orders_since_%dh',
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
};
