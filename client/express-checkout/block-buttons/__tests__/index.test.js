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
	} );
} );
