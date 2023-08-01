/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';
import {
	getDocumentationUrlForDisabledPaymentMethod,
	DocumentationUrlForDisabledPaymentMethod,
} from '../utils';

describe( 'getDocumentationUrlForDisabledPaymentMethod', () => {
	test( 'returns the correct URL for Afterpay and Clearpay methods', () => {
		expect(
			getDocumentationUrlForDisabledPaymentMethod(
				PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY
			)
		).toBe( DocumentationUrlForDisabledPaymentMethod.BNPLS );
		expect(
			getDocumentationUrlForDisabledPaymentMethod(
				PAYMENT_METHOD_IDS.AFFIRM
			)
		).toBe( DocumentationUrlForDisabledPaymentMethod.BNPLS );
	} );

	test( 'returns the default URL for other payment methods', () => {
		expect(
			getDocumentationUrlForDisabledPaymentMethod(
				'other-payment-method'
			)
		).toBe( DocumentationUrlForDisabledPaymentMethod.DEFAULT );
	} );
} );
