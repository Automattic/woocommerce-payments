/**
 * Internal dependencies
 */
import { getTerms, getCookieValue } from '../upe';

describe( 'UPE checkout utils', () => {
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
			expect( getTerms( paymentMethods, 'always' ) ).toMatchObject(
				terms.always
			);
		} );

		it( 'should use a specified value for the terms parameter', () => {
			expect( getTerms( paymentMethods, 'never' ) ).toMatchObject(
				terms.never
			);
		} );
	} );
	describe( 'getCookieValue', () => {
		const mockCookieGet = jest.fn();

		Object.defineProperty( document, 'cookie', {
			get: mockCookieGet,
		} );

		beforeEach( () => {
			mockCookieGet.mockReturnValue(
				'woocommerce_items_in_cart=1; woocommerce_cart_hash=4a2d0baa7ee12ffa935450f63945824b;'
			);
		} );

		afterEach( () => {
			mockCookieGet.mockReturnValue( '' );
		} );

		it( 'should get the value of the specified cookie', () => {
			expect( getCookieValue( 'woocommerce_cart_hash' ) ).toBe(
				'4a2d0baa7ee12ffa935450f63945824b'
			);
		} );

		it( 'should return an empty string when no cookie is found', () => {
			mockCookieGet.mockReturnValue(
				'woocommerce_items_in_cart=1; woocommerce_cart_hash=4a2d0baa7ee12ffa935450f63945824b;'
			);
			expect( getCookieValue( 'nom_nom_nom' ) ).toBe( '' );
		} );
	} );
} );
