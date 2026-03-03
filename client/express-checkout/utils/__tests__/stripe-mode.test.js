/**
 * Internal dependencies
 */
import { getStripeElementsMode } from '../stripe-mode';

describe( 'getStripeElementsMode', () => {
	beforeEach( () => {
		window.wcpayExpressCheckoutParams = {};
	} );

	afterEach( () => {
		delete window.wcpayExpressCheckoutParams;
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
