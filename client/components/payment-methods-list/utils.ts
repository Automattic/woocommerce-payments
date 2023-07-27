/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const DocumentationUrlForDisabledPaymentMethod = {
	DEFAULT:
		'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
	BNPLS:
		'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
};

export const getDocumentationUrlForDisabledPaymentMethod = (
	paymentMethodId: string
): string => {
	let link;
	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
		case PAYMENT_METHOD_IDS.AFFIRM:
			link = DocumentationUrlForDisabledPaymentMethod.BNPLS;
			break;
		default:
			link = DocumentationUrlForDisabledPaymentMethod.DEFAULT;
	}
	return link;
};
