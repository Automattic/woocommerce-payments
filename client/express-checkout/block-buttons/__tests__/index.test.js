/**
 * Internal dependencies
 */
import {
	expressCheckoutElementApplePay,
	expressCheckoutElementGooglePay,
	expressCheckoutElementAmazonPay,
} from '../index';
import { checkPaymentMethodIsAvailable } from '../../utils/checkPaymentMethodIsAvailable';

jest.mock( '../../utils/checkPaymentMethodIsAvailable', () => ( {
	checkPaymentMethodIsAvailable: jest.fn(),
} ) );

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn().mockReturnValue( [] ),
} ) );

jest.mock( 'wcpay/checkout/constants', () => ( {
	PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT:
		'woocommerce_payments_express_checkout',
} ) );

const mockCart = {
	cartTotals: {
		total_price: '1000',
		currency_code: 'USD',
	},
};

const mockApi = {
	loadStripeForExpressCheckout: jest.fn(),
};

describe( 'Express checkout blocks registration', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		delete global.wcpayExpressCheckoutParams;
	} );

	describe( 'canMakePayment', () => {
		describe( 'when wcpayExpressCheckoutParams is undefined', () => {
			it( 'should return false for Apple Pay', () => {
				const result = expressCheckoutElementApplePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay', () => {
				const result = expressCheckoutElementGooglePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Amazon Pay', () => {
				const result = expressCheckoutElementAmazonPay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'when payment_request is NOT in enabled_methods', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams = {
					enabled_methods: [],
				};
			} );

			it( 'should return false for Apple Pay', () => {
				const result = expressCheckoutElementApplePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay', () => {
				const result = expressCheckoutElementGooglePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'when amazon_pay is NOT in enabled_methods', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams = {
					enabled_methods: [ 'payment_request' ],
				};
			} );

			it( 'should return false for Amazon Pay', () => {
				const result = expressCheckoutElementAmazonPay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'when payment_request IS in enabled_methods', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams = {
					enabled_methods: [ 'payment_request' ],
				};
				checkPaymentMethodIsAvailable.mockResolvedValue( true );
			} );

			it( 'should call checkPaymentMethodIsAvailable for Apple Pay', () => {
				expressCheckoutElementApplePay( mockApi ).canMakePayment( {
					cart: mockCart,
				} );
				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'applePay',
					mockCart,
					mockApi
				);
			} );

			it( 'should call checkPaymentMethodIsAvailable for Google Pay', () => {
				expressCheckoutElementGooglePay( mockApi ).canMakePayment( {
					cart: mockCart,
				} );
				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'googlePay',
					mockCart,
					mockApi
				);
			} );
		} );

		describe( 'when amazon_pay IS in enabled_methods', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams = {
					enabled_methods: [ 'amazon_pay' ],
				};
				checkPaymentMethodIsAvailable.mockResolvedValue( true );
			} );

			it( 'should call checkPaymentMethodIsAvailable for Amazon Pay', () => {
				expressCheckoutElementAmazonPay( mockApi ).canMakePayment( {
					cart: mockCart,
				} );
				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'amazonPay',
					mockCart,
					mockApi
				);
			} );
		} );

		describe( 'when enabled_methods is missing from params', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams = {};
			} );

			it( 'should return false for Apple Pay (defaults to empty array)', () => {
				const result = expressCheckoutElementApplePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay (defaults to empty array)', () => {
				const result = expressCheckoutElementGooglePay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Amazon Pay (defaults to empty array)', () => {
				const result = expressCheckoutElementAmazonPay(
					mockApi
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );
		} );

		describe( 'when the cart Store API extension carries a method list', () => {
			beforeEach( () => {
				// Both methods are enabled at this location. `enabled_methods`
				// stands in for the stale, currency-gated localized list — the
				// cart response is what's authoritative for currency.
				global.wcpayExpressCheckoutParams = {
					enabled_methods: [ 'payment_request', 'amazon_pay' ],
					methods_enabled_at_location: [
						'payment_request',
						'amazon_pay',
					],
				};
				checkPaymentMethodIsAvailable.mockResolvedValue( true );
			} );

			const cartWithMethods = ( methods ) => ( {
				...mockCart,
				extensions: {
					wcpay: {
						express_checkout_methods: methods,
					},
				},
			} );

			it( 'rejects amazon_pay when the cart extension excludes it', () => {
				const result = expressCheckoutElementAmazonPay(
					mockApi
				).canMakePayment( {
					cart: cartWithMethods( [ 'payment_request' ] ),
				} );

				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'still allows apple/google pay when the cart extension keeps payment_request', () => {
				expressCheckoutElementApplePay( mockApi ).canMakePayment( {
					cart: cartWithMethods( [ 'payment_request' ] ),
				} );

				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'applePay',
					expect.any( Object ),
					mockApi
				);
			} );

			it( 'allows amazon_pay when the cart extension keeps it', () => {
				expressCheckoutElementAmazonPay( mockApi ).canMakePayment( {
					cart: cartWithMethods( [
						'payment_request',
						'amazon_pay',
					] ),
				} );

				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'amazonPay',
					expect.any( Object ),
					mockApi
				);
			} );

			it( 'does not resurrect a method the merchant disabled at this location', () => {
				// The cart extension is currency-gated but not location-gated, so
				// it may list a method disabled here. Intersecting with the
				// location-only allow-list keeps that method suppressed.
				global.wcpayExpressCheckoutParams.methods_enabled_at_location =
					[ 'payment_request' ];

				const result = expressCheckoutElementAmazonPay(
					mockApi
				).canMakePayment( {
					cart: cartWithMethods( [
						'payment_request',
						'amazon_pay',
					] ),
				} );

				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'recovers a method the stale localized currency gating dropped', () => {
				// US merchant, amazon_pay enabled at this location. Page loaded
				// under a EUR cart, so the localized list dropped amazon_pay on
				// currency grounds. A plugin then flipped the cart to USD and the
				// cart response now includes it — it must come back.
				global.wcpayExpressCheckoutParams.enabled_methods = [
					'payment_request',
				];

				expressCheckoutElementAmazonPay( mockApi ).canMakePayment( {
					cart: cartWithMethods( [
						'payment_request',
						'amazon_pay',
					] ),
				} );

				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'amazonPay',
					expect.any( Object ),
					mockApi
				);
			} );
		} );
	} );
} );
