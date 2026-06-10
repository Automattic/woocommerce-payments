/**
 * Internal dependencies
 */
import { initExpressPaymentMethods } from '..';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { createPaymentCredential } from 'wcpay/express-checkout/utils';
import { checkAllExpressMethodsAvailability } from 'wcpay/express-checkout/utils/checkPaymentMethodIsAvailable';
import {
	appendConfirmationTokenToForm,
	appendExpressPaymentTypeToForm,
} from 'wcpay/checkout/classic/upe-utils';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getUPEConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/fingerprint', () => ( {
	getFingerprint: jest.fn().mockResolvedValue( { visitorId: 'fp_123' } ),
	appendFingerprintInputToForm: jest.fn(),
} ) );

jest.mock( 'wcpay/express-checkout/utils/payment-method-overrides', () => ( {
	getPaymentMethodsOverride: jest.fn( () => ( {
		paymentMethods: { applePay: 'always', googlePay: 'never' },
	} ) ),
} ) );

jest.mock(
	'wcpay/express-checkout/utils/checkPaymentMethodIsAvailable',
	() => ( {
		checkAllExpressMethodsAvailability: jest.fn().mockResolvedValue( {
			applePay: true,
			googlePay: false,
			amazonPay: true,
		} ),
	} )
);

let mockStripeMinorUnit = 2;
jest.mock( 'wcpay/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn( ( key ) => {
		if ( key === 'flags' ) {
			return { isEceUsingConfirmationTokens: true };
		}
		if ( key === 'checkout' ) {
			return {
				stripe_minor_unit: mockStripeMinorUnit,
				display_prices_with_tax: false,
			};
		}
		if ( key === 'store_name' ) {
			return 'Test Store';
		}
		return null;
	} ),
	shouldUseConfirmationTokens: jest.fn( () => true ),
	createPaymentCredential: jest.fn().mockResolvedValue( {
		id: 'ct_123',
		type: 'confirmation_token',
	} ),
	// Passthrough, so assertions can inspect the options given to `stripe.elements()`.
	buildStripeElementsOptions: jest.fn( ( options ) => options ),
} ) );

let mockGetCart;
jest.mock( 'wcpay/express-checkout/cart-api', () =>
	jest.fn().mockImplementation( () => ( {
		getCart: ( ...args ) => mockGetCart( ...args ),
	} ) )
);

jest.mock( 'wcpay/express-checkout/constants', () => ( {
	snakeToCamel: ( snake ) =>
		snake.replace( /_([a-z])/g, ( _, l ) => l.toUpperCase() ),
} ) );

jest.mock( 'wcpay/checkout/classic/upe-utils', () => ( {
	appendPaymentMethodIdToForm: jest.fn(),
	appendConfirmationTokenToForm: jest.fn(),
	appendExpressPaymentTypeToForm: jest.fn(),
	appendFraudPreventionTokenInputToForm: jest.fn(),
} ) );

const getSimpleCart = () => ( {
	totals: {
		total_price: '2399',
		total_refund: '0',
		total_shipping: '0',
		total_tax: '0',
		currency_minor_unit: 2,
	},
	items: [
		{
			name: 'A product',
			quantity: 1,
			prices: { price: '2399', currency_minor_unit: 2 },
			totals: {
				line_subtotal: '2399',
				line_subtotal_tax: '0',
				currency_minor_unit: 2,
			},
		},
	],
	extensions: {},
} );

const getZeroTotalTrialCart = () => ( {
	totals: {
		total_price: '0',
		total_refund: '0',
		total_shipping: '0',
		total_tax: '0',
		currency_minor_unit: 2,
	},
	items: [
		{
			name: 'Trial subscription',
			quantity: 1,
			prices: { price: '0', currency_minor_unit: 2 },
			totals: {
				line_subtotal: '0',
				line_subtotal_tax: '0',
				currency_minor_unit: 2,
			},
			extensions: {
				subscriptions: {
					trial_length: 15,
					trial_period: 'day',
					billing_period: 'month',
					billing_interval: 1,
				},
			},
		},
	],
	extensions: {
		subscriptions: [
			{
				billing_period: 'month',
				billing_interval: 1,
				totals: { total_price: '420', currency_minor_unit: 2 },
			},
		],
	},
} );

describe( 'initExpressPaymentMethods', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockGetCart = jest.fn().mockResolvedValue( getSimpleCart() );

		// Reset the global wc object
		delete global.wc;
	} );

	test( 'does nothing when wc.customPlaceOrderButton API is not available', () => {
		global.wc = undefined;

		getUPEConfig.mockReturnValue( true );

		// Should not throw
		initExpressPaymentMethods( {} );
	} );

	test( 'does nothing when wc object exists but customPlaceOrderButton is missing', () => {
		global.wc = {};

		getUPEConfig.mockReturnValue( true );

		initExpressPaymentMethods( {} );
	} );

	test( 'does nothing when feature flag is disabled', () => {
		const mockRegister = jest.fn();
		global.wc = {
			customPlaceOrderButton: {
				register: mockRegister,
			},
		};

		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'isExpressCheckoutInPaymentMethodsEnabled' ) {
				return false;
			}
			return null;
		} );

		initExpressPaymentMethods( {} );

		expect( mockRegister ).not.toHaveBeenCalled();
	} );

	test( 'calls registerExpressPaymentMethods when API is available and feature is enabled', () => {
		const mockRegister = jest.fn();
		global.wc = {
			customPlaceOrderButton: {
				register: mockRegister,
			},
		};

		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'isExpressCheckoutInPaymentMethodsEnabled' ) {
				return true;
			}
			if ( key === 'currency' ) {
				return 'usd';
			}
			if ( key === 'cartTotal' ) {
				return 1000;
			}
			if ( key === 'paymentMethodsConfig' ) {
				return {
					apple_pay: {
						isExpressCheckout: true,
						gatewayId: 'woocommerce_payments_apple_pay',
					},
				};
			}
			return null;
		} );

		// Mock the DOM for checkExpressPaymentMethodsAvailability
		const mockApi = {
			getStripe: jest.fn().mockResolvedValue( {
				elements: jest.fn().mockReturnValue( {
					create: jest.fn().mockReturnValue( {
						on: jest.fn(),
						mount: jest.fn(),
						unmount: jest.fn(),
					} ),
				} ),
			} ),
		};

		initExpressPaymentMethods( mockApi );

		// The function is async internally but we can verify it started
		// by checking that the feature flag was consulted.
		expect( getUPEConfig ).toHaveBeenCalledWith(
			'isExpressCheckoutInPaymentMethodsEnabled'
		);
	} );
} );

describe( 'custom place order button flow', () => {
	let mockRegister;
	let registeredHandlers;
	let eceHandlers;
	let mockEceButton;
	let mockElements;
	let mockStripe;
	let mockApi;
	let containerEl;
	let methodRowEl;

	const flushAsync = async () => {
		// Several awaits are chained internally (cart fetch, availability,
		// fingerprint, Stripe loading) - a macrotask flushes them all.
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	};

	const initWithConfig = async ( paymentMethodId, gatewayId ) => {
		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'isExpressCheckoutInPaymentMethodsEnabled' ) {
				return true;
			}
			if ( key === 'currency' ) {
				return 'USD';
			}
			if ( key === 'cartTotal' ) {
				return 2399;
			}
			if ( key === 'paymentMethodsConfig' ) {
				return {
					[ paymentMethodId ]: {
						isExpressCheckout: true,
						gatewayId,
						stripePaymentMethodType:
							paymentMethodId === 'amazon_pay'
								? 'amazon_pay'
								: 'card',
					},
				};
			}
			return null;
		} );

		initExpressPaymentMethods( mockApi );
		await flushAsync();
	};

	beforeEach( () => {
		jest.clearAllMocks();
		mockStripeMinorUnit = 2;
		mockGetCart = jest.fn().mockResolvedValue( getSimpleCart() );
		checkAllExpressMethodsAvailability.mockResolvedValue( {
			applePay: true,
			googlePay: true,
			amazonPay: true,
		} );

		registeredHandlers = {};
		mockRegister = jest.fn( ( gatewayId, handler ) => {
			registeredHandlers[ gatewayId ] = handler;
		} );
		global.wc = { customPlaceOrderButton: { register: mockRegister } };

		eceHandlers = {};
		mockEceButton = {
			on: jest.fn( ( event, callback ) => {
				eceHandlers[ event ] = callback;
			} ),
			mount: jest.fn(),
			unmount: jest.fn(),
		};
		mockElements = {
			create: jest.fn( () => mockEceButton ),
			submit: jest.fn().mockResolvedValue( {} ),
		};
		mockStripe = { elements: jest.fn( () => mockElements ) };
		mockApi = { getStripe: jest.fn().mockResolvedValue( mockStripe ) };

		containerEl = document.createElement( 'div' );
		methodRowEl = document.createElement( 'li' );
		document.body.appendChild( methodRowEl );

		global.jQuery = jest.fn( () => ( {
			length: 1,
			first: () => ( { length: 0 } ),
		} ) );
	} );

	afterEach( () => {
		methodRowEl.remove();
		delete global.jQuery;
	} );

	it( 'does not register any method when the cart total is zero without a subscription', async () => {
		mockGetCart = jest.fn().mockResolvedValue( {
			...getSimpleCart(),
			totals: { ...getSimpleCart().totals, total_price: '0' },
		} );
		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'isExpressCheckoutInPaymentMethodsEnabled' ) {
				return true;
			}
			if ( key === 'currency' ) {
				return 'USD';
			}
			if ( key === 'cartTotal' ) {
				return 0;
			}
			if ( key === 'paymentMethodsConfig' ) {
				return {
					google_pay: {
						isExpressCheckout: true,
						gatewayId: 'woocommerce_payments_google_pay',
						stripePaymentMethodType: 'card',
					},
				};
			}
			return null;
		} );

		initExpressPaymentMethods( mockApi );
		await flushAsync();

		expect( mockRegister ).not.toHaveBeenCalled();
	} );

	it( 'charges the recurring total with off_session setupFutureUsage for a zero-total trial subscription cart', async () => {
		mockGetCart = jest.fn().mockResolvedValue( getZeroTotalTrialCart() );

		await initWithConfig( 'amazon_pay', 'woocommerce_payments_amazon_pay' );

		expect( mockRegister ).toHaveBeenCalledWith(
			'woocommerce_payments_amazon_pay',
			expect.any( Object )
		);

		methodRowEl.className =
			'wc_payment_method payment_method_woocommerce_payments_amazon_pay';
		await registeredHandlers.woocommerce_payments_amazon_pay.render(
			containerEl,
			{ validate: jest.fn(), submit: jest.fn() }
		);

		expect( mockStripe.elements ).toHaveBeenCalledWith(
			expect.objectContaining( {
				amount: 420,
				currency: 'usd',
				paymentMethodTypes: [ 'amazon_pay' ],
				setupFutureUsage: 'off_session',
			} )
		);
		expect( mockEceButton.mount ).toHaveBeenCalledWith( containerEl );
	} );

	it( 'resolves the payment sheet click with the cart line items', async () => {
		await initWithConfig( 'google_pay', 'wcpay_gpay_click_test' );

		const wcApi = {
			validate: jest.fn().mockResolvedValue( { hasError: false } ),
			submit: jest.fn(),
		};
		await registeredHandlers.wcpay_gpay_click_test.render(
			containerEl,
			wcApi
		);

		const clickEvent = { resolve: jest.fn() };
		await eceHandlers.click( clickEvent );

		expect( clickEvent.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				business: { name: 'Test Store' },
				emailRequired: true,
				shippingAddressRequired: false,
				lineItems: [ { name: 'A product', amount: 2399 } ],
			} )
		);
	} );

	it( 'does not resolve the payment sheet click when form validation fails', async () => {
		await initWithConfig( 'google_pay', 'wcpay_gpay_validation_test' );

		const wcApi = {
			validate: jest.fn().mockResolvedValue( { hasError: true } ),
			submit: jest.fn(),
		};
		await registeredHandlers.wcpay_gpay_validation_test.render(
			containerEl,
			wcApi
		);

		const clickEvent = { resolve: jest.fn() };
		await eceHandlers.click( clickEvent );

		expect( clickEvent.resolve ).not.toHaveBeenCalled();
	} );

	it( 'appends the confirmation token to the form and submits the checkout on confirm', async () => {
		await initWithConfig( 'google_pay', 'wcpay_gpay_confirm_test' );

		const wcApi = {
			validate: jest.fn().mockResolvedValue( { hasError: false } ),
			submit: jest.fn(),
		};
		await registeredHandlers.wcpay_gpay_confirm_test.render(
			containerEl,
			wcApi
		);

		await eceHandlers.confirm();

		expect( mockElements.submit ).toHaveBeenCalled();
		expect( createPaymentCredential ).toHaveBeenCalled();
		expect( appendConfirmationTokenToForm ).toHaveBeenCalledWith(
			expect.anything(),
			'ct_123'
		);
		expect( appendExpressPaymentTypeToForm ).toHaveBeenCalledWith(
			expect.anything(),
			'google_pay'
		);
		expect( wcApi.submit ).toHaveBeenCalled();
	} );

	it( 'parses the displayed total with the currency minor unit when cart data is unavailable', async () => {
		mockGetCart = jest
			.fn()
			.mockRejectedValue( new Error( 'cart unavailable' ) );
		// A zero-decimal currency, e.g. JPY.
		mockStripeMinorUnit = 0;

		const orderReviewTable = document.createElement( 'div' );
		orderReviewTable.className = 'woocommerce-checkout-review-order-table';
		orderReviewTable.innerHTML =
			'<div class="order-total"><span class="woocommerce-Price-amount">¥1,234</span></div>';
		document.body.appendChild( orderReviewTable );

		getUPEConfig.mockImplementation( ( key ) => {
			if ( key === 'isExpressCheckoutInPaymentMethodsEnabled' ) {
				return true;
			}
			if ( key === 'currency' ) {
				return 'JPY';
			}
			if ( key === 'cartTotal' ) {
				return 0;
			}
			if ( key === 'paymentMethodsConfig' ) {
				return {
					google_pay: {
						isExpressCheckout: true,
						gatewayId: 'wcpay_gpay_dom_fallback_test',
						stripePaymentMethodType: 'card',
					},
				};
			}
			return null;
		} );
		initExpressPaymentMethods( mockApi );
		await flushAsync();

		await registeredHandlers.wcpay_gpay_dom_fallback_test.render(
			containerEl,
			{ validate: jest.fn(), submit: jest.fn() }
		);

		expect( mockStripe.elements ).toHaveBeenCalledWith(
			expect.objectContaining( { amount: 1234, currency: 'jpy' } )
		);

		orderReviewTable.remove();
	} );

	it( 'hides the payment method row when Stripe reports the method as unavailable', async () => {
		await initWithConfig( 'google_pay', 'wcpay_gpay_hide_test' );

		methodRowEl.className =
			'wc_payment_method payment_method_wcpay_gpay_hide_test';
		await registeredHandlers.wcpay_gpay_hide_test.render( containerEl, {
			validate: jest.fn(),
			submit: jest.fn(),
		} );

		eceHandlers.ready( { availablePaymentMethods: { googlePay: false } } );

		expect( methodRowEl.style.display ).toBe( 'none' );
		expect( containerEl.style.display ).toBe( 'none' );
	} );
} );
