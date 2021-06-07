/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import AdvancedSettings from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';

describe( 'AdvancedSettings', () => {
	it( 'toggles the advanced settings section', () => {
		const settingsContext = {
			featureFlags: { upe: true },
		};

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<AdvancedSettings />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.queryByText( 'Debug mode' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Block appearance' )
		).not.toBeInTheDocument();

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect( screen.queryByText( 'Debug mode' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Block appearance' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Debug mode' ) ).toHaveFocus();
	} );

	it( 'BlockAppearance is not displayed if UPE feature flag is disabled', () => {
		const settingsContext = {
			featureFlags: {},
		};

		render(
			<WCPaySettingsContext.Provider value={ settingsContext }>
				<AdvancedSettings />
			</WCPaySettingsContext.Provider>
		);

		userEvent.click( screen.getByText( 'Advanced settings' ) );

		expect(
			screen.queryByText( 'Block appearance' )
		).not.toBeInTheDocument();
	} );
} );
