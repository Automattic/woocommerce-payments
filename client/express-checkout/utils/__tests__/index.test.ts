/**
 * Internal dependencies
 */
import {
	WCPayExpressCheckoutParams,
	getErrorMessageFromNotice,
	getExpressCheckoutData,
	getExpressCheckoutButtonStyleSettings,
} from '..';

describe( 'Express checkout utils', () => {
	beforeEach( () => {
		// Reset window params before each test.
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'getExpressCheckoutData returns null for missing option', () => {
		expect(
			getExpressCheckoutData(
				// Force wrong usage, just in case this is called from JS with incorrect params.
				'does-not-exist' as keyof WCPayExpressCheckoutParams
			)
		).toBeNull();
	} );

	test( 'getExpressCheckoutData returns correct value for present option', () => {
		// We don't care that the implementation is partial for the purposes of the test, so
		// the type assertion is fine.
		window.wcpayExpressCheckoutParams = {
			ajax_url: 'test',
		} as WCPayExpressCheckoutParams;

		expect( getExpressCheckoutData( 'ajax_url' ) ).toBe( 'test' );
	} );

	test( 'getErrorMessageFromNotice strips formatting', () => {
		const notice = '<p><b>Error:</b> Payment failed.</p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.'
		);
	} );

	test( 'getErrorMessageFromNotice strips scripts', () => {
		const notice =
			'<p><b>Error:</b> Payment failed.<script>alert("hello")</script></p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.alert("hello")'
		);
	} );
} );

describe( 'getExpressCheckoutButtonStyleSettings', () => {
	beforeEach( () => {
		// Reset window params before each test.
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'returns google and apple pay enabled when google_apple_pay is in enabled methods', () => {
		window.wcpayExpressCheckoutParams = {
			button: {
				type: 'buy',
				theme: 'dark',
				height: '48',
			},
			enabled_methods: [ 'google_apple_pay' ],
		} as WCPayExpressCheckoutParams;

		const settings = getExpressCheckoutButtonStyleSettings();

		expect( settings.paymentMethods.applePay ).toBe( 'always' );
		expect( settings.paymentMethods.googlePay ).toBe( 'always' );
		expect( settings.paymentMethods.amazonPay ).toBe( 'never' );
	} );

	test( 'returns amazon pay enabled when amazon_pay is in enabled methods', () => {
		window.wcpayExpressCheckoutParams = {
			button: {
				type: 'buy',
				theme: 'dark',
				height: '48',
			},
			enabled_methods: [ 'amazon_pay' ],
		} as WCPayExpressCheckoutParams;

		const settings = getExpressCheckoutButtonStyleSettings();

		expect( settings.paymentMethods.applePay ).toBe( 'never' );
		expect( settings.paymentMethods.googlePay ).toBe( 'never' );
		// Amazon Pay uses 'auto' to let Stripe determine availability based on browser/region.
		expect( settings.paymentMethods.amazonPay ).toBe( 'auto' );
	} );

	test( 'returns all methods enabled when both are in enabled methods', () => {
		window.wcpayExpressCheckoutParams = {
			button: {
				type: 'buy',
				theme: 'dark',
				height: '48',
			},
			enabled_methods: [ 'google_apple_pay', 'amazon_pay' ],
		} as WCPayExpressCheckoutParams;

		const settings = getExpressCheckoutButtonStyleSettings();

		expect( settings.paymentMethods.applePay ).toBe( 'always' );
		expect( settings.paymentMethods.googlePay ).toBe( 'always' );
		// Amazon Pay uses 'auto' to let Stripe determine availability based on browser/region.
		expect( settings.paymentMethods.amazonPay ).toBe( 'auto' );
	} );

	test( 'returns all methods disabled when enabled methods array is empty', () => {
		window.wcpayExpressCheckoutParams = {
			button: {
				type: 'buy',
				theme: 'dark',
				height: '48',
			},
			enabled_methods: [] as WCPayExpressCheckoutParams[ 'enabled_methods' ],
		} as WCPayExpressCheckoutParams;

		const settings = getExpressCheckoutButtonStyleSettings();

		expect( settings.paymentMethods.applePay ).toBe( 'never' );
		expect( settings.paymentMethods.googlePay ).toBe( 'never' );
		expect( settings.paymentMethods.amazonPay ).toBe( 'never' );
	} );

	test( 'always disables link, paypal, and klarna regardless of enabled methods', () => {
		window.wcpayExpressCheckoutParams = {
			button: {
				type: 'buy',
				theme: 'dark',
				height: '48',
			},
			enabled_methods: [ 'google_apple_pay', 'amazon_pay' ],
		} as WCPayExpressCheckoutParams;

		const settings = getExpressCheckoutButtonStyleSettings();

		expect( settings.paymentMethods.link ).toBe( 'never' );
		expect( settings.paymentMethods.paypal ).toBe( 'never' );
		expect( settings.paymentMethods.klarna ).toBe( 'never' );
	} );
} );
