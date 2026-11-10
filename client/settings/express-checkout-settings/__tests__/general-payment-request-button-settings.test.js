/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { loadStripe } from '@stripe/stripe-js/pure';

/**
 * Internal dependencies
 */
import GeneralPaymentRequestButtonSettings from '../general-payment-request-button-settings';

jest.mock( '@stripe/stripe-js/pure', () => ( {
	loadStripe: jest.fn().mockReturnValue( null ),
} ) );
jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn().mockReturnValue( null ),
} ) );
jest.mock( '../express-checkout-settings-notices', () => () => null );
jest.mock( '../payment-request-button-preview', () => () => null );
jest.mock( 'utils/express-checkout', () => ( {
	getExpressCheckoutConfig: jest.fn().mockReturnValue( {
		publishableKey: 'pk_test_123',
		accountId: 'acct_123',
		locale: 'en',
	} ),
} ) );
jest.mock( 'wcpay/data/settings', () => ( {
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'small' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	usePaymentRequestButtonBorderRadius: jest.fn().mockReturnValue( [ 4 ] ),
} ) );

const DAHLIA_URL = 'https://js.stripe.com/dahlia/stripe.js';

describe( 'GeneralPaymentRequestButtonSettings', () => {
	let originalWcpaySettings;

	beforeEach( () => {
		originalWcpaySettings = global.wcpaySettings;
		global.wcpaySettings = { stripeJsUrl: DAHLIA_URL };
	} );

	afterEach( () => {
		global.wcpaySettings = originalWcpaySettings;
		document.body.innerHTML = '';
	} );

	it( 'adds the configured Stripe.js tag before loading Stripe', () => {
		let sourcesWhenLoading = [];
		loadStripe.mockImplementation( () => {
			sourcesWhenLoading = Array.from(
				document.querySelectorAll(
					'script[src^="https://js.stripe.com/"]'
				)
			).map( ( script ) => script.src );
			return null;
		} );

		render( <GeneralPaymentRequestButtonSettings type="google_pay" /> );

		expect( sourcesWhenLoading ).toEqual( [
			'https://js.stripe.com/dahlia/stripe.js',
		] );
	} );
} );
