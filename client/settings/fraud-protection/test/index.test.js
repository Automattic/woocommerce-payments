/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import FraudProtection from '..';
import { useCurrentProtectionLevel } from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useCurrentProtectionLevel: jest.fn(),
} ) );

describe( 'FraudProtection', () => {
	beforeEach( () => useCurrentProtectionLevel.mockReturnValue( 'standard' ) );

	it( 'renders', () => {
		render(
			<WCPaySettingsContext.Provider>
				<FraudProtection />
			</WCPaySettingsContext.Provider>
		);

		const recommended = screen.getByText( /Recommended/ );
		expect( recommended ).toBeInTheDocument();
		expect( recommended ).toHaveTextContent( '(Recommended)' );

		const standardHelp = screen.getByText( /Standard protection:/ );
		expect( standardHelp ).toBeInTheDocument();
		expect( standardHelp ).toHaveTextContent(
			"Standard protection: Provides a standard level of filtering that's suitable for most businesses."
		);
	} );
} );
