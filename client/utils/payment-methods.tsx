/** @format */
/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentMethodInformationObject from 'wcpay/payment-methods-map';

export const getPaymentMethodDescription = (
	paymentMethodId: string,
	currency: string
): string | null => {
	let {
		description,
		allows_pay_later: allowsPayLater,
	} = PaymentMethodInformationObject[ paymentMethodId ];

	if ( ! description ) return null;

	if ( allowsPayLater ) {
		description = sprintf( description, currency.toUpperCase() );
	}

	return description;
};
