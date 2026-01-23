/**
 * Internal dependencies
 */
import {
	getErrorMessageFromNotice,
	getExpressCheckoutData,
	getStripeElementsMode,
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

	describe( 'getStripeElementsMode', () => {
		beforeEach( () => {
			window.wcpayExpressCheckoutParams = {};
		} );

		test( 'returns subscription mode for subscription product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'subscription' },
			};

			expect( getStripeElementsMode() ).toBe( 'subscription' );
		} );

		test( 'returns subscription mode for variable-subscription product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'variable-subscription' },
			};

			expect( getStripeElementsMode() ).toBe( 'subscription' );
		} );

		test( 'returns subscription mode for subscription_variation product type', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'subscription_variation' },
			};

			expect( getStripeElementsMode() ).toBe( 'subscription' );
		} );

		test( 'returns subscription mode when has_subscription is true', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'simple' },
				has_subscription: true,
			};

			expect( getStripeElementsMode() ).toBe( 'subscription' );
		} );

		test( 'returns payment mode for non-subscription products', () => {
			window.wcpayExpressCheckoutParams = {
				product: { product_type: 'simple' },
				has_subscription: false,
			};

			expect( getStripeElementsMode() ).toBe( 'payment' );
		} );

		test( 'returns payment mode when product and checkout are not set', () => {
			window.wcpayExpressCheckoutParams = {};

			expect( getStripeElementsMode() ).toBe( 'payment' );
		} );
	} );
} );
