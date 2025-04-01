/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GooglePayTestModeCompatibilityNotice from '../google-pay-test-mode-compatibility-notice';
import { useTestMode, usePaymentRequestEnabledSettings } from 'wcpay/data';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useTestMode: jest.fn(),
	usePaymentRequestEnabledSettings: jest.fn(),
} ) );

describe( 'GooglePayTestModeCompatibilityNotice', () => {
	beforeEach( () => {
		jest.resetAllMocks();
	} );

	it( 'does not render when the account is not live', () => {
		useTestMode.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		const { container } = render(
			<WCPaySettingsContext.Provider
				value={ { accountStatus: { isLive: false } } }
			>
				<GooglePayTestModeCompatibilityNotice />
			</WCPaySettingsContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when test mode is not active', () => {
		useTestMode.mockReturnValue( [ false ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		const { container } = render(
			<WCPaySettingsContext.Provider
				value={ { accountStatus: { isLive: true } } }
			>
				<GooglePayTestModeCompatibilityNotice />
			</WCPaySettingsContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'does not render when Google Pay is not enabled', () => {
		useTestMode.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ false ] );

		const { container } = render(
			<WCPaySettingsContext.Provider
				value={ { accountStatus: { isLive: true } } }
			>
				<GooglePayTestModeCompatibilityNotice />
			</WCPaySettingsContext.Provider>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders the notice when the requirements are met', () => {
		useTestMode.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		render(
			<WCPaySettingsContext.Provider
				value={ { accountStatus: { isLive: true } } }
			>
				<GooglePayTestModeCompatibilityNotice />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.getByText( /Google Pay is incompatible with test mode/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeInTheDocument();
	} );
} );
