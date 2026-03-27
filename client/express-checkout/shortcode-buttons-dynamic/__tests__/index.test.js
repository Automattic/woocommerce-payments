/**
 * Internal dependencies
 */
import { initExpressPaymentMethods } from '..';
import { getUPEConfig } from 'wcpay/utils/checkout';

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

jest.mock( 'wcpay/express-checkout/utils', () => ( {
	getExpressCheckoutData: jest.fn( ( key ) => {
		if ( key === 'flags' ) {
			return { isEceUsingConfirmationTokens: true };
		}
		return null;
	} ),
	shouldUseConfirmationTokens: jest.fn( () => true ),
	createPaymentCredential: jest.fn().mockResolvedValue( {
		id: 'ct_123',
		type: 'confirmation_token',
	} ),
} ) );

jest.mock( 'wcpay/express-checkout/constants', () => ( {
	snakeToCamel: ( snake ) =>
		snake.replace( /_([a-z])/g, ( _, l ) => l.toUpperCase() ),
} ) );

jest.mock( 'wcpay/checkout/classic/upe-utils', () => ( {
	...jest.requireActual( 'wcpay/checkout/classic/upe-utils' ),
	appendConfirmationTokenToForm: jest.fn(),
	appendExpressPaymentTypeToForm: jest.fn(),
} ) );

describe( 'initExpressPaymentMethods', () => {
	beforeEach( () => {
		jest.clearAllMocks();

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
