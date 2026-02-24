/**
 * Internal dependencies
 */
import { getExpressCheckoutButtonStyleSettings } from '..';

describe( 'getExpressCheckoutButtonStyleSettings', () => {
	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'returns correct defaults when no settings are provided', () => {
		window.wcpayExpressCheckoutParams = {};

		const result = getExpressCheckoutButtonStyleSettings();

		expect( result.paymentMethods.applePay ).toBe( 'never' );
		expect( result.paymentMethods.googlePay ).toBe( 'never' );
		expect( result.paymentMethods.amazonPay ).toBe( 'never' );
		expect( result.paymentMethods.link ).toBe( 'never' );
		expect( result.buttonHeight ).toBe( 48 );
	} );

	test( 'enables Apple Pay and Google Pay when payment_request is in enabled_methods', () => {
		window.wcpayExpressCheckoutParams = {
			enabled_methods: [ 'payment_request' ],
			button: {},
		};

		const result = getExpressCheckoutButtonStyleSettings();

		expect( result.paymentMethods.applePay ).toBe( 'always' );
		expect( result.paymentMethods.googlePay ).toBe( 'always' );
	} );

	test( 'enables Amazon Pay when amazon_pay is in enabled_methods', () => {
		window.wcpayExpressCheckoutParams = {
			enabled_methods: [ 'amazon_pay' ],
			button: {},
		};

		const result = getExpressCheckoutButtonStyleSettings();

		expect( result.paymentMethods.amazonPay ).toBe( 'auto' );
	} );

	describe( 'theme mapping', () => {
		test( 'maps dark theme to black', () => {
			window.wcpayExpressCheckoutParams = {
				button: { theme: 'dark' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonTheme.googlePay ).toBe( 'black' );
			expect( result.buttonTheme.applePay ).toBe( 'black' );
		} );

		test( 'maps light theme to white', () => {
			window.wcpayExpressCheckoutParams = {
				button: { theme: 'light' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonTheme.googlePay ).toBe( 'white' );
			expect( result.buttonTheme.applePay ).toBe( 'white' );
		} );

		test( 'maps light-outline to white for googlePay and white-outline for applePay', () => {
			window.wcpayExpressCheckoutParams = {
				button: { theme: 'light-outline' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonTheme.googlePay ).toBe( 'white' );
			expect( result.buttonTheme.applePay ).toBe( 'white-outline' );
		} );
	} );

	describe( 'button type mapping', () => {
		test( 'maps default type to plain for both', () => {
			window.wcpayExpressCheckoutParams = {
				button: { type: 'default' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonType.googlePay ).toBe( 'plain' );
			expect( result.buttonType.applePay ).toBe( 'plain' );
		} );

		test( 'passes through non-default type', () => {
			window.wcpayExpressCheckoutParams = {
				button: { type: 'buy' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonType.googlePay ).toBe( 'buy' );
			expect( result.buttonType.applePay ).toBe( 'buy' );
		} );
	} );

	describe( 'button height clamping', () => {
		test( 'clamps height below 40 to 40', () => {
			window.wcpayExpressCheckoutParams = {
				button: { height: '30' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonHeight ).toBe( 40 );
		} );

		test( 'clamps height above 55 to 55', () => {
			window.wcpayExpressCheckoutParams = {
				button: { height: '70' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonHeight ).toBe( 55 );
		} );

		test( 'uses height as-is when within range', () => {
			window.wcpayExpressCheckoutParams = {
				button: { height: '45' },
				enabled_methods: [],
			};

			const result = getExpressCheckoutButtonStyleSettings();

			expect( result.buttonHeight ).toBe( 45 );
		} );
	} );
} );
