/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD = {
	DEFAULT:
		'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
	BNPLS:
		'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
};

export const getDocumentationUrlForDisabledPaymentMethod = (
	paymentMethodId
) => {
	let link;
	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
		case PAYMENT_METHOD_IDS.AFFIRM:
			link = DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.BNPLS;
			break;
		default:
			link = DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.DEFAULT;
	}
	return link;
};
