/**
 * Internal dependencies
 */
import { getTerms } from '../upe';

describe( 'Shared UPE checkout utils', () => {
	describe( 'getTerms', () => {
		const paymentMethods = {
			card: {
				isReusable: true,
			},
			bancontact: {
				isReusable: true,
			},
			eps: {
				isReusable: true,
			},
			giropay: {
				isReusable: false,
			},
		};

		const terms = {
			always: {
				card: 'always',
				bancontact: 'always',
				eps: 'always',
			},
			never: {
				card: 'never',
				bancontact: 'never',
				eps: 'never',
			},
		};

		it( 'should only generate a terms parameter for reusable payment methods', () => {
			expect( getTerms( paymentMethods, 'always' ) ).toEqual(
				terms.always
			);
		} );

		it( 'should use a specified value for the terms parameter', () => {
			expect( getTerms( paymentMethods, 'never' ) ).toEqual(
				terms.never
			);
		} );

		it( 'should exclude express checkout methods from terms', () => {
			const methodsWithExpressCheckout = {
				...paymentMethods,
				apple_pay: {
					isReusable: true,
					isExpressCheckout: true,
				},
				google_pay: {
					isReusable: true,
					isExpressCheckout: true,
				},
				amazon_pay: {
					isReusable: true,
					isExpressCheckout: true,
				},
			};

			expect( getTerms( methodsWithExpressCheckout, 'always' ) ).toEqual(
				terms.always
			);
		} );
	} );
} );
