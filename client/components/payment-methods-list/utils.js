/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const getDocumentationLinkForDisabledPaymentMethod = (
	paymentMethodId
) => {
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
	return (
		// eslint-disable-next-line jsx-a11y/anchor-has-content
		<a
			target="_blank"
			rel="noreferrer"
			/* eslint-disable-next-line max-len */
			href={ link }
		/>
	);
};
