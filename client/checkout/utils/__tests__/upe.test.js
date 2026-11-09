/**
 * Internal dependencies
 */
import { getLinkEmail, getTerms } from '../upe';

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
	} );

	describe( 'getLinkEmail', () => {
		it( 'should return the trimmed email when it looks valid', () => {
			expect( getLinkEmail( ' shopper@example.com ' ) ).toBe(
				'shopper@example.com'
			);
		} );

		it.each( [ '', 'shopper', 'shopper@example', 'a b@example.com' ] )(
			'should return undefined for "%s"',
			( email ) => {
				expect( getLinkEmail( email ) ).toBeUndefined();
			}
		);

		it( 'should return undefined when the email is missing', () => {
			expect( getLinkEmail( undefined ) ).toBeUndefined();
		} );
	} );
} );
