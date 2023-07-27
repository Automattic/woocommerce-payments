/**
 * Internal dependencies
 */
import {
	getTerms,
	getCookieValue,
	isWCPayChosen,
	getPaymentIntentFromSession,
	generateCheckoutEventNames,
	getUpeSettings,
	getStripeElementOptions,
} from '../upe';
import { getPaymentMethodsConstants } from '../../constants';
import { getUPEConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/utils/checkout' );

jest.mock( '../../constants', () => {
	return {
		getPaymentMethodsConstants: jest.fn(),
	};
} );

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

	describe( 'getUPESettings', () => {
		afterEach( () => {
			const checkboxElement = document.getElementById(
				'wc-woocommerce_payments-new-payment-method'
			);
			if ( checkboxElement ) {
				checkboxElement.remove();
			}
		} );

		it( 'should not provide terms when cart does not contain subscriptions and the saving checkbox is unchecked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return false;
				}
			} );

			createCheckboxElementWhich( false );

			const upeSettings = getUpeSettings();

			expect( upeSettings.terms.card ).toEqual( 'never' );
		} );

		it( 'should provide terms when cart does not contain subscriptions but the saving checkbox is checked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return false;
				}
			} );

			createCheckboxElementWhich( true );

			const upeSettings = getUpeSettings();

			// console.log(result);
			expect( upeSettings.terms.card ).toEqual( 'always' );
		} );

		it( 'should provide terms when cart contains subscriptions but the saving checkbox is unchecked', () => {
			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'paymentMethodsConfig' ) {
					return {
						card: {
							label: 'Card',
							isReusable: true,
						},
					};
				}

				if ( argument === 'cartContainsSubscription' ) {
					return true;
				}
			} );

			createCheckboxElementWhich( false );
			const upeSettings = getUpeSettings();

			expect( upeSettings.terms.card ).toEqual( 'always' );
		} );

		function createCheckboxElementWhich( isChecked ) {
			// Create the checkbox element
			const checkboxElement = document.createElement( 'input' );
			checkboxElement.type = 'checkbox';
			checkboxElement.checked = isChecked;
			checkboxElement.id = 'wc-woocommerce_payments-new-payment-method';

			document.body.appendChild( checkboxElement );
		}
	} );

	describe( 'generateCheckoutEventNames', () => {
		it( 'should return empty string when there are no payment methods', () => {
			getPaymentMethodsConstants.mockImplementation( () => [] );

			const result = generateCheckoutEventNames();

			expect( result ).toEqual( '' );
		} );

		it( 'should generate correct event names when there are payment methods', () => {
			getPaymentMethodsConstants.mockImplementation( () => [
				'woocommerce_payments_bancontact',
				'woocommerce_payments_eps',
			] );

			const result = generateCheckoutEventNames();

			expect( result ).toEqual(
				'checkout_place_order_woocommerce_payments_bancontact checkout_place_order_woocommerce_payments_eps'
			);
		} );
	} );
} );

describe( 'getStripeElementOptions', () => {
	test( 'should return options with "always" terms for cart containing subscription', () => {
		const shouldSavePayment = false;
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return true;
			}
		} );
		const paymentMethodsConfig = {
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

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { bancontact: 'always', card: 'always', eps: 'always' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );

	test( 'should return options with "always" terms when checkbox to save payment method is checked', () => {
		const shouldSavePayment = true;
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return false;
			}
		} );
		const paymentMethodsConfig = {
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

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { bancontact: 'always', card: 'always', eps: 'always' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );

	test( 'should return options with "never" for terms when shouldSavePayment is false and no subscription in cart', () => {
		const shouldSavePayment = false;
		const paymentMethodsConfig = {
			card: {
				isReusable: true,
			},
		};

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'cartContainsSubscription' ) {
				return false;
			}
		} );

		const options = getStripeElementOptions(
			shouldSavePayment,
			paymentMethodsConfig
		);

		expect( options ).toEqual( {
			fields: {
				billingDetails: {
					address: {
						city: 'never',
						country: 'never',
						line1: 'never',
						line2: 'never',
						postalCode: 'never',
						state: 'never',
					},
					email: 'never',
					name: 'never',
					phone: 'never',
				},
			},
			terms: { card: 'never' },
			wallets: { applePay: 'never', googlePay: 'never' },
		} );
	} );
} );
