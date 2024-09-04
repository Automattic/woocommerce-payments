export const PAYMENT_METHOD_NAME_CARD = 'woo_payments';
export const PAYMENT_METHOD_NAME_BANCONTACT = 'woo_payments_bancontact';
export const PAYMENT_METHOD_NAME_BECS = 'woo_payments_au_becs_debit';
export const PAYMENT_METHOD_NAME_EPS = 'woo_payments_eps';
export const PAYMENT_METHOD_NAME_GIROPAY = 'woo_payments_giropay';
export const PAYMENT_METHOD_NAME_IDEAL = 'woo_payments_ideal';
export const PAYMENT_METHOD_NAME_P24 = 'woo_payments_p24';
export const PAYMENT_METHOD_NAME_SEPA = 'woo_payments_sepa_debit';
export const PAYMENT_METHOD_NAME_SOFORT = 'woo_payments_sofort';
export const PAYMENT_METHOD_NAME_AFFIRM = 'woo_payments_affirm';
export const PAYMENT_METHOD_NAME_AFTERPAY = 'woo_payments_afterpay_clearpay';
export const PAYMENT_METHOD_NAME_KLARNA = 'woo_payments_klarna';
export const PAYMENT_METHOD_NAME_PAYMENT_REQUEST =
	'woo_payments_payment_request';
export const PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT = 'woo_payments';
export const PAYMENT_METHOD_NAME_WOOPAY_EXPRESS_CHECKOUT =
	'woo_payments_woopay';
export const PAYMENT_METHOD_ERROR = 'woo_payments_payment_method_error';
export const WC_STORE_CART = 'wc/store/cart';

export function getPaymentMethodsConstants() {
	return [
		PAYMENT_METHOD_NAME_BANCONTACT,
		PAYMENT_METHOD_NAME_BECS,
		PAYMENT_METHOD_NAME_EPS,
		PAYMENT_METHOD_NAME_GIROPAY,
		PAYMENT_METHOD_NAME_IDEAL,
		PAYMENT_METHOD_NAME_P24,
		PAYMENT_METHOD_NAME_SEPA,
		PAYMENT_METHOD_NAME_SOFORT,
		PAYMENT_METHOD_NAME_AFFIRM,
		PAYMENT_METHOD_NAME_AFTERPAY,
		PAYMENT_METHOD_NAME_CARD,
		PAYMENT_METHOD_NAME_KLARNA,
	];
}

export const SHORTCODE_SHIPPING_ADDRESS_FIELDS = {
	address_1: 'shipping_address_1',
	address_2: 'shipping_address_2',
	city: 'shipping_city',
	state: 'shipping_state',
	postcode: 'shipping_postcode',
	country: 'shipping_country',
	first_name: 'shipping_first_name',
	last_name: 'shipping_last_name',
};
export const SHORTCODE_BILLING_ADDRESS_FIELDS = {
	address_1: 'billing_address_1',
	address_2: 'billing_address_2',
	city: 'billing_city',
	state: 'billing_state',
	postcode: 'billing_postcode',
	country: 'billing_country',
	first_name: 'billing_first_name',
	last_name: 'billing_last_name',
};
