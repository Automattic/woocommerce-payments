/**
 * Internal dependencies
 */
import { getStripeElementOptions } from '../utils';
import { getUPEConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/utils/checkout' );

describe( 'Blocks checkout utils', () => {
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
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'never',
				},
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
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'never',
				},
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
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'never',
				},
			} );
		} );

		test( 'should return options with link: "auto" when both card and link are available', () => {
			const shouldSavePayment = false;
			const paymentMethodsConfig = {
				card: {
					isReusable: true,
				},
				link: {
					isReusable: false,
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
				wallets: {
					applePay: 'never',
					googlePay: 'never',
					link: 'auto',
				},
			} );
		} );
	} );
} );
