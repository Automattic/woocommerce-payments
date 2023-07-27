/**
 * Internal dependencies
 */
import {
	getDocumentationUrlForDisabledPaymentMethod,
	DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD,
} from '../utils';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

describe( 'getDocumentationUrlForDisabledPaymentMethod', () => {
	test.each( [
		[
			PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY,
			DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.BNPLS,
		],
		[
			PAYMENT_METHOD_IDS.AFFIRM,
			DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.BNPLS,
		],
		[
			PAYMENT_METHOD_IDS.BACS,
			DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.DEFAULT,
		],
		[ 'unknown', DOCUMENTATION_URL_FOR_DISABLED_PAYMENT_METHOD.DEFAULT ],
	] )(
		'returns the correct documentation URL for %s',
		( paymentMethodId, expected ) => {
			expect(
				getDocumentationUrlForDisabledPaymentMethod( paymentMethodId )
			).toBe( expected );
		}
	);
} );
