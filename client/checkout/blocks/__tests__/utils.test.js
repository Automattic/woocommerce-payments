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

		test( 'should return options with "never" terms when the cart contains a subscription that requires manual renewal', () => {
			const shouldSavePayment = false;
			const paymentMethodsConfig = {
				card: {
					isReusable: true,
				},
			};

			getUPEConfig.mockImplementation( ( argument ) => {
				if ( argument === 'cartContainsSubscription' ) {
					return true;
				}

				if ( argument === 'subscriptionRequiresManualRenewal' ) {
					return true;
				}
			} );

			const options = getStripeElementOptions(
				shouldSavePayment,
				paymentMethodsConfig
			);

			expect( options.terms ).toEqual( { card: 'never' } );
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

		describe( 'Link email', () => {
			beforeEach( () => {
				getUPEConfig.mockReturnValue( false );
			} );

			it( 'should pass the email as default value when Link is enabled', () => {
				const options = getStripeElementOptions(
					false,
					{ card: { isReusable: true }, link: { isReusable: false } },
					'shopper@example.com'
				);

				expect( options.defaultValues ).toEqual( {
					billingDetails: { email: 'shopper@example.com' },
				} );
			} );

			it( 'should not pass the email when Link is disabled', () => {
				const options = getStripeElementOptions(
					false,
					{ card: { isReusable: true } },
					'shopper@example.com'
				);

				expect( options.defaultValues ).toBeUndefined();
			} );

			it.each( [ undefined, '', 'shopper@' ] )(
				'should not pass an invalid email (%s)',
				( email ) => {
					const options = getStripeElementOptions(
						false,
						{
							card: { isReusable: true },
							link: { isReusable: false },
						},
						email
					);

					expect( options.defaultValues ).toBeUndefined();
				}
			);
		} );
	} );
} );
