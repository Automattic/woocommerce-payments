/**
 * Tests for the dynamic place order button feature flag functionality.
 */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentRequestSettings from '../payment-request-settings';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
	useWooPayEnabledSettings,
	useAppleGooglePayInPaymentMethodsOptionsEnabledSettings,
} from '../../../data';

// Mock the data hooks
jest.mock( '../../../data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePaymentRequestLocations: jest.fn(),
	useTestMode: jest.fn().mockReturnValue( [ false ] ),
	usePaymentRequestButtonType: jest.fn().mockReturnValue( [ 'default' ] ),
	usePaymentRequestButtonBorderRadius: jest.fn().mockReturnValue( [ 4 ] ),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'small' ] ),
	usePaymentRequestButtonTheme: jest.fn().mockReturnValue( [ 'dark' ] ),
	useWooPayEnabledSettings: jest.fn(),
	useWooPayShowIncompatibilityNotice: jest.fn().mockReturnValue( false ),
	useAppleGooglePayInPaymentMethodsOptionsEnabledSettings: jest.fn(),
} ) );

// Mock the global wcpaySettings
const mockWcpaySettings = {
	featureFlags: {
		isDynamicCheckoutPlaceOrderButtonEnabled: true,
	},
	accountStatus: {
		isLive: false, // Set to false to avoid GooglePayTestModeCompatibilityNotice issues
	},
	restUrl: 'http://example.com/wp-json/',
};

// Mock the settings provider
const renderWithSettingsProvider = ( component ) => {
	global.wcpaySettings = mockWcpaySettings;
	return render(
		<WCPaySettingsContext.Provider
			value={ { accountStatus: { isLive: false }, featureFlags: {} } }
		>
			{ component }
		</WCPaySettingsContext.Provider>
	);
};

describe( 'Dynamic Place Order Button Feature Flag', () => {
	beforeEach( () => {
		// Reset global settings before each test
		global.wcpaySettings = { ...mockWcpaySettings };

		// Mock the data hooks
		usePaymentRequestEnabledSettings.mockReturnValue( [
			false,
			jest.fn(),
		] );
		usePaymentRequestLocations.mockReturnValue( [ [], jest.fn() ] );
		useWooPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );
		useAppleGooglePayInPaymentMethodsOptionsEnabledSettings.mockReturnValue(
			[ false, jest.fn() ]
		);
	} );

	afterEach( () => {
		// Clean up after each test
		delete global.wcpaySettings;
	} );

	it( 'renders Apple Pay / Google Pay in payment methods checkbox when feature flag is enabled', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = true;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Check if the Apple Pay / Google Pay in payment methods checkbox is rendered
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).toBeInTheDocument();
	} );

	it( 'does not render Apple Pay / Google Pay in payment methods checkbox when feature flag is disabled', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = false;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Check if the Apple Pay / Google Pay in payment methods checkbox is NOT rendered
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );

	it( 'handles missing feature flag gracefully', () => {
		// Remove the feature flag entirely
		delete global.wcpaySettings.featureFlags
			.isDynamicCheckoutPlaceOrderButtonEnabled;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should not render the checkbox when feature flag is missing
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );

	it( 'handles null feature flag gracefully', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = null;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should not render the checkbox when feature flag is null
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );

	it( 'handles undefined feature flag gracefully', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = undefined;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should not render the checkbox when feature flag is undefined
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );

	it( 'handles string "true" feature flag as truthy', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled =
			'true';

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should render the checkbox when feature flag is string "true"
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).toBeInTheDocument();
	} );

	it( 'handles string "false" feature flag as truthy', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled =
			'false';

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should render the checkbox when feature flag is string "false" (non-empty string is truthy)
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).toBeInTheDocument();
	} );

	it( 'handles empty string feature flag as falsy', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled =
			'';

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should not render the checkbox when feature flag is empty string
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );

	it( 'handles number 1 feature flag as truthy', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = 1;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should render the checkbox when feature flag is number 1
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).toBeInTheDocument();
	} );

	it( 'handles number 0 feature flag as falsy', () => {
		global.wcpaySettings.featureFlags.isDynamicCheckoutPlaceOrderButtonEnabled = 0;

		renderWithSettingsProvider(
			<PaymentRequestSettings section="enable" />
		);

		// Should not render the checkbox when feature flag is number 0
		const checkbox = screen.queryByRole( 'checkbox', {
			name: /apple pay.*google pay.*payment methods/i,
		} );

		expect( checkbox ).not.toBeInTheDocument();
	} );
} );
