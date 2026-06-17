/**
 * Internal dependencies
 */
import {
	makeExpressCheckoutElement,
	makeDynamicPlaceOrderButton,
} from '../payment-methods';
import DynamicButtonContainer from '../components/dynamic-button-container';
import { checkPaymentMethodIsAvailable } from '../../utils/checkPaymentMethodIsAvailable';

jest.mock( '../../utils/checkPaymentMethodIsAvailable', () => ( {
	checkPaymentMethodIsAvailable: jest.fn(),
} ) );

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn().mockReturnValue( [] ),
	getUPEConfig: jest.fn( ( key ) => {
		if ( key === 'expressCheckoutMethodsConfig' ) {
			return {
				apple_pay: {
					isExpressCheckout: true,
					gatewayId: 'woocommerce_payments_apple_pay',
					title: 'Apple Pay',
					description: 'Apple Pay description',
					stripePaymentMethodType: 'card',
				},
				google_pay: {
					isExpressCheckout: true,
					gatewayId: 'woocommerce_payments_google_pay',
					title: 'Google Pay',
					description: 'Google Pay description',
					stripePaymentMethodType: 'card',
				},
				amazon_pay: {
					isExpressCheckout: true,
					gatewayId: 'woocommerce_payments_amazon_pay',
					title: 'Amazon Pay',
					description: 'Amazon Pay description',
					stripePaymentMethodType: 'amazon_pay',
				},
			};
		}
		return null;
	} ),
} ) );

jest.mock( '../constants', () => ( {
	PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT:
		'woocommerce_payments_express_checkout',
} ) );

jest.mock( '../../constants', () => ( {
	snakeToCamel: ( snake ) =>
		snake.replace( /_([a-z])/g, ( _, l ) => l.toUpperCase() ),
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

describe( 'makeDynamicPlaceOrderButton', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'registers under the gateway id from the server config', () => {
		const method = makeDynamicPlaceOrderButton( mockApi, 'googlePay' );

		expect( method.name ).toBe( 'woocommerce_payments_google_pay' );
		expect( method.paymentMethodId ).toBe(
			'woocommerce_payments_google_pay'
		);
		expect( method.ariaLabel ).toBe( 'Google Pay' );
		expect( method.savedTokenComponent ).toBeNull();
	} );

	it( 'delegates canMakePayment to the availability check', async () => {
		checkPaymentMethodIsAvailable.mockResolvedValue( true );

		const result = await makeDynamicPlaceOrderButton(
			mockApi,
			'amazonPay'
		).canMakePayment( { cart: mockCart } );

		expect( result ).toBe( true );
		expect( checkPaymentMethodIsAvailable ).toHaveBeenCalledWith(
			'amazonPay',
			mockCart,
			mockApi
		);
	} );

	it( 'renders the place order button with the method configuration', () => {
		const method = makeDynamicPlaceOrderButton( mockApi, 'amazonPay' );

		const buttonElement = method.placeOrderButton( {
			onSubmit: 'on-submit-prop',
		} );

		expect( buttonElement.type ).toBe( DynamicButtonContainer );
		expect( buttonElement.props ).toEqual(
			expect.objectContaining( {
				expressPaymentMethod: 'amazonPay',
				expressPaymentType: 'amazon_pay',
				stripePaymentMethodType: 'amazon_pay',
				gatewayId: 'woocommerce_payments_amazon_pay',
				api: mockApi,
				onSubmit: 'on-submit-prop',
			} )
		);
	} );

	it( 'labels the payment method with the server-provided title', () => {
		const method = makeDynamicPlaceOrderButton( mockApi, 'applePay' );

		expect( method.label.props ).toEqual(
			expect.objectContaining( {
				title: 'Apple Pay',
				paymentMethodId: 'apple_pay',
			} )
		);
	} );
} );
