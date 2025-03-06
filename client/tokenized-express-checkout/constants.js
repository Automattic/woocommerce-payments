// This const defines the max number of shipping options that can be handled by the ECE.
// More than 9 options will prevent the UI from behaving correctly.
// See https://github.com/Automattic/woocommerce-payments/issues/10490 .
export const SHIPPING_RATES_UPPER_LIMIT_COUNT = 9;
