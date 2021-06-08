/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsManager from '..';
import WCPaySettingsContext from '../../wcpay-settings-context';

describe( 'SettingsManager', () => {
	it( 'renders the PaymentMethods section if the UPE feature flag is enabled', () => {
		const context = { featureFlags: { upe: true } };

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<SettingsManager />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText( 'Payments accepted on checkout' )
		).toBeInTheDocument();
	} );

	it( 'does not render the PaymentMethods section if the UPE feature flag is disabled', () => {
		const context = { featureFlags: {} };
		render(
			<WCPaySettingsContext.Provider value={ context }>
				<SettingsManager />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText( 'Payments accepted on checkout' )
		).not.toBeInTheDocument();
	} );
} );
