/**
 * Internal dependencies
 */
import {
	getDocumentationUrlForDisabledPaymentMethod,
	DocumentationUrlForDisabledPaymentMethod,
} from '../utils';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

describe( 'getDocumentationUrlForDisabledPaymentMethod', () => {
	test.each( [
		[
			PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY,
			DocumentationUrlForDisabledPaymentMethod.BNPLS,
		],
		[
			PAYMENT_METHOD_IDS.AFFIRM,
			DocumentationUrlForDisabledPaymentMethod.BNPLS,
		],
		[
			PAYMENT_METHOD_IDS.P24,
			DocumentationUrlForDisabledPaymentMethod.DEFAULT,
		],
		[ 'unknown', DocumentationUrlForDisabledPaymentMethod.DEFAULT ],
	] )(
		'returns the correct documentation URL for %s',
		( paymentMethodId, expected ) => {
			expect(
				getDocumentationUrlForDisabledPaymentMethod( paymentMethodId )
			).toBe( expected );
		}
	);
} );
