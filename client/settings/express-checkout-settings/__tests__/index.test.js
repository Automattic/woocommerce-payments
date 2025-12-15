/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutSettings from '..';
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( '../../../data', () => ( {
	useTestMode: jest.fn().mockReturnValue( [] ),
	useGetSettings: jest.fn().mockReturnValue( {} ),
	useSettings: jest.fn().mockReturnValue( {} ),
	usePaymentRequestEnabledSettings: jest
		.fn()
		.mockReturnValue( [ true, jest.fn() ] ),
	usePaymentRequestLocations: jest
		.fn()
		.mockReturnValue( [ [ true, true, true ], jest.fn() ] ),
	useAppleGooglePayInPaymentMethodsOptionsEnabledSettings: jest
		.fn()
		.mockReturnValue( [ false, jest.fn() ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ true, jest.fn() ] ),
	useWooPayCustomMessage: jest.fn().mockReturnValue( [ 'test', jest.fn() ] ),
	useWooPayStoreLogo: jest.fn().mockReturnValue( [ 'test', jest.fn() ] ),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'small' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	usePaymentRequestButtonBorderRadius: jest.fn().mockReturnValue( [ 4 ] ),
	useWooPayGlobalThemeSupportEnabledSettings: jest
		.fn()
		.mockReturnValue( [ false, jest.fn() ] ),
	useWooPayLocations: jest
		.fn()
		.mockReturnValue( [ [ true, true, true ], jest.fn() ] ),
	useWooPayShowIncompatibilityNotice: jest.fn().mockReturnValue( false ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( { createErrorNotice: jest.fn() } ) ),
} ) );

jest.mock( '../payment-request-button-preview' );
PaymentRequestButtonPreview.mockImplementation( () => '<></>' );

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn().mockReturnValue( null ),
} ) );
jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn().mockReturnValue( null ),
} ) );

jest.mock( 'utils/express-checkout', () => ( {
	getExpressCheckoutConfig: jest.fn().mockReturnValue( {
		publishableKey: '123',
		accountId: '0001',
		locale: 'en',
	} ),
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Link: jest
		.fn()
		.mockImplementation( ( { href, children } ) => (
			<a href={ href }>{ children }</a>
		) ),
} ) );

// Mock CSS imports to prevent CSS parsing errors due to RangeControl.
jest.mock( '../index.scss', () => ( {} ) );

// Suppress CSS parsing errors from components
// eslint-disable-next-line no-console
const originalError = console.error;
beforeAll( () => {
	// eslint-disable-next-line no-console
	console.error = ( ...args ) => {
		// Suppress CSS parsing errors
		if (
			args[ 0 ] &&
			args[ 0 ].message &&
			args[ 0 ].message.includes( 'Could not parse CSS stylesheet' )
		) {
			return;
		}
		originalError.call( console, ...args );
	};
} );

afterAll( () => {
	// eslint-disable-next-line no-console
	console.error = originalError;
} );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider value={ global.wcpaySettings }>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'ExpressCheckoutSettings', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {},
			restUrl: 'http://example.com/wp-json/',
			featureFlags: {
				woopayExpressCheckout: true,
				isDynamicCheckoutPlaceOrderButtonEnabled: true,
			},
		};
	} );

	test( 'renders error message for invalid method IDs', () => {
		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="foo" />
		);

		const errorMessage = screen.queryByText(
			'Invalid express checkout method ID specified.'
		);
		expect( errorMessage ).toBeInTheDocument();
	} );

	test( 'renders payment request title and description', () => {
		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="payment_request" />
		);

		const heading = screen.queryByRole( 'heading', {
			name: 'Settings',
		} );
		expect( heading ).toBeInTheDocument();
	} );

	test( 'renders payment request enable setting and confirm its checkbox label', () => {
		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="payment_request" />
		);

		const label = screen.getByRole( 'checkbox', {
			name: 'Enable Apple Pay / Google Pay as express payment buttons',
		} );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders woopay settings and confirm its checkbox label', () => {
		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="woopay" />
		);

		const label = screen.getByRole( 'checkbox', {
			name: 'Enable WooPay',
		} );
		expect( label ).toBeInTheDocument();
	} );

	test( 'renders WooPay express button appearance settings if feature flag is enabled and confirm its first input', () => {
		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="woopay" />
		);

		expect(
			screen.queryByRole( 'combobox', {
				name: 'Call to action',
			} )
		).toBeInTheDocument();
	} );

	test( 'does not render WooPay express button appearance settings if feature flag is disabled', () => {
		global.wcpaySettings.featureFlags.woopayExpressCheckout = false;

		renderWithSettingsProvider(
			<ExpressCheckoutSettings methodId="woopay" />
		);

		expect(
			screen.queryByRole( 'heading', {
				name: 'Call to action',
			} )
		).not.toBeInTheDocument();
	} );
} );
