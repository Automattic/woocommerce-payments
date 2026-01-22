/**
 * Internal dependencies
 */
import {
	getErrorMessageFromNotice,
	getExpressCheckoutData,
	getSetupFutureUsage,
} from '..';

describe( 'Express checkout utils', () => {
	test( 'getExpressCheckoutData returns null for missing option', () => {
		expect( getExpressCheckoutData( 'does-not-exist' ) ).toBeNull();
	} );

	test( 'getExpressCheckoutData returns correct value for present option', () => {
		window.wcpayExpressCheckoutParams = {
			ajax_url: 'test',
		};

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

	describe( 'getSetupFutureUsage', () => {
		beforeEach( () => {
			window.wcpayExpressCheckoutParams = {};
		} );

		test( 'returns setupFutureUsage for subscription product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'subscription' },
			};

			expect( getSetupFutureUsage() ).toEqual( {
				setupFutureUsage: 'off_session',
			} );
		} );

		test( 'returns setupFutureUsage for variable-subscription product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'variable-subscription' },
			};

			expect( getSetupFutureUsage() ).toEqual( {
				setupFutureUsage: 'off_session',
			} );
		} );

		test( 'returns setupFutureUsage for subscription_variation product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'subscription_variation' },
			};

			expect( getSetupFutureUsage() ).toEqual( {
				setupFutureUsage: 'off_session',
			} );
		} );

		test( 'returns setupFutureUsage when has_subscription is true', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'simple' },
				has_subscription: true,
			};

			expect( getSetupFutureUsage() ).toEqual( {
				setupFutureUsage: 'off_session',
			} );
		} );

		test( 'returns empty object for non-subscription products', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'simple' },
				has_subscription: false,
			};

			expect( getSetupFutureUsage() ).toEqual( {} );
		} );

		test( 'returns empty object when product and checkout are not set', () => {
			window.wcpayExpressCheckoutParams = {};

			expect( getSetupFutureUsage() ).toEqual( {} );
		} );
	} );
} );
