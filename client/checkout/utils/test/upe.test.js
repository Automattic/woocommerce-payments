/**
 * Internal dependencies
 */
import {
	getTerms,
	getCookieValue,
	isWCPayChosen,
	getPaymentIntentFromSession,
} from '../upe';

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

	describe( 'getCookieValue', () => {
		beforeAll( () => {
			Object.defineProperty( document, 'cookie', {
				get: () => {
					return 'woocommerce_items_in_cart=1; woocommerce_cart_hash=4a2d0baa7ee12ffa935450f63945824b;';
				},
				configurable: true,
			} );
		} );

		it( 'should get the value of the specified cookie', () => {
			expect( getCookieValue( 'woocommerce_cart_hash' ) ).toBe(
				'4a2d0baa7ee12ffa935450f63945824b'
			);
		} );

		it( 'should return an empty string when no cookie is found', () => {
			expect( getCookieValue( 'nom_nom_nom' ) ).toBe( '' );
		} );
	} );

	describe( 'isWCPayChosen', () => {
		const container = document.createElement( 'div' );

		it( 'should return true when WCPay is chosen', () => {
			container.innerHTML =
				'<input type="radio" id="payment_method_woocommerce_payments" value="woocommerce-payments" checked>';
			document.body.appendChild( container );
			expect( isWCPayChosen() ).toBe( true );
		} );

		it( 'should return false when WCPay is not chosen', () => {
			container.innerHTML = `
				<input type="radio" id="payment_method_woocommerce_payments" value="woocommerce-payments">
				<input type="radio" id="payment_method_woocommerce_payments_bancontact" value="bancontact" checked>
				`;
			document.body.appendChild( container );
			expect( isWCPayChosen() ).toBe( false );
		} );
	} );

	describe( 'getPaymentIntentFromSession', () => {
		const paymentMethodsConfig = {
			card: {
				upePaymentIntentData:
					'abcd1234-pi_abc123-pi_abc123_secret_5678xyz',
			},
			eps: {
				upePaymentIntentData: null,
			},
		};

		const cardData = {
			clientSecret: 'pi_abc123_secret_5678xyz',
			intentId: 'pi_abc123',
		};

		it( 'should return the correct client secret and intent ID', () => {
			Object.defineProperty( document, 'cookie', {
				get: () => {
					return 'woocommerce_cart_hash=abcd1234;';
				},
				configurable: true,
			} );
			expect(
				getPaymentIntentFromSession( paymentMethodsConfig, 'card' )
			).toEqual( cardData );
		} );

		it( 'should return an empty object if no payment intent exists', () => {
			Object.defineProperty( document, 'cookie', {
				get: () => {
					return 'woocommerce_cart_hash=abcd1234;';
				},
				configurable: true,
			} );
			expect(
				getPaymentIntentFromSession( paymentMethodsConfig, 'eps' )
			).toEqual( {} );
		} );

		it( 'should return an empty object if no cart hash exists', () => {
			Object.defineProperty( document, 'cookie', {
				get: () => {
					return 'woocommerce_cart_items=1;';
				},
				configurable: true,
			} );
			expect(
				getPaymentIntentFromSession( paymentMethodsConfig, 'card' )
			).toEqual( {} );
		} );

		it( 'should return an empty object if the payment intent data does not start with the cart hash', () => {
			Object.defineProperty( document, 'cookie', {
				get: () => {
					return 'woocommerce_cart_hash=xyz9876;';
				},
				configurable: true,
			} );
			expect(
				getPaymentIntentFromSession( paymentMethodsConfig, 'card' )
			).toEqual( {} );
		} );
	} );
} );
