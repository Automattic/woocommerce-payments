/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const getDocumentationLink = ( paymentMethodId ) => {
	let link;
	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
		case PAYMENT_METHOD_IDS.AFFIRM:
			link =
				'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support';
			break;
		default:
			link =
				'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled';
	}
	return link;
};
