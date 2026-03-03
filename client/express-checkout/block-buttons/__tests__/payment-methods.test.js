/**
 * Internal dependencies
 */
import { makeExpressCheckoutElement } from '../payment-methods';
import { checkPaymentMethodIsAvailable } from '../../utils/checkPaymentMethodIsAvailable';

jest.mock( '../../utils/checkPaymentMethodIsAvailable', () => ( {
	checkPaymentMethodIsAvailable: jest.fn(),
} ) );

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn().mockReturnValue( [] ),
	getUPEConfig: jest.fn().mockReturnValue( null ),
} ) );

jest.mock( '../constants', () => ( {
	PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT:
		'woocommerce_payments_express_checkout',
} ) );

jest.mock( '../../constants', () => ( {
	EXPRESS_PAYMENT_METHODS: {
		applePay: {
			key: 'apple_pay',
			expressPaymentType: 'apple_pay',
			paymentMethodTypes: [ 'card' ],
			gatewayId: 'woocommerce_payments_apple_pay',
			title: 'WooPayments - Apple Pay',
			description: 'Apple Pay description',
			ariaLabel: 'Apple Pay',
			fallbackTitle: 'Apple Pay',
		},
		googlePay: {
			key: 'google_pay',
			expressPaymentType: 'google_pay',
			paymentMethodTypes: [ 'card' ],
			gatewayId: 'woocommerce_payments_google_pay',
			title: 'WooPayments - Google Pay',
			description: 'Google Pay description',
			ariaLabel: 'Google Pay',
			fallbackTitle: 'Google Pay',
		},
		amazonPay: {
			key: 'amazon_pay',
			expressPaymentType: 'amazon_pay',
			paymentMethodTypes: [ 'amazon_pay' ],
			gatewayId: 'woocommerce_payments_amazon_pay',
			title: 'WooPayments - Amazon Pay',
			description: 'Amazon Pay description',
			ariaLabel: 'Amazon Pay',
			fallbackTitle: 'Amazon Pay',
		},
	},
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

describe( 'makeExpressCheckoutElement', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		delete global.wcpayExpressCheckoutParams;
	} );

	describe( 'canMakePayment', () => {
		describe( 'when wcpayExpressCheckoutParams is undefined', () => {
			it( 'should return false for Apple Pay', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'applePay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'googlePay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Amazon Pay', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'amazonPay'
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
				const result = makeExpressCheckoutElement(
					mockApi,
					'applePay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'googlePay'
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
				const result = makeExpressCheckoutElement(
					mockApi,
					'amazonPay'
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
				makeExpressCheckoutElement(
					mockApi,
					'applePay'
				).canMakePayment( { cart: mockCart } );
				expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
					'applePay',
					mockCart,
					mockApi
				);
			} );

			it( 'should call checkPaymentMethodIsAvailable for Google Pay', () => {
				makeExpressCheckoutElement(
					mockApi,
					'googlePay'
				).canMakePayment( { cart: mockCart } );
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
				makeExpressCheckoutElement(
					mockApi,
					'amazonPay'
				).canMakePayment( { cart: mockCart } );
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
				const result = makeExpressCheckoutElement(
					mockApi,
					'applePay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Google Pay (defaults to empty array)', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'googlePay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );

			it( 'should return false for Amazon Pay (defaults to empty array)', () => {
				const result = makeExpressCheckoutElement(
					mockApi,
					'amazonPay'
				).canMakePayment( { cart: mockCart } );
				expect( result ).toBe( false );
				expect( checkPaymentMethodIsAvailable ).not.toHaveBeenCalled();
			} );
		} );
	} );
} );
